<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;

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
}
