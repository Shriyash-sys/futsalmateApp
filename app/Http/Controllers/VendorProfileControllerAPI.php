<?php

namespace App\Http\Controllers;

use App\Models\Vendor;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Storage;

class VendorProfileControllerAPI extends Controller
{
	// ----------------------------------------Show Vendor Profile----------------------------------------
	public function show(Request $request)
	{
		$vendor = $request->user();

		if (!$vendor || !($vendor instanceof Vendor)) {
			return response()->json([
				'status' => 'error',
				'message' => 'Vendor not authenticated or invalid model.'
			], 403);
		}

		return response()->json([
			'status' => 'success',
			'vendor' => $vendor
		], 200);
	}

	// ----------------------------------------Edit Vendor Profile----------------------------------------
	public function editProfile(Request $request)
	{
		$vendor = $request->user();

		if (!$vendor || !($vendor instanceof Vendor)) {
			return response()->json([
				'status' => 'error',
				'message' => 'Vendor not authenticated or invalid model.'
			], 403);
		}

		$validated = $request->validate([
			'name' => 'nullable|string|max:255',
			'email' => [
				'required',
				'email',
				Rule::unique('vendors', 'email')->ignore($vendor->id),
			],
			'phone' => 'nullable|string|max:20',
			'address' => 'nullable|string|max:255',
			'owner_name' => 'nullable|string|max:255',
		]);

		if (array_key_exists('name', $validated)) {
			$vendor->name = $validated['name'];
		}

		if (array_key_exists('email', $validated)) {
			$vendor->email = $validated['email'];
		}

		if (array_key_exists('phone', $validated)) {
			$vendor->phone = $validated['phone'];
		}

		if (array_key_exists('address', $validated)) {
			$vendor->address = $validated['address'];
		}

		if (array_key_exists('owner_name', $validated)) {
			$vendor->owner_name = $validated['owner_name'];
		}

		$vendor->save();

		return response()->json([
			'status' => 'success',
			'message' => 'Profile updated successfully',
			'vendor' => $vendor
		], 200);
	}

	public function addProfilePhoto(Request $request)
	{
		$request->validate([
			'profile_photo' => 'required|image|mimes:jpeg,png,jpg,gif|max:2048',
		]);

		$vendor = $request->user();
		if (!$vendor || !($vendor instanceof Vendor)) {
			return response()->json([
				'status' => 'error',
				'message' => 'Vendor not authenticated or invalid model.'
			], 403);
		}

		if ($request->hasFile('profile_photo')) {
			$file = $request->file('profile_photo');
			if (!$file || !$file->isValid()) {
				return response()->json([
					'status' => 'error',
					'message' => 'Uploaded file is invalid.'
				], 422);
			}

			if ($vendor->profile_photo_path) {
				Storage::disk('public')->delete($vendor->profile_photo_path);
			}

			$path = $file->storePublicly('vendor_profile_photos', 'public');
			$vendor->profile_photo_path = $path;
			$vendor->profile_photo_url = asset('storage/' . $path);
			$vendor->save();

			return response()->json([
				'status' => 'success',
				'message' => 'Profile photo updated.',
				'profile_photo_url' => $vendor->profile_photo_url,
				'vendor' => $vendor
			], 200);
		}

		return response()->json([
			'status' => 'error',
			'message' => 'No photo uploaded.'
		], 422);
	}

	public function deleteProfilePhoto(Request $request)
	{
		$vendor = $request->user();
		if (!$vendor || !($vendor instanceof Vendor)) {
			return response()->json([
				'status' => 'error',
				'message' => 'Vendor not authenticated or invalid model.'
			], 403);
		}

		if ($vendor->profile_photo_path) {
			Storage::disk('public')->delete($vendor->profile_photo_path);
			$vendor->profile_photo_path = null;
			$vendor->profile_photo_url = null;
			$vendor->save();
		}

		return response()->json([
			'status' => 'success',
			'message' => 'Profile photo removed.',
			'vendor' => $vendor
		], 200);
	}
}
