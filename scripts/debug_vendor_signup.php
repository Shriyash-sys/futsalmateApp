<?php
require __DIR__ . '/../vendor/autoload.php';

use Illuminate\Http\Request;
use App\Http\Controllers\SignupControllerAPI;

$payload = [
    'name' => 'Test Vendor',
    'email' => 'vendor@example.test',
    'password' => 'password123',
    'password_confirmation' => 'password123',
    'phone' => '1234567890',
    'address' => 'Some address',
    'owner_name' => 'Owner',
    'terms' => true
];

$req = Request::create('/api/vendor/signup', 'POST', $payload);
$controller = new SignupControllerAPI();

try {
    $res = $controller->vendorSignup($req);
    if (is_object($res)) {
        echo "Status: " . $res->getStatusCode() . "\n";
        echo $res->getContent() . "\n";
    } else {
        var_dump($res);
    }
} catch (Exception $e) {
    echo "Exception: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString();
}
