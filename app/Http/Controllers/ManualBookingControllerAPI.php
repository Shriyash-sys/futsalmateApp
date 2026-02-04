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
            'start_time' => 'required|date_format:g A',
            'end_time' => 'required|date_format:g A',
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

        // Convert AM/PM format to 24-hour format for comparison and storage
        try {
            $startTime24 = \Carbon\Carbon::createFromFormat('g A', $validated['start_time'])->format('H:00:00');
            $endTime24 = \Carbon\Carbon::createFromFormat('g A', $validated['end_time'])->format('H:00:00');
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid time format. Please use format like "5 AM".'
            ], 422);
        }

        // Check if booking times are within court's operating hours (if set)
        if ($court->opening_time && $court->closing_time) {
            if ($startTime24 < $court->opening_time || $endTime24 > $court->closing_time) {
                return response()->json([
                    'status' => 'error',
                    'message' => "Booking time must be between court's operating hours ({$court->opening_time} - {$court->closing_time})."
                ], 422);
            }
        }

        // Check if start_time is before end_time
        if ($startTime24 >= $endTime24) {
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
            ->where(function ($query) use ($startTime24, $endTime24) {
                $query->whereBetween('start_time', [$startTime24, $endTime24])
                    ->orWhereBetween('end_time', [$startTime24, $endTime24])
                    ->orWhere(function ($q) use ($startTime24, $endTime24) {
                        $q->where('start_time', '<=', $startTime24)
                            ->where('end_time', '>=', $endTime24);
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

        // For manual bookings, payment is pending and status is always confirmed
        $booking = Book::create([
            'transaction_uuid' => $transaction_uuid,
            'date' => $validated['date'],
            'start_time' => $startTime24,
            'end_time' => $endTime24,
            'notes' => $validated['notes'] ?? null,
            'customer_name' => $validated['customer_name'],
            'customer_phone' => $validated['customer_phone'],
            'payment' => $validated['payment'],
            'court_id' => $validated['court_id'],
            'user_id' => null,
            'price' => $court->price,
            'payment_status' => 'Pending',
            'status' => 'Confirmed',
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Booking created manually by vendor.',
            'booking' => $booking->load('court')
        ], 201);
    }
}
