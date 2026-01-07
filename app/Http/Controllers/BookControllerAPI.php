<?php

namespace App\Http\Controllers;

use App\Models\Book;
use App\Models\Court;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class BookControllerAPI extends Controller
{
    /**
     * Book a court
     */
    public function bookCourt(Request $request)
    {
        $validated = $request->validate([
            'date' => 'required|date|after_or_equal:today',
            'start_time' => 'required|string|max:255',
            'end_time' => 'required|string|max:255',
            'notes' => 'nullable|min:0|max:255|string',
            'court_id' => 'required|exists:courts,id',
            'payment' => 'required|string|in:eSewa,Cash',
        ]);

        $user = $request->user();
        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthenticated.'
            ], 401);
        }

        $court = Court::find($request->court_id);
        if (!$court) {
            return response()->json([
                'status' => 'error',
                'message' => 'Court not found.'
            ], 404);
        }

        $transaction_uuid = Str::uuid()->toString();

        $booking = Book::create([
            'transaction_uuid' => $transaction_uuid,
            'date' => $validated['date'],
            'start_time' => $validated['start_time'],
            'end_time' => $validated['end_time'],
            'notes' => $validated['notes'] ?? null,
            'payment' => $validated['payment'],
            'court_id' => $validated['court_id'],
            'user_id' => $user->id,
            'price' => $court->price,
            'payment_status' => 'Pending',
            'status' => 'Pending',
        ]);

        if ($validated['payment'] === 'Cash') {
            return response()->json([
                'status' => 'success',
                'message' => 'Court booked successfully. Please pay in cash at the venue.',
                'booking' => $booking
            ], 201);
        }

        // Prepare eSewa payment data
        $amount = $booking->price;
        $tax_amount = 0;
        $total_amount = $amount + $tax_amount;
        $product_code = config('services.esewa.merchant_code', 'EPAYTEST');
        $product_service_charge = 0;
        $product_delivery_charge = 0;
        $success_url = url('/api/book/esewa/success');
        $failure_url = url('/api/book/esewa/failure');
        $signed_field_names = "total_amount,transaction_uuid,product_code";

        $message = "total_amount=$total_amount,transaction_uuid=$transaction_uuid,product_code=$product_code";
        $secret_key = config('services.esewa.secret_key');
        $signature = base64_encode(hash_hmac('sha256', $message, $secret_key, true));

        return response()->json([
            'status' => 'success',
            'message' => 'Booking created. Proceed with eSewa payment.',
            'booking' => $booking,
            'payment' => [
                'payment_url' => config('services.esewa.payment_url'),
                'amount' => $amount,
                'tax_amount' => $tax_amount,
                'total_amount' => $total_amount,
                'transaction_uuid' => $transaction_uuid,
                'product_code' => $product_code,
                'product_service_charge' => $product_service_charge,
                'product_delivery_charge' => $product_delivery_charge,
                'success_url' => $success_url,
                'failure_url' => $failure_url,
                'signed_field_names' => $signed_field_names,
                'signature' => $signature
            ]
        ], 201);
    }

    /**
     * Handle eSewa payment success callback
     */
    public function success(Request $request)
    {
        $encodedData = $request->query('data');

        if (!$encodedData) {
            return response()->json([
                'status' => 'error',
                'message' => 'No payment data received.'
            ], 400);
        }

        // Base64 decode
        $jsonData = base64_decode($encodedData);

        // JSON decode to associative array
        $data = json_decode($jsonData, true);

        if (!$data) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid payment data format.'
            ], 400);
        }

        // Verify signature for security
        if (isset($data['signed_field_names']) && isset($data['signature'])) {
            $secret_key = config('services.esewa.secret_key');
            $signedFields = explode(',', $data['signed_field_names']);
            $message = '';
            foreach ($signedFields as $field) {
                if (isset($data[$field])) {
                    $message .= "$field={$data[$field]}";
                    if ($field !== end($signedFields)) {
                        $message .= ',';
                    }
                }
            }
            $expectedSignature = base64_encode(hash_hmac('sha256', $message, $secret_key, true));
            
            if ($data['signature'] !== $expectedSignature) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Invalid payment signature. Possible fraud attempt.'
                ], 400);
            }
        }

        if ($data['status'] == "COMPLETE") {
            // Mark payment as Paid and auto-confirm booking
            $updated = Book::where('transaction_uuid', $data['transaction_uuid'])
                ->where('payment_status', '!=', 'Paid')
                ->where('status', '!=', 'Cancelled')
                ->update(['payment_status' => 'Paid', 'status' => 'Confirmed']);

            if ($updated) {
                $booking = Book::where('transaction_uuid', $data['transaction_uuid'])->first();
                
                return response()->json([
                    'status' => 'success',
                    'message' => 'Payment successful and booking confirmed.',
                    'booking' => $booking,
                    'payment_data' => $data
                ], 200);
            } else {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Booking not found or already processed.',
                    'payment_data' => $data
                ], 404);
            }
        }

        return response()->json([
            'status' => 'error',
            'message' => 'Payment status is not complete.',
            'payment_data' => $data
        ], 400);
    }

    /**
     * Handle eSewa payment failure callback
     */
    public function failure(Request $request)
    {
        $txn = $request->query('txn');

        if (!$txn) {
            return response()->json([
                'status' => 'error',
                'message' => 'No transaction ID provided.'
            ], 400);
        }

        // Mark the failed booking as cancelled and payment failed
        $updated = Book::where('transaction_uuid', $txn)
            ->where('payment_status', '!=', 'Paid')
            ->update(['payment_status' => 'Failed', 'status' => 'Cancelled']);

        if ($updated) {
            return response()->json([
                'status' => 'success',
                'message' => 'Payment failed. Booking has been cancelled.',
                'transaction_uuid' => $txn
            ], 200);
        }

        return response()->json([
            'status' => 'error',
            'message' => 'Booking not found or already processed.',
            'transaction_uuid' => $txn
        ], 404);
    }

    /**
     * Edit a booking
     */
    public function editBooking(Request $request, $id)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthenticated.'
            ], 401);
        }

        $booking = Book::with('court')->find($id);
        if (!$booking) {
            return response()->json([
                'status' => 'error',
                'message' => 'Booking not found.'
            ], 404);
        }

        // Check if the booking belongs to the authenticated user
        if ($booking->user_id !== $user->id) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized. This booking does not belong to you.'
            ], 403);
        }

        // Check if booking can be edited (e.g., not already confirmed or cancelled)
        if (in_array($booking->status, ['Confirmed', 'Cancelled', 'Rejected'])) {
            return response()->json([
                'status' => 'error',
                'message' => 'Cannot edit booking with status: ' . $booking->status
            ], 400);
        }

        $validated = $request->validate([
            'date' => 'required|date|after_or_equal:today',
            'start_time' => 'required|string|max:255',
            'end_time' => 'required|string|max:255',
        ]);

        $booking->date = $validated['date'];
        $booking->start_time = $validated['start_time'];
        $booking->end_time = $validated['end_time'];
        $booking->save();

        return response()->json([
            'status' => 'success',
            'message' => 'Booking updated successfully.',
            'booking' => $booking->load('court')
        ], 200);
    }

    /**
     * Get booked times for a specific court and date
     */
    public function getBookedTimes(Request $request)
    {
        $validated = $request->validate([
            'court_id' => 'required|exists:courts,id',
            'date' => 'required|date',
        ]);

        $bookings = Book::where('court_id', $validated['court_id'])
            ->where('date', $validated['date'])
            ->where('status', '!=', 'Rejected')
            ->where('status', '!=', 'Cancelled')
            ->get(['start_time', 'end_time', 'status']);

        return response()->json([
            'status' => 'success',
            'booked_times' => $bookings
        ], 200);
    }

    /**
     * Get booking confirmation details
     */
    public function showBookingConfirmation(Request $request, $id)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthenticated.'
            ], 401);
        }

        $booking = Book::with('court')->find($id);
        if (!$booking) {
            return response()->json([
                'status' => 'error',
                'message' => 'Booking not found.'
            ], 404);
        }

        // Check if the booking belongs to the authenticated user
        if ($booking->user_id !== $user->id) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized. This booking does not belong to you.'
            ], 403);
        }

        return response()->json([
            'status' => 'success',
            'booking' => $booking
        ], 200);
    }

    /**
     * Cancel a booking
     */
    public function cancelBooking(Request $request, $id)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthenticated.'
            ], 401);
        }

        $booking = Book::where('id', $id)->where('user_id', $user->id)->first();

        if (!$booking) {
            return response()->json([
                'status' => 'error',
                'message' => 'Booking not found or not authorized.'
            ], 404);
        }

        // Check if booking can be cancelled
        if (in_array($booking->status, ['Cancelled', 'Rejected'])) {
            return response()->json([
                'status' => 'error',
                'message' => 'Booking is already cancelled or rejected.'
            ], 400);
        }

        $booking->status = 'Cancelled';
        $booking->save();

        return response()->json([
            'status' => 'success',
            'message' => 'Booking cancelled successfully.',
            'booking' => $booking->load('court')
        ], 200);
    }

    /**
     * View booking details
     */
    public function viewBooking(Request $request, $id)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthenticated.'
            ], 401);
        }

        $booking = Book::with('court')->find($id);
        if (!$booking) {
            return response()->json([
                'status' => 'error',
                'message' => 'Booking not found.'
            ], 404);
        }

        // Check if the booking belongs to the authenticated user
        if ($booking->user_id !== $user->id) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized. This booking does not belong to you.'
            ], 403);
        }

        return response()->json([
            'status' => 'success',
            'booking' => $booking
        ], 200);
    }

    /**
     * Vendor approves a booking
     */
    public function vendorApproveBooking(Request $request, $id)
    {
        $vendor = $request->user();
        if (!$vendor) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthenticated.'
            ], 401);
        }

        $booking = Book::with('court')->find($id);
        if (!$booking) {
            return response()->json([
                'status' => 'error',
                'message' => 'Booking not found.'
            ], 404);
        }

        if ($booking->court->vendor_id !== $vendor->id) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized. This booking is not for your court.'
            ], 403);
        }

        if ($booking->payment === 'eSewa' && $booking->payment_status !== 'Paid') {
            return response()->json([
                'status' => 'error',
                'message' => 'Cannot approve booking until payment is completed.'
            ], 400);
        }

        if ($booking->status !== 'Pending') {
            return response()->json([
                'status' => 'error',
                'message' => 'Cannot approve booking with status: ' . $booking->status
            ], 400);
        }

        $booking->status = 'Confirmed';
        $booking->save();

        return response()->json([
            'status' => 'success',
            'message' => 'Booking approved.',
            'booking' => $booking
        ], 200);
    }

    /**
     * Vendor rejects a booking
     */
    public function vendorRejectBooking(Request $request, $id)
    {
        $vendor = $request->user();
        if (!$vendor) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthenticated.'
            ], 401);
        }

        $booking = Book::with('court')->find($id);
        if (!$booking) {
            return response()->json([
                'status' => 'error',
                'message' => 'Booking not found.'
            ], 404);
        }

        if ($booking->court->vendor_id !== $vendor->id) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized. This booking is not for your court.'
            ], 403);
        }

        if ($booking->status !== 'Pending') {
            return response()->json([
                'status' => 'error',
                'message' => 'Cannot reject booking with status: ' . $booking->status
            ], 400);
        }

        $booking->status = 'Rejected';
        $booking->save();

        // Optionally: handle refund for paid booking here

        return response()->json([
            'status' => 'success',
            'message' => 'Booking rejected.',
            'booking' => $booking
        ], 200);
    }
}
