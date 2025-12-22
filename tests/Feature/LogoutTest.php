<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\User;
use App\Models\Vendor;

class LogoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_logout_revokes_current_token()
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
            'password' => \Illuminate\Support\Facades\Hash::make('password123')
        ]);

        $token = $user->createToken('auth-token')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
                         ->postJson('/api/logout');

        $response->assertStatus(200)->assertJsonFragment(['message' => 'Logged out successfully.']);

        // The token should be removed from the database
        $this->assertDatabaseMissing('personal_access_tokens', [
            'tokenable_id' => $user->id,
            'tokenable_type' => User::class
        ]);

        // Refresh application to clear any lingering test client state (cookies, etc.)
        $this->refreshApplication();

        $this->withHeader('Authorization', 'Bearer ' . $token)
             ->getJson('/api/user')
             ->assertStatus(401);
    }

    public function test_vendor_logout_revokes_current_token()
    {
        $vendor = Vendor::factory()->create([
            'email_verified_at' => now(),
            'password' => \Illuminate\Support\Facades\Hash::make('password123')
        ]);

        $token = $vendor->createToken('vendor-token')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
                         ->postJson('/api/vendor/logout');

        $response->assertStatus(200)->assertJsonFragment(['message' => 'Vendor logged out successfully.']);

        // Token removed for vendor
        $this->assertDatabaseMissing('personal_access_tokens', [
            'tokenable_id' => $vendor->id,
            'tokenable_type' => Vendor::class
        ]);

        // Refresh application to clear any lingering test client state (cookies, etc.)
        $this->refreshApplication();

        $this->withHeader('Authorization', 'Bearer ' . $token)
             ->getJson('/api/user')
             ->assertStatus(401);
    }

    public function test_logout_without_auth_returns_unauthenticated()
    {
        $this->postJson('/api/logout')
             ->assertStatus(401);
    }
}
