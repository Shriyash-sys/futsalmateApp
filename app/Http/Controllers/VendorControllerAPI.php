<?php

namespace App\Http\Controllers;

use Throwable;
use App\Models\Book;
use App\Models\User;
use App\Models\Court;
use App\Models\Vendor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Storage;

class VendorControllerAPI extends Controller
{
    // ----------------------------------------Vendor Dashboard----------------------------------------
    public function vendorDashboard(Request $request)
    {
        $vendor = $request->user();
        if (!$vendor) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthenticated.'
            ], 401);
        }

        // You can add more vendor-specific dashboard data here

        return response()->json([
            'status' => 'success',
            'data' => [
                'vendor' => $vendor,
                // Add other dashboard data as needed
            ]
        ], 200);
    }

    /**
     * Vendor: view all their courts
     */
    public function viewVendorCourts(Request $request)
    {
        $actor = $request->user();
        Log::info('viewVendorCourts called', ['actor' => $actor?->id]);

        if (!($actor instanceof Vendor)) {
            Log::warning('viewVendorCourts: unauthorized actor', ['actor' => $actor?->id]);
            return response()->json([
                'status' => 'error',
                'message' => 'This endpoint is only for vendors.'
            ], 403);
        }

        try {
            $courts = Court::where('vendor_id', $actor->id)
                ->orderBy('created_at', 'desc')
                ->get();

            Log::info('viewVendorCourts: courts retrieved', ['vendor_id' => $actor->id, 'count' => $courts->count()]);

            return response()->json([
                'status' => 'success',
                'data' => [
                    'courts' => $courts,
                    'total_courts' => $courts->count()
                ]
            ], 200);
        } catch (Throwable $e) {
            Log::error('viewVendorCourts failed', ['error' => $e->getMessage()]);
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve courts. See server logs for details.'
            ], 500);
        }
    }

    /**
     * Vendor: view all users who have booked from this vendor
     */
    public function viewVendorCustomers(Request $request)
    {
        $actor = $request->user();
        Log::info('viewVendorCustomers called', ['actor' => $actor?->id]);

        if (!($actor instanceof Vendor)) {
            Log::warning('viewVendorCustomers: unauthorized actor', ['actor' => $actor?->id]);
            return response()->json([
                'status' => 'error',
                'message' => 'This endpoint is only for vendors.'
            ], 403);
        }

        try {
            // Get all courts for this vendor
            $courtIds = Court::where('vendor_id', $actor->id)->pluck('id');

            // Get all unique users who have booked these courts
            $userIds = Book::whereIn('court_id', $courtIds)
                ->whereNotNull('user_id')
                ->distinct()
                ->pluck('user_id');

            // Get user details with their booking statistics
            $customers = User::whereIn('id', $userIds)->get()->map(function ($user) use ($courtIds) {
                $userBookings = Book::whereIn('court_id', $courtIds)
                    ->where('user_id', $user->id)
                    ->get();

                $totalBookings = $userBookings->count();
                $confirmedBookings = $userBookings->where('status', 'Confirmed')->count();
                $totalSpent = $userBookings->where('payment_status', 'Paid')->sum('price');
                $lastBookingDate = $userBookings->max('date');

                return [
                    'id' => $user->id,
                    'full_name' => $user->full_name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'profile_photo' => $user->profile_photo,
                    'statistics' => [
                        'total_bookings' => $totalBookings,
                        'confirmed_bookings' => $confirmedBookings,
                        'total_spent' => number_format($totalSpent, 2),
                        'last_booking_date' => $lastBookingDate
                    ]
                ];
            });

            Log::info('viewVendorCustomers: customers retrieved', ['vendor_id' => $actor->id, 'count' => $customers->count()]);

            return response()->json([
                'status' => 'success',
                'data' => [
                    'customers' => $customers,
                    'total_customers' => $customers->count()
                ]
            ], 200);
        } catch (Throwable $e) {
            Log::error('viewVendorCustomers failed', ['error' => $e->getMessage()]);
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve customers. See server logs for details.'
            ], 500);
        }
    }

    /**
     * Vendor: add a court
     */
    public function vendorAddCourt(Request $request)
    {
        $validated = $request->validate([
            'court_name' => 'required|string|max:255',
            'location' => 'required|string|max:255',
            'price' => 'required|string|max:255',
            'images' => 'nullable|array|max:8',
            'images.*' => 'image|mimes:jpeg,png,jpg,gif|max:2048',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'facilities' => 'nullable|array',
            'facilities.*' => 'string|max:100',
            'description' => 'nullable|string|max:1000',
            'status' => 'nullable|in:active,inactive',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180'
        ]);

        $actor = $request->user();
        Log::info('vendorAddCourt called', ['actor' => $actor?->id, 'actor_class' => $actor ? get_class($actor) : null]);

        if (!($actor instanceof Vendor)) {
            Log::warning('vendorAddCourt: unauthorized actor', ['actor' => $actor?->id]);
            return response()->json([
                'status' => 'error',
                'message' => 'This endpoint is only for vendors.'
            ], 403);
        }

        // Handle optional image uploads (up to 8)
        $imageUrls = [];
        try {
            if ($request->hasFile('images')) {
                foreach ($request->file('images') as $imageFile) {
                    $imagePath = $imageFile->store('images', 'public');
                    $imageUrls[] = Storage::url($imagePath);
                    Log::info('vendorAddCourt: image stored', ['path' => $imagePath, 'url' => end($imageUrls)]);
                }
            }

            if ($request->hasFile('image')) {
                $imagePath = $request->file('image')->store('images', 'public');
                $imageUrls[] = Storage::url($imagePath);
                Log::info('vendorAddCourt: image stored', ['path' => $imagePath, 'url' => end($imageUrls)]);
            }

            Log::info('vendorAddCourt: validated payload', ['validated' => $validated]);

            $court = Court::create([
                'court_name' => $validated['court_name'],
                'location' => $validated['location'],
                'price' => $validated['price'],
                'image' => count($imageUrls) > 0 ? json_encode($imageUrls) : null,
                'facilities' => $validated['facilities'] ?? null,
                'description' => $validated['description'] ?? null,
                'status' => $validated['status'] ?? 'inactive',
                'latitude' => isset($validated['latitude']) ? $validated['latitude'] : null,
                'longitude' => isset($validated['longitude']) ? $validated['longitude'] : null,
                'vendor_id' => $actor->id
            ]);

            Log::info('vendorAddCourt: court created', ['court_id' => $court->id]);

            return response()->json([
                'status' => 'success',
                'message' => 'Court added successfully.',
                'court' => $court
            ], 201);
        } catch (Throwable $e) {
            Log::error('vendorAddCourt failed', ['error' => $e->getMessage()]);
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to add court. See server logs for details.'
            ], 500);
        }
    }

    /**
     * Vendor: edit a court
     */
    public function vendorEditCourt(Request $request, $courtId)
    {
        $validated = $request->validate([
            'court_name' => 'nullable|string|max:255',
            'location' => 'nullable|string|max:255',
            'price' => 'nullable|string|max:255',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'description' => 'nullable|string|max:1000',
            'status' => 'nullable|in:active,inactive',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180'
        ]);

        $actor = $request->user();
        Log::info('vendorEditCourt called', ['actor' => $actor?->id, 'court_id' => $courtId]);

        if (!($actor instanceof Vendor)) {
            Log::warning('vendorEditCourt: unauthorized actor', ['actor' => $actor?->id]);
            return response()->json([
                'status' => 'error',
                'message' => 'This endpoint is only for vendors.'
            ], 403);
        }

        try {
            $court = Court::find($courtId);

            if (!$court) {
                Log::warning('vendorEditCourt: court not found', ['court_id' => $courtId]);
                return response()->json([
                    'status' => 'error',
                    'message' => 'Court not found.'
                ], 404);
            }

            if ($court->vendor_id !== $actor->id) {
                Log::warning('vendorEditCourt: unauthorized access', ['actor' => $actor->id, 'court_vendor' => $court->vendor_id]);
                return response()->json([
                    'status' => 'error',
                    'message' => 'You are not authorized to edit this court.'
                ], 403);
            }

            /** @var Court $court */
            // Handle optional image upload
            if ($request->hasFile('image')) {
                $imagePath = $request->file('image')->store('images', 'public');
                $imageUrl = Storage::url($imagePath);
                $validated['image'] = $imageUrl;
                Log::info('vendorEditCourt: image stored', ['path' => $imagePath, 'url' => $imageUrl]);
            }

            $court->update($validated);
            Log::info('vendorEditCourt: court updated', ['court_id' => $court->id]);

            return response()->json([
                'status' => 'success',
                'message' => 'Court updated successfully.',
                'court' => $court
            ], 200);
        } catch (Throwable $e) {
            Log::error('vendorEditCourt failed', ['error' => $e->getMessage()]);
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to edit court. See server logs for details.'
            ], 500);
        }
    }

    /**
     * Vendor: delete a court
     */
    public function vendorDeleteCourt(Request $request, $courtId)
    {
        $actor = $request->user();
        Log::info('vendorDeleteCourt called', ['actor' => $actor?->id, 'court_id' => $courtId]);

        if (!($actor instanceof Vendor)) {
            Log::warning('vendorDeleteCourt: unauthorized actor', ['actor' => $actor?->id]);
            return response()->json([
                'status' => 'error',
                'message' => 'This endpoint is only for vendors.'
            ], 403);
        }

        try {
            $court = Court::find($courtId);

            if (!$court) {
                Log::warning('vendorDeleteCourt: court not found', ['court_id' => $courtId]);
                return response()->json([
                    'status' => 'error',
                    'message' => 'Court not found.'
                ], 404);
            }

            if ($court->vendor_id !== $actor->id) {
                Log::warning('vendorDeleteCourt: unauthorized access', ['actor' => $actor->id, 'court_vendor' => $court->vendor_id]);
                return response()->json([
                    'status' => 'error',
                    'message' => 'You are not authorized to delete this court.'
                ], 403);
            }

            /** @var Court $court */
            $court->delete();
            Log::info('vendorDeleteCourt: court deleted', ['court_id' => $courtId]);

            return response()->json([
                'status' => 'success',
                'message' => 'Court deleted successfully.'
            ], 200);
        } catch (Throwable $e) {
            Log::error('vendorDeleteCourt failed', ['error' => $e->getMessage()]);
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete court. See server logs for details.'
            ], 500);
        }
    }
}
