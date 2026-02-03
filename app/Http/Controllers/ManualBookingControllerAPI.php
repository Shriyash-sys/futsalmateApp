<?php

namespace App\Http\Controllers;

use App\Models\Book;
use App\Models\Court;
use App\Models\Vendor;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class ManualBookingControllerAPI extends Controller
{
    /**
     * Vendor adds a booking manually (walk-in)
     */
    public function manualBookCourt(Request $request)
    {
        $vendor = $request->user();
        if (!$vendor) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthenticated.'
            ], 401);
        }

        if (!($vendor instanceof Vendor)) {
            return response()->json([
                'status' => 'error',
                'message' => 'This endpoint is only for vendors.'
            ], 403);
        }

        $validated = $request->validate([
            'date' => 'required|date|after_or_equal:today',
            'start_time' => 'required|string|max:255',
            'end_time' => 'required|string|max:255',
            'notes' => 'nullable|min:0|max:255|string',
            'customer_name' => 'required|string|max:255',
            'customer_phone' => 'required|string|max:15',
            'court_id' => 'required|exists:courts,id',
            'payment' => 'required|string|in:eSewa,Cash',
        ]);

        $court = Court::where('id', $validated['court_id'])
            ->where('vendor_id', $vendor->id)
            ->first();

        if (!$court) {
            return response()->json([
                'status' => 'error',
                'message' => 'Court not found or not authorized.'
            ], 404);
        }

        // Check if booking times are within court's operating hours
        if ($validated['start_time'] < $court->opening_time || $validated['end_time'] > $court->closing_time) {
            return response()->json([
                'status' => 'error',
                'message' => "Booking time must be between court's operating hours ({$court->opening_time} - {$court->closing_time})."
            ], 422);
        }

        // Check if start_time is before end_time
        if ($validated['start_time'] >= $validated['end_time']) {
            return response()->json([
                'status' => 'error',
                'message' => 'Start time must be before end time.'
            ], 422);
        }

        // Check for time slot conflicts
        $conflictingBooking = Book::where('court_id', $validated['court_id'])
            ->where('date', $validated['date'])
            ->where('status', '!=', 'Cancelled')
            ->where('status', '!=', 'Rejected')
            ->where(function ($query) use ($validated) {
                $query->whereBetween('start_time', [$validated['start_time'], $validated['end_time']])
                    ->orWhereBetween('end_time', [$validated['start_time'], $validated['end_time']])
                    ->orWhere(function ($q) use ($validated) {
                        $q->where('start_time', '<=', $validated['start_time'])
                            ->where('end_time', '>=', $validated['end_time']);
                    });
            })
            ->exists();

        if ($conflictingBooking) {
            return response()->json([
                'status' => 'error',
                'message' => 'This time slot is already booked for the selected date.'
            ], 409);
        }

        $transaction_uuid = Str::uuid()->toString();

        // For manual bookings, both payment methods are marked as Paid and Confirmed
        // since the vendor is recording a completed transaction
        $booking = Book::create([
            'transaction_uuid' => $transaction_uuid,
            'date' => $validated['date'],
            'start_time' => $validated['start_time'],
            'end_time' => $validated['end_time'],
            'notes' => $validated['notes'] ?? null,
            'customer_name' => $validated['customer_name'],
            'customer_phone' => $validated['customer_phone'],
            'payment' => $validated['payment'],
            'court_id' => $validated['court_id'],
            'user_id' => null,
            'price' => $court->price,
            'payment_status' => 'Paid',
            'status' => 'Confirmed',
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Booking created manually by vendor.',
            'booking' => $booking->load('court')
        ], 201);
    }
}
