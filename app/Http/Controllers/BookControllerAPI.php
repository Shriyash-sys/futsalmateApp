<?php

namespace App\Http\Controllers;

use App\Models\Book;
use App\Models\Court;
use Illuminate\Support\Str;
use Illuminate\Http\Request;

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
            'payment_status' => $validated['payment'] === 'eSewa' ? 'Paid' : 'Pending',
            'status' => $validated['payment'] === 'Cash' ? 'Pending' : 'PendingPayment',
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
        $product_code = 'EPAYTEST';
        $product_service_charge = 0;
        $product_delivery_charge = 0;
        $success_url = url('/api/book/esewa/success');
        $failure_url = url('/api/book/esewa/failure');
        $signed_field_names = "total_amount,transaction_uuid,product_code";

        $message = "total_amount=$total_amount,transaction_uuid=$transaction_uuid,product_code=$product_code";
        $secret_key = "8gBm/:&EnhH.1/q"; // Replace with your actual merchant key
        $signature = base64_encode(hash_hmac('sha256', $message, $secret_key, true));

        return response()->json([
            'status' => 'success',
            'message' => 'Booking created. Proceed with eSewa payment.',
            'booking' => $booking,
            'payment' => [
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

        if ($data['status'] == "COMPLETE") {
            $updated = Book::where('transaction_uuid', $data['transaction_uuid'])
                ->where('status', 'PendingPayment')
                ->update(['status' => 'Confirmed']);

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
                    'message' => 'Booking not found or already updated.',
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

        // Delete the failed booking
        $deleted = Book::where('transaction_uuid', $txn)
            ->where('status', 'PendingPayment')
            ->delete();

        // Alternative: mark it as failed instead of deleting
        // $updated = Book::where('transaction_uuid', $txn)
        //     ->where('status', 'PendingPayment')
        //     ->update(['status' => 'Failed']);

        if ($deleted) {
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
}
