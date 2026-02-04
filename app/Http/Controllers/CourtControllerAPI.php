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

            $courtDetail = [
                'id' => $court->id,
                'court_name' => $court->court_name,
                'location' => $court->location,
                'price' => $court->price,
                'description' => $court->description,
                'image' => $court->image,
                'opening_time' => $court->opening_time,
                'closing_time' => $court->closing_time,
                'latitude' => $court->latitude,
                'longitude' => $court->longitude,
                'vendor' => $court->vendor ? [
                    'id' => $court->vendor->id,
                    'name' => $court->vendor->name,
                    'phone' => $court->vendor->phone,
                    'email' => $court->vendor->email,
                ] : null,
                'today_bookings' => $todayBookings,
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
}
