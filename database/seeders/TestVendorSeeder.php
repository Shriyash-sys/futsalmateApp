<?php

namespace Database\Seeders;

use App\Models\Vendor;
use App\Models\Court;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class TestVendorSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create test vendor
        $vendor = Vendor::create([
            'name' => 'Test Futsal Center',
            'email' => 'vendor@test.com',
            'password' => Hash::make('password123'),
            'phone' => '+977 9841234567',
            'address' => 'Kathmandu, Nepal',
            'owner_name' => 'Test Owner'
        ]);

        echo "✓ Created vendor: {$vendor->email} (ID: {$vendor->id})\n";
        echo "  Password: password123\n\n";

        // Create test courts for this vendor
        $courts = [
            [
                'court_name' => 'Premium Indoor Court A',
                'location' => 'Thamel, Kathmandu',
                'price' => '1500',
                'description' => 'Professional indoor futsal court with premium flooring',
                'status' => 'active',
                'facilities' => json_encode(['Indoor', 'Parking', 'Showers', 'Cafeteria']),
                'latitude' => 27.7172,
                'longitude' => 85.3240,
            ],
            [
                'court_name' => 'Outdoor Court B',
                'location' => 'Patan, Lalitpur',
                'price' => '1000',
                'description' => 'Open-air futsal court with natural grass',
                'status' => 'active',
                'facilities' => json_encode(['Outdoor', 'Parking']),
                'latitude' => 27.6667,
                'longitude' => 85.3333,
            ],
            [
                'court_name' => 'Training Court C',
                'location' => 'Bhaktapur',
                'price' => '800',
                'description' => 'Budget-friendly court for practice sessions',
                'status' => 'inactive',
                'facilities' => json_encode(['Indoor', 'Internet']),
                'latitude' => 27.6710,
                'longitude' => 85.4298,
            ],
        ];

        foreach ($courts as $courtData) {
            $courtData['vendor_id'] = $vendor->id;
            $court = Court::create($courtData);
            echo "✓ Created court: {$court->court_name} (ID: {$court->id})\n";
        }

        echo "\n✓ Test data seeded successfully!\n";
        echo "  Login with: vendor@test.com / password123\n";
        echo "  You can now edit courts with IDs: 1, 2, 3\n";
    }
}
