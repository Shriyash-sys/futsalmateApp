<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use App\Models\Vendor;
use App\Models\User;
use App\Notifications\VendorEmailOtp;

class VendorSignupTest extends TestCase
{
    use RefreshDatabase;

    public function test_vendor_signup_creates_vendor_and_sends_verification()
    {
        $this->withoutExceptionHandling();
        Notification::fake();

        $payload = [
            'name' => 'Test Vendor',
            'email' => 'vendor@example.test',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'phone' => '1234567890',
            'address' => '123 Example St',
            'owner_name' => 'Test Owner',
            'terms' => true
        ];

        $response = $this->postJson('/api/vendor/signup', $payload);

        $response->assertStatus(201);
        $this->assertDatabaseHas('vendors', ['email' => 'vendor@example.test']);

        $vendor = Vendor::where('email', 'vendor@example.test')->first();
        Notification::assertSentTo($vendor, VendorEmailOtp::class);

        // OTP saved and expiry set
        $this->assertNotNull($vendor->email_otp);
        $this->assertNotNull($vendor->email_otp_expires_at);
    }

    public function test_vendor_cannot_login_before_verification()
    {
        $vendor = Vendor::factory()->create(["email_verified_at" => null, "password" => \Illuminate\Support\Facades\Hash::make('password123')]);

        $response = $this->postJson('/api/vendor/login', [
            'email' => $vendor->email,
            'password' => 'password123'
        ]);

        $response->assertStatus(403);
        $response->assertJsonFragment(['message' => 'Please verify your vendor email before logging in.']);
    }

    public function test_vendor_can_login_after_verification()
    {
        $vendor = Vendor::factory()->create(["email_verified_at" => now(), "password" => \Illuminate\Support\Facades\Hash::make('password123')]);

        $response = $this->postJson('/api/vendor/login', [
            'email' => $vendor->email,
            'password' => 'password123'
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure(['status','message','vendor','token']);
    }

    public function test_vendor_can_verify_using_otp_and_then_login()
    {
        Notification::fake();

        $payload = [
            'name' => 'OTP Vendor',
            'email' => 'otp-vendor@example.test',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'phone' => '1234567890',
            'address' => '123 Example St',
            'owner_name' => 'OTP Owner',
            'terms' => true
        ];

        $this->postJson('/api/vendor/signup', $payload)->assertStatus(201);

        $vendor = Vendor::where('email', 'otp-vendor@example.test')->first();
        $this->assertNotNull($vendor->email_otp);

        // Verify using OTP
        $response = $this->postJson('/api/vendor/email/verify/otp', [
            'email' => $vendor->email,
            'otp' => $vendor->email_otp
        ]);

        $response->assertStatus(200);

        // Now try to login
        $login = $this->postJson('/api/vendor/login', [
            'email' => $vendor->email,
            'password' => 'password123'
        ]);

        $login->assertStatus(200);
        $login->assertJsonStructure(['status','message','vendor','token']);
    }

    public function test_vendor_resend_otp_returns_already_valid_if_not_forced()
    {
        Notification::fake();

        $vendor = Vendor::factory()->create([
            'email_otp' => '654321',
            'email_otp_expires_at' => now()->addMinutes(10),
            'email_verified_at' => null
        ]);

        $response = $this->postJson('/api/vendor/email/verify/resend-otp', [
            'email' => $vendor->email
        ]);

        $response->assertStatus(200);
        $response->assertJsonFragment(['message' => 'OTP already sent and still valid.']);
        Notification::assertNothingSent();
    }

    public function test_vendor_resend_otp_with_force_sends_new_otp()
    {
        Notification::fake();

        $vendor = Vendor::factory()->create([
            'email_otp' => '654321',
            'email_otp_expires_at' => now()->addMinutes(10),
            'email_verified_at' => null
        ]);

        $old = $vendor->email_otp;

        $response = $this->postJson('/api/vendor/email/verify/resend-otp', [
            'email' => $vendor->email,
            'force' => true
        ]);

        $response->assertStatus(200);
        $response->assertJsonFragment(['message' => 'Verification code sent.']);
        Notification::assertSentTo($vendor, VendorEmailOtp::class);

        $vendor->refresh();
        $this->assertNotEquals($old, $vendor->email_otp);
        $this->assertTrue($vendor->email_otp_expires_at->isFuture());
    }

    public function test_vendor_resend_otp_throttles_after_limit()
    {
        Notification::fake();

        $vendor = Vendor::factory()->create();

        $limit = config('auth.otp_resend_limit', 5);

        for ($i = 0; $i < $limit; $i++) {
            $this->postJson('/api/vendor/email/verify/resend-otp', [
                'email' => $vendor->email,
                'force' => true
            ])->assertStatus(200);
        }

        $this->postJson('/api/vendor/email/verify/resend-otp', [
            'email' => $vendor->email,
            'force' => true
        ])->assertStatus(429);
    }
}
