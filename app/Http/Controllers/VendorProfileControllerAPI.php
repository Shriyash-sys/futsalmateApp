<?php

namespace App\Http\Controllers;

use App\Models\Vendor;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use App\Http\Controllers\Controller;

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
			'profile_photo' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
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

		if (array_key_exists('profile_photo', $validated)) {
			$photo = $validated['profile_photo'];
			$path = $photo->store('vendor_profiles', 'public');
			$vendor->profile_photo_path = $path;
			$vendor->profile_photo_url = asset('storage/' . $path);
		}

		$vendor->save();

		return response()->json([
			'status' => 'success',
			'message' => 'Profile updated successfully',
			'vendor' => $vendor
		], 200);
	}
}
