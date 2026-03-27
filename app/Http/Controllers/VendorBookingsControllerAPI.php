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
     * Vendor cancels a confirmed booking (sets status to Cancelled).
     */
    public function vendorCancelBooking(Request $request, $id)
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

        if (!in_array($booking->status, ['Pending', 'Confirmed'])) {
            return response()->json([
                'status' => 'error',
                'message' => 'Cannot cancel booking with status: ' . $booking->status
            ], 400);
        }

        $booking->status = 'Cancelled';
        $booking->save();

        return response()->json([
            'status' => 'success',
            'message' => 'Booking cancelled.',
            'booking' => $booking
        ], 200);
    }

    /**
     * Vendor edits a booking (date, time, and optionally customer info for manual bookings).
     */
    public function vendorEditBooking(Request $request, $id)
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

        if (!in_array($booking->status, ['Pending', 'Confirmed'])) {
            return response()->json([
                'status' => 'error',
                'message' => 'Cannot edit booking with status: ' . $booking->status
            ], 400);
        }

        $rules = [
            'date' => 'required|date|after_or_equal:today',
            'start_time' => 'required|date_format:g A',
            'end_time' => 'required|date_format:g A',
        ];
        if ($booking->user_id === null) {
            $rules['customer_name'] = 'nullable|string|max:255';
            $rules['customer_phone'] = 'nullable|string|max:15';
        }
        $validated = $request->validate($rules);

        $court = $booking->court;

        try {
            $startTime24 = \Carbon\Carbon::createFromFormat('g A', $validated['start_time'])->format('H:00:00');
            $endTime24 = \Carbon\Carbon::createFromFormat('g A', $validated['end_time'])->format('H:00:00');
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid time format. Please use format like "5 AM".'
            ], 422);
        }

        $tz = config('app.timezone');
        $bookingStart = \Carbon\Carbon::parse($validated['date'] . ' ' . $startTime24, $tz);
        if ($bookingStart->isToday() && $bookingStart->isPast()) {
            return response()->json([
                'status' => 'error',
                'message' => 'For today, start time cannot be in the past.',
            ], 422);
        }

        if ($court->opening_time && $court->closing_time) {
            if ($startTime24 < $court->opening_time || $endTime24 > $court->closing_time) {
                return response()->json([
                    'status' => 'error',
                    'message' => "Booking time must be between court's operating hours ({$court->opening_time} - {$court->closing_time})."
                ], 422);
            }
        }

        if ($startTime24 >= $endTime24) {
            return response()->json([
                'status' => 'error',
                'message' => 'Start time must be before end time.'
            ], 422);
        }

        $conflictingBooking = Book::where('court_id', $booking->court_id)
            ->where('date', $validated['date'])
            ->where('id', '!=', $booking->id)
            ->where('status', '!=', 'Cancelled')
            ->where('status', '!=', 'Rejected')
            ->where(function ($query) use ($startTime24, $endTime24) {
                $query->where('start_time', '<', $endTime24)
                    ->where('end_time', '>', $startTime24);
            })
            ->exists();

        if ($conflictingBooking) {
            return response()->json([
                'status' => 'error',
                'message' => 'This time slot is already booked for the selected date.'
            ], 409);
        }

        $booking->date = $validated['date'];
        $booking->start_time = $startTime24;
        $booking->end_time = $endTime24;
        if ($booking->user_id === null) {
            if (!empty($validated['customer_name'] ?? null)) {
                $booking->customer_name = $validated['customer_name'];
            }
            if (!empty($validated['customer_phone'] ?? null)) {
                $booking->customer_phone = $validated['customer_phone'];
            }
        }
        $booking->save();

        return response()->json([
            'status' => 'success',
            'message' => 'Booking updated successfully.',
            'booking' => $booking->load('court')
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

        if (strcasecmp((string) ($booking->payment_status ?? ''), 'Paid') === 0) {
            return response()->json([
                'status' => 'error',
                'message' => 'Payment is already marked as Paid and cannot be changed.',
            ], 400);
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
