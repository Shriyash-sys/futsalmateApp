<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Hash;

class SignupTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_signup_sets_user_type_user()
    {
        $response = $this->postJson('/api/signup', [
            'full_name' => 'Test User',
            'email' => 'user@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'terms' => true,
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('users', [
            'email' => 'user@example.com',
            'user_type' => 'user',
        ]);
    }

    public function test_user_signup_sends_otp()
    {
        Notification::fake();

        $response = $this->postJson('/api/signup', [
            'full_name' => 'Test User',
            'email' => 'user-otp@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'terms' => true,
        ]);

        $response->assertStatus(201);

        $user = User::where('email', 'user-otp@example.com')->first();
        Notification::assertSentTo($user, \App\Notifications\UserEmailOtp::class);
        $this->assertNotNull($user->email_otp);
        $this->assertNotNull($user->email_otp_expires_at);
    }

    public function test_vendor_signup_sets_user_type_vendor()
    {
        $response = $this->postJson('/api/signup', [
            'full_name' => 'Vendor User',
            'email' => 'vendor@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'terms' => true,
            'type' => 'vendor',
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('users', [
            'email' => 'vendor@example.com',
            'user_type' => 'vendor',
        ]);
    }

    public function test_resend_otp_returns_already_valid_if_not_forced()
    {
        Notification::fake();

        $user = User::create([
            'full_name' => 'Test User',
            'email' => 'resend-user@example.test',
            'password' => Hash::make('password123'),
            'terms' => true,
            'email_otp' => '123456',
            'email_otp_expires_at' => now()->addMinutes(10)
        ]);

        $response = $this->postJson('/api/email/verify/resend-otp', [
            'email' => $user->email
        ]);

        $response->assertStatus(200);
        $response->assertJsonFragment(['message' => 'OTP already sent and still valid.']);
        Notification::assertNothingSent();
    }

    public function test_resend_otp_with_force_sends_new_otp()
    {
        Notification::fake();

        $user = User::create([
            'full_name' => 'Test User',
            'email' => 'resend-force@example.test',
            'password' => Hash::make('password123'),
            'terms' => true,
            'email_otp' => '123456',
            'email_otp_expires_at' => now()->addMinutes(10)
        ]);

        $oldOtp = $user->email_otp;

        $response = $this->postJson('/api/email/verify/resend-otp', [
            'email' => $user->email,
            'force' => true
        ]);

        $response->assertStatus(200);
        $response->assertJsonFragment(['message' => 'Verification code sent.']);
        Notification::assertSentTo($user, \App\Notifications\UserEmailOtp::class);

        $user->refresh();
        $this->assertNotEquals($oldOtp, $user->email_otp);
        $this->assertTrue($user->email_otp_expires_at->isFuture());
    }

    public function test_resend_otp_throttles_after_limit()
    {
        Notification::fake();

        $user = User::factory()->create();

        $limit = config('auth.otp_resend_limit', 5);

        for ($i = 0; $i < $limit; $i++) {
            $this->postJson('/api/email/verify/resend-otp', [
                'email' => $user->email,
                'force' => true
            ])->assertStatus(200);
        }

        $this->postJson('/api/email/verify/resend-otp', [
            'email' => $user->email,
            'force' => true
        ])->assertStatus(429);
    }
}
