<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\User;
use App\Models\Vendor;
use App\Models\Court;
use App\Models\Book;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    // nothing for now
});

it('marks booking paid and confirmed on eSewa success', function () {
    // Create a user and a court
    $user = User::factory()->create();

    $vendorForCourt = Vendor::factory()->create();

    $court = Court::create([
        'court_name' => 'Test Court',
        'location' => 'Test Location',
        'price' => 500,
        'status' => 'Active',
        'vendor_id' => $vendorForCourt->id,
    ]);

    // Book a court with eSewa payment
    $resp = $this->actingAs($user, 'sanctum')->postJson('/api/book', [
        'date' => now()->addDay()->toDateString(),
        'start_time' => '10:00',
        'end_time' => '11:00',
        'court_id' => $court->id,
        'payment' => 'eSewa',
    ]);

    $resp->assertStatus(201);

    $booking = $resp->json('booking');
    expect($booking)->not->toBeNull();
    $txn = $booking['transaction_uuid'];

    // Simulate eSewa success callback
    $payload = ['status' => 'COMPLETE', 'transaction_uuid' => $txn];
    $encoded = base64_encode(json_encode($payload));

    $successResp = $this->getJson('/api/book/esewa/success?data=' . urlencode($encoded));
    $successResp->assertStatus(200)
        ->assertJsonFragment(['status' => 'success'])
        ->assertJsonFragment(['message' => 'Payment successful and booking confirmed.']);

    // Assert booking is paid and confirmed in DB
    $this->assertDatabaseHas('books', [
        'transaction_uuid' => $txn,
        'payment_status' => 'Paid',
        'status' => 'Confirmed'
    ]);
});

it('allows vendor to approve and reject bookings and enforces ownership', function () {
    $vendor = Vendor::factory()->create();
    $otherVendor = Vendor::factory()->create();

    $court = Court::create([
        'court_name' => 'Vendor Court',
        'location' => 'Here',
        'price' => 1000,
        'status' => 'Active',
        'vendor_id' => $vendor->id,
    ]);

    $user = User::factory()->create();

    // Create a cash booking (pending)
    $booking = Book::create([
        'transaction_uuid' => Str::uuid()->toString(),
        'date' => now()->addDay()->toDateString(),
        'start_time' => '12:00',
        'end_time' => '13:00',
        'notes' => null,
        'payment' => 'Cash',
        'court_id' => $court->id,
        'user_id' => $user->id,
        'price' => $court->price,
        'payment_status' => 'Pending',
        'status' => 'Pending',
    ]);

    // Ensure booking exists before vendor action
    $this->assertDatabaseHas('books', ['id' => $booking->id, 'status' => 'Pending']);

    // Approve as correct vendor
    $approveResp = $this->actingAs($vendor, 'sanctum')->postJson("/api/vendor/bookings/{$booking->id}/approve");

    if ($approveResp->status() !== 200) {
        fwrite(STDERR, "Approve response body: " . $approveResp->getContent() . PHP_EOL);
    }

    $approveResp->assertStatus(200)->assertJsonFragment(['status' => 'success']);

    $this->assertDatabaseHas('books', ['id' => $booking->id, 'status' => 'Confirmed']);
    // Create another pending booking to test reject and ownership
    $booking2 = Book::create([
        'transaction_uuid' => Str::uuid()->toString(),
        'date' => now()->addDays(2)->toDateString(),
        'start_time' => '14:00',
        'end_time' => '15:00',
        'notes' => null,
        'payment' => 'Cash',
        'court_id' => $court->id,
        'user_id' => $user->id,
        'price' => $court->price,
        'payment_status' => 'Pending',
        'status' => 'Pending',
    ]);

    // Reject as other vendor (should be unauthorized)
    $unauthResp = $this->actingAs($otherVendor, 'sanctum')->postJson("/api/vendor/bookings/{$booking2->id}/reject");
    $unauthResp->assertStatus(403);

    // Reject as correct vendor
    $rejectResp = $this->actingAs($vendor, 'sanctum')->postJson("/api/vendor/bookings/{$booking2->id}/reject");
    $rejectResp->assertStatus(200)->assertJsonFragment(['status' => 'success']);

    $this->assertDatabaseHas('books', ['id' => $booking2->id, 'status' => 'Rejected']);
});
