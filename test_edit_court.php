<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Http\Kernel::class);

// Get the first vendor
$vendor = App\Models\Vendor::first();
$token = $vendor->createToken('test-edit')->plainTextToken;

echo "Testing vendorEditCourt with:\n";
echo "  Token: {$token}\n";
echo "  Vendor ID: {$vendor->id}\n";
echo "  Court ID: 1\n\n";

// Create a fake request
$request = \Illuminate\Http\Request::create(
    '/api/vendor/edit-courts/1',
    'PUT',
    [
        'court_name' => 'Updated Court Name',
        'location' => 'Updated Location',
        'price' => '2000',
    ]
);

$request->headers->set('Authorization', 'Bearer ' . $token);

try {
    $response = $kernel->handle($request);
    echo "Response Status: {$response->getStatusCode()}\n";
    echo "Response Body:\n";
    echo $response->getContent() . "\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}

$kernel->terminate($request, $response);
