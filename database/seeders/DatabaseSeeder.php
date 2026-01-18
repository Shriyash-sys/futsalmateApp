<?php

namespace Database\Seeders;

use App\Models\Admin;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        // User::factory()->create([
        //     'full_name' => 'Test User',
        //     'email' => 'test@example.com',
        // ]);

        // Seed admin data
        Admin::factory()->create([
            'full_name' => 'Admin User',
            'email' => 'admin@futsalmate.com',
            'phone' => '+977-9745388429',
            'password' => \Illuminate\Support\Facades\Hash::make('password'),
        ]);
    }
}
