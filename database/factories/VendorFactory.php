<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class VendorFactory extends Factory
{
    protected $model = \App\Models\Vendor::class;

    public function definition()
    {
        return [
            'name' => $this->faker->company,
            'email' => $this->faker->unique()->safeEmail,
            'email_verified_at' => null,
            'password' => \Illuminate\Support\Facades\Hash::make('password123'),
            'phone' => $this->faker->phoneNumber,
            'address' => $this->faker->address,
            'owner_name' => $this->faker->name,
        ];
    }
}
