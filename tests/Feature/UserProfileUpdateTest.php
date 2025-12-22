<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\User;

class UserProfileUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_update_profile()
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
            'password' => \Illuminate\Support\Facades\Hash::make('password123')
        ]);

        $token = $user->createToken('auth-token')->plainTextToken;

        $payload = [
            'full_name' => 'New Name',
            'email' => 'new-email@example.test',
            'phone' => '9998887777'
        ];

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
                         ->patchJson('/api/profile', $payload);

        $response->assertStatus(200)
                 ->assertJsonFragment(['status' => 'success', 'message' => 'Profile updated successfully'])
                 ->assertJsonFragment(['email' => 'new-email@example.test', 'full_name' => 'New Name']);

        $this->assertDatabaseHas('users', ['id' => $user->id, 'email' => 'new-email@example.test']);
    }

    public function test_unauthenticated_update_returns_401()
    {
        $this->patchJson('/api/profile', [])->assertStatus(401);
    }

    public function test_cannot_change_email_to_existing_one()
    {
        $existing = User::factory()->create(['email' => 'taken@example.test']);
        $user = User::factory()->create();
        $token = $user->createToken('auth-token')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer ' . $token)
             ->patchJson('/api/profile', ['email' => 'taken@example.test'])
             ->assertStatus(422);
    }
}
