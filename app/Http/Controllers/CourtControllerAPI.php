<?php

namespace App\Http\Controllers;

use Throwable;
use App\Models\Book;
use App\Models\Court;
use App\Models\Vendor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Storage;

class CourtControllerAPI extends Controller
{
    /**
     * Show all active courts for booking
     */
    public function showBookCourt()
    {
        $courts = Court::where('status', 'active')->get();
        return response()->json([
            'status' => 'success',
            'courts' => $courts
        ], 200);
    }

    /**
     * Show individual active court details for booking
     */
    public function showCourtDetail($courtId)
    {
        try {
            $court = Court::where('id', $courtId)
                ->whereIn('status', ['active', 'Active'])
                ->with('vendor')
                ->first();

            if (!$court) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Court not found or is inactive'
                ], 404);
            }

            // Get today's bookings to show availability
            $todayBookings = Book::where('court_id', $court->id)
                ->where('date', now()->toDateString())
                ->where('status', 'Confirmed')
                ->orderBy('start_time', 'asc')
                ->get(['start_time', 'end_time', 'customer_name']);

            // Calculate available time slots (assuming 12-hour operation: 8 AM to 8 PM)
            $availableSlots = [];
            try {
                $availableSlots = $this->getAvailableSlots($todayBookings ?? []);
            } catch (Throwable $e) {
                Log::warning('showCourtDetail: slot calculation failed', [
                    'court_id' => $court->id,
                    'error' => $e->getMessage()
                ]);
            }

            $courtDetail = [
                'id' => $court->id,
                'court_name' => $court->court_name,
                'location' => $court->location,
                'price' => $court->price,
                'description' => $court->description,
                'image' => $court->image,
                'latitude' => $court->latitude,
                'longitude' => $court->longitude,
                'vendor' => $court->vendor ? [
                    'id' => $court->vendor->id,
                    'name' => $court->vendor->name,
                    'phone' => $court->vendor->phone,
                    'email' => $court->vendor->email,
                ] : null,
                'today_bookings' => $todayBookings,
                'available_slots' => $availableSlots,
                'total_slots' => 12,
                'booked_slots' => $todayBookings ? count($todayBookings) : 0,
                'available_slots_count' => $todayBookings ? 12 - count($todayBookings) : 12,
            ];

            return response()->json([
                'status' => 'success',
                'data' => $courtDetail
            ], 200);
        } catch (Throwable $e) {
            Log::error('Error fetching court detail', [
                'court_id' => $courtId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            $response = [
                'status' => 'error',
                'message' => 'Failed to fetch court details'
            ];

            if (config('app.debug')) {
                $response['debug'] = [
                    'error' => $e->getMessage(),
                    'exception' => get_class($e),
                ];
            }

            return response()->json($response, 500);
        }
    }

    /**
     * Helper function to calculate available time slots
     */
    private function getAvailableSlots($bookings)
    {
        // Operating hours: 8 AM to 8 PM (12 slots of 1 hour each)
        $slots = [];
        for ($hour = 8; $hour < 20; $hour++) {
            $slotStart = str_pad($hour, 2, '0', 0) . ':00:00';
            $slotEnd = str_pad($hour + 1, 2, '0', 0) . ':00:00';

            $isBooked = false;
            foreach ($bookings as $booking) {
                $startTime = $booking->start_time instanceof \DateTimeInterface
                    ? $booking->start_time->format('H:i:s')
                    : (string) $booking->start_time;
                $endTime = $booking->end_time instanceof \DateTimeInterface
                    ? $booking->end_time->format('H:i:s')
                    : (string) $booking->end_time;

                if ($startTime <= $slotStart && $slotEnd <= $endTime) {
                    $isBooked = true;
                    break;
                }
            }

            $slots[] = [
                'start_time' => $slotStart,
                'end_time' => $slotEnd,
                'is_available' => !$isBooked,
            ];
        }
        return $slots;
    }
}
