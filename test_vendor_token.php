<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// Get the first vendor
$vendor = App\Models\Vendor::first();

if (!$vendor) {
    echo "No vendor found in database!\n";
    exit(1);
}

echo "Vendor: {$vendor->email} (ID: {$vendor->id})\n";

// Create a fresh token
$token = $vendor->createToken('test-token')->plainTextToken;
echo "Generated Token: {$token}\n\n";

// Test authentication with this token
$personalAccessToken = \Laravel\Sanctum\PersonalAccessToken::findToken($token);
if ($personalAccessToken) {
    $authenticatedUser = $personalAccessToken->tokenable;
    echo "Token authenticates as:\n";
    echo "  Class: " . get_class($authenticatedUser) . "\n";
    echo "  ID: {$authenticatedUser->id}\n";
    echo "  Email: {$authenticatedUser->email}\n\n";
    
    // Check if it's a Vendor instance
    echo "Is Vendor instance? " . ($authenticatedUser instanceof App\Models\Vendor ? "YES" : "NO") . "\n";
} else {
    echo "Token validation failed!\n";
}

// List all courts owned by this vendor
$courts = App\Models\Court::where('vendor_id', $vendor->id)->get(['id', 'court_name', 'vendor_id']);
echo "\nCourts owned by vendor ID {$vendor->id}:\n";
foreach ($courts as $court) {
    echo "  - Court ID {$court->id}: {$court->court_name} (vendor_id: {$court->vendor_id})\n";
}
