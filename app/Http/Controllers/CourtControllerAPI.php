<?php

namespace App\Http\Controllers;

use Throwable;
use App\Models\Court;
use App\Models\Vendor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
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
     * Vendor: add a court
     */
    public function vendorAddCourt(Request $request)
    {
        $validated = $request->validate([
            'court_name' => 'required|string|max:255',
            'location' => 'required|string|max:255',
            'price' => 'required|string|max:255',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
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

        // Handle optional image upload
        $imageUrl = null;
        try {
            if ($request->hasFile('image')) {
                $imagePath = $request->file('image')->store('images', 'public');
                $imageUrl = Storage::url($imagePath);
                Log::info('vendorAddCourt: image stored', ['path' => $imagePath, 'url' => $imageUrl]);
            }

            Log::info('vendorAddCourt: validated payload', ['validated' => $validated]);

            $court = Court::create([
                'court_name' => $validated['court_name'],
                'location' => $validated['location'],
                'price' => $validated['price'],
                'image' => $imageUrl,
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
