<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use App\Models\Book;
use App\Models\Court;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Kreait\Firebase\Contract\Messaging;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;

class BookControllerAPI extends Controller
{
    /**
     * Send upcoming booking reminders (called by cron via HTTP).
     */
    public function sendUpcomingReminders(Request $request)
    {
        $key = $request->query('key');
        $expectedKey = config('app.reminder_key', env('REMINDER_CRON_KEY'));

        if (!$expectedKey || $key !== $expectedKey) {
            return response()->json(['status' => 'error', 'message' => 'Unauthorized'], 401);
        }

        /** @var Messaging $messaging */
        $messaging = app(Messaging::class);

        $now = Carbon::now();

        $this->sendRemindersForOffset($messaging, 30, 'reminder_30_sent', $now);
        $this->sendRemindersForOffset($messaging, 10, 'reminder_10_sent', $now);

        return response()->json(['status' => 'success'], 200);
    }

    protected function sendRemindersForOffset(Messaging $messaging, int $minutesBefore, string $flag, Carbon $now): void
    {
        $books = Book::with(['user', 'court'])
            ->where('payment_status', 'Paid')
            ->where('status', 'Confirmed')
            ->where($flag, false)
            ->get();

        foreach ($books as $booking) {
            $start = Carbon::parse($booking->date . ' ' . $booking->start_time);
            if ($start->lessThanOrEqualTo($now)) {
                continue;
            }

            $minutesToStart = $now->diffInMinutes($start, false);

            if ($minutesToStart < $minutesBefore - 1 || $minutesToStart > $minutesBefore + 1) {
                continue;
            }

            $user = $booking->user;
            $court = $booking->court;
            $courtName = optional($court)->court_name ?? 'your match';
            $title = 'Upcoming Match Reminder';
            $body = "Your match at {$courtName} starts in {$minutesBefore} minutes.";

            // Send to player
            if ($user && $user->fcm_token) {
                $message = CloudMessage::new()
                    ->withNotification(Notification::create($title, $body));
                $messaging->send($message->withChangedTarget('token', $user->fcm_token));
            }

            // Optionally also notify vendor that their court has a match starting soon
            if ($court && $court->vendor && $court->vendor->fcm_token) {
                $vendorMessage = CloudMessage::new()
                    ->withNotification(Notification::create(
                        'Upcoming Booking',
                        "A booking at {$courtName} starts in {$minutesBefore} minutes."
                    ));
                $messaging->send($vendorMessage->withChangedTarget('token', $court->vendor->fcm_token));
            }

            $booking->$flag = true;
            $booking->save();
        }
    }

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

        // Validate that start_time and end_time are within court's opening and closing hours
        $startTime = strtotime($validated['start_time']);
        $endTime = strtotime($validated['end_time']);
        $courtOpeningTime = strtotime($court->opening_time);
        $courtClosingTime = strtotime($court->closing_time);

        if ($startTime < $courtOpeningTime) {
            return response()->json([
                'status' => 'error',
                'message' => 'Start time must be after or equal to court opening time (' . $court->opening_time . ').'
            ], 400);
        }

        if ($endTime > $courtClosingTime) {
            return response()->json([
                'status' => 'error',
                'message' => 'End time must be before or equal to court closing time (' . $court->closing_time . ').'
            ], 400);
        }

        if ($startTime >= $endTime) {
            return response()->json([
                'status' => 'error',
                'message' => 'Start time must be before end time.'
            ], 400);
        }

        $bookingStart = Carbon::parse($validated['date'] . ' ' . $validated['start_time']);
        if ($bookingStart->isPast()) {
            return response()->json([
                'status' => 'error',
                'message' => 'You cannot book a past time slot.'
            ], 400);
        }

        $hasConflict = Book::where('court_id', $validated['court_id'])
            ->where('date', $validated['date'])
            ->whereNotIn('status', ['Cancelled', 'Rejected'])
            ->where(function ($query) use ($validated) {
                $query->where('start_time', '<', $validated['end_time'])
                    ->where('end_time', '>', $validated['start_time']);
            })
            ->exists();

        if ($hasConflict) {
            return response()->json([
                'status' => 'error',
                'message' => 'This time slot is already booked for the selected date.'
            ], 409);
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
        // IMPORTANT: use the exact same formatted values (2 decimal places)
        // that will be sent to eSewa, otherwise the signature will be invalid.
        $amount = number_format((float) $booking->price, 2, '.', '');
        $tax_amount = number_format(0, 2, '.', '');
        $total_amount = number_format($amount + $tax_amount, 2, '.', '');
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
        // eSewa sends the response parameters encoded in Base64 in the HTTP body.
        // Some integrations also send it as a "data" query / form parameter.
        // To be robust, we try all three in order.
        $encodedData = $request->query('data');
        if (!$encodedData) {
            $encodedData = $request->input('data');
        }
        if (!$encodedData) {
            $encodedData = $request->getContent();
        }

        if (!$encodedData) {
            return response()->json([
                'status' => 'error',
                'message' => 'No payment data received.'
            ], 400);
        }

        try {
            // Base64 decode
            $jsonData = base64_decode($encodedData, true);
            if ($jsonData === false) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Unable to decode payment data.'
                ], 400);
            }

            // JSON decode to associative array
            $data = json_decode($jsonData, true);
        } catch (\Throwable $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to parse payment data.',
            ], 400);
        }

        if (!is_array($data)) {
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

        if (($data['status'] ?? null) === "COMPLETE") {
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
     * Edit a booking for user
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
        if ((int) $booking->user_id !== (int) $user->id) {
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

        $startDateTime = Carbon::parse($booking->date . ' ' . $booking->start_time);
        $cutoffTime = $startDateTime->copy()->subHours(2);

        if (Carbon::now()->greaterThanOrEqualTo($cutoffTime)) {
            return response()->json([
                'status' => 'error',
                'message' => 'You cannot edit within 2 hours of the match start time.'
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
     * Get booking confirmation details for user
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

        return response()->json([
            'status' => 'success',
            'booking' => $booking
        ], 200);
    }

    /**
     * Cancel a booking for user
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

        $startDateTime = Carbon::parse($booking->date . ' ' . $booking->start_time);
        $cutoffTime = $startDateTime->copy()->subHours(2);

        if (Carbon::now()->greaterThanOrEqualTo($cutoffTime)) {
            return response()->json([
                'status' => 'error',
                'message' => 'You cannot cancel within 2 hours of the match start time.'
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
     * View booking details for user
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
        if ((int) $booking->user_id !== (int) $user->id) {
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
     * Get upcoming bookings for authenticated user
     */
    public function upcomingBookings(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthenticated.'
            ], 401);
        }

        $now = Carbon::now();

        $bookings = Book::with('court')
            ->whereNotNull('user_id')
            ->where('user_id', $user->id)
            ->whereNotIn('status', ['Cancelled', 'Rejected'])
            ->where(function ($query) use ($now) {
                $query->where('date', '>', $now->toDateString())
                    ->orWhere(function ($q) use ($now) {
                        $q->where('date', $now->toDateString())
                            ->where('end_time', '>', $now->format('H:i:s'));
                    });
            })
            ->orderBy('date')
            ->orderBy('start_time')
            ->get();

        return response()->json([
            'status' => 'success',
            'upcoming_bookings' => $bookings
        ], 200);
    }

    /**
     * Get past bookings for authenticated user
     */
    public function pastBookings(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthenticated.'
            ], 401);
        }

        $now = Carbon::now();

        $bookings = Book::with('court')
            ->whereNotNull('user_id')
            ->where('user_id', $user->id)
            ->where(function ($query) use ($now) {
                $query->where('date', '<', $now->toDateString())
                    ->orWhere(function ($q) use ($now) {
                        $q->where('date', $now->toDateString())
                            ->where('end_time', '<=', $now->format('H:i:s'));
                    });
            })
            ->orderBy('date', 'desc')
            ->orderBy('start_time', 'desc')
            ->get();

        return response()->json([
            'status' => 'success',
            'past_bookings' => $bookings
        ], 200);
    }

    /**
     * View booking details by booking ID
     */
    public function viewBookingById(Request $request, $id)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthenticated.'
            ], 401);
        }

        $booking = Book::with('court')
            ->whereNotNull('user_id')
            ->where('user_id', $user->id)
            ->where('id', $id)
            ->first();

        if (!$booking) {
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
}
