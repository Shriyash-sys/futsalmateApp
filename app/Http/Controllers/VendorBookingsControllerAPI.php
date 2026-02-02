<?php

namespace App\Http\Controllers;

use Throwable;
use App\Models\Book;
use App\Models\Court;
use App\Models\Vendor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;

class VendorBookingsControllerAPI extends Controller
{
    /**
     * Vendor: view bookings for vendor courts
     */
    public function vendorCourtBookings(Request $request)
    {
        $actor = $request->user();
        Log::info('vendorCourtBookings called', ['actor' => $actor?->id]);

        if (!($actor instanceof Vendor)) {
            Log::warning('vendorCourtBookings: unauthorized actor', ['actor' => $actor?->id]);
            return response()->json([
                'status' => 'error',
                'message' => 'This endpoint is only for vendors.'
            ], 403);
        }

        try {
            $courtIds = Court::where('vendor_id', $actor->id)->pluck('id');

            $bookings = Book::whereIn('court_id', $courtIds)
                ->with(['court', 'user'])
                ->orderBy('date', 'desc')
                ->orderBy('start_time', 'desc')
                ->get();

            return response()->json([
                'status' => 'success',
                'data' => [
                    'bookings' => $bookings,
                    'total_bookings' => $bookings->count()
                ]
            ], 200);
        } catch (Throwable $e) {
            Log::error('vendorCourtBookings failed', ['error' => $e->getMessage()]);
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve bookings. See server logs for details.'
            ], 500);
        }
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
