<?php

namespace App\Http\Controllers;

use Throwable;
use App\Models\Book;
use App\Models\Court;
use App\Models\Vendor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use Kreait\Firebase\Contract\Messaging;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;

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
     * Vendor approves a booking (used for Cash: Pending → Confirmed).
     * eSewa bookings are auto-confirmed on payment; only Pending Cash bookings need approval.
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

        $booking = Book::with(['court', 'user'])->find($id);
        if (!$booking) {
            return response()->json([
                'status' => 'error',
                'message' => 'Booking not found.'
            ], 404);
        }

        if (!$booking->court) {
            return response()->json([
                'status' => 'error',
                'message' => 'Court information not found for this booking.'
            ], 404);
        }

        if ((int) $booking->court->vendor_id !== (int) $vendor->id) {
            Log::warning('Vendor approval authorization failed', [
                'vendor_id' => $vendor->id,
                'court_vendor_id' => $booking->court->vendor_id,
                'booking_id' => $booking->id,
                'court_id' => $booking->court->id,
            ]);
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

        $this->notifyUserBookingStatus($booking, 'approved');

        return response()->json([
            'status' => 'success',
            'message' => 'Booking approved.',
            'booking' => $booking
        ], 200);
    }

    /**
     * Vendor rejects a booking (used for Cash: Pending → Rejected).
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

        $booking = Book::with(['court', 'user'])->find($id);
        if (!$booking) {
            return response()->json([
                'status' => 'error',
                'message' => 'Booking not found.'
            ], 404);
        }

        if (!$booking->court) {
            return response()->json([
                'status' => 'error',
                'message' => 'Court information not found for this booking.'
            ], 404);
        }

        if ((int) $booking->court->vendor_id !== (int) $vendor->id) {
            Log::warning('Vendor rejection authorization failed', [
                'vendor_id' => $vendor->id,
                'court_vendor_id' => $booking->court->vendor_id,
                'booking_id' => $booking->id,
                'court_id' => $booking->court->id,
            ]);
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

        $this->notifyUserBookingStatus($booking, 'rejected');

        return response()->json([
            'status' => 'success',
            'message' => 'Booking rejected.',
            'booking' => $booking
        ], 200);
    }

    /**
     * Vendor updates payment status of a booking (Pending, Paid, Unpaid).
     */
    public function updatePaymentStatus(Request $request, $id)
    {
        $vendor = $request->user();
        if (!$vendor) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthenticated.'
            ], 401);
        }

        $booking = Book::with(['court', 'user'])->find($id);
        if (!$booking) {
            return response()->json([
                'status' => 'error',
                'message' => 'Booking not found.'
            ], 404);
        }

        if (!$booking->court) {
            return response()->json([
                'status' => 'error',
                'message' => 'Court information not found for this booking.'
            ], 404);
        }

        if ((int) $booking->court->vendor_id !== (int) $vendor->id) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized. This booking is not for your court.'
            ], 403);
        }

        $validated = $request->validate([
            'payment_status' => 'required|string|in:Pending,Paid,Unpaid',
        ]);

        $booking->payment_status = $validated['payment_status'];
        $booking->save();

        return response()->json([
            'status' => 'success',
            'message' => 'Payment status updated.',
            'booking' => $booking
        ], 200);
    }

    /**
     * Send FCM notification to the user about their booking status (approved or rejected).
     */
    protected function notifyUserBookingStatus(Book $booking, string $status): void
    {
        $user = $booking->user;
        if (!$user || !$user->fcm_token) {
            return;
        }

        $courtName = $booking->court ? $booking->court->court_name : 'the court';
        $date = $booking->date;
        $time = $booking->start_time;

        if ($status === 'approved') {
            $title = 'Booking Confirmed';
            $body = "Your booking at {$courtName} on {$date} at {$time} has been confirmed.";
        } else {
            $title = 'Booking Rejected';
            $body = "Your booking at {$courtName} on {$date} at {$time} has been rejected by the venue.";
        }

        try {
            /** @var Messaging $messaging */
            $messaging = app(Messaging::class);
            $message = CloudMessage::new()
                ->withNotification(Notification::create($title, $body));
            $messaging->send($message->withChangedTarget('token', $user->fcm_token));
        } catch (Throwable $e) {
            Log::warning('Failed to send booking status notification to user', [
                'booking_id' => $booking->id,
                'user_id' => $user->id,
                'status' => $status,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
