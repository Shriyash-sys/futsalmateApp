<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use App\Models\Vendor;
use App\Models\User;
use App\Notifications\VendorVerifyEmail;

class VendorSignupTest extends TestCase
{
    use RefreshDatabase;

    public function test_vendor_signup_creates_vendor_and_sends_verification()
    {
        Notification::fake();

        $payload = [
            'name' => 'Test Vendor',
            'email' => 'vendor@example.test',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'phone' => '1234567890'
        ];

        $response = $this->postJson('/api/vendor/signup', $payload);

        $response->assertStatus(201);
        $this->assertDatabaseHas('vendors', ['email' => 'vendor@example.test']);

        $vendor = Vendor::where('email', 'vendor@example.test')->first();
        Notification::assertSentTo($vendor, VendorVerifyEmail::class);
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
}
