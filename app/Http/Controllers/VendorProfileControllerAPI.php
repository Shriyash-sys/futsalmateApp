<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Vendor;
use Illuminate\Validation\Rule;

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

		$vendor->save();

		return response()->json([
			'status' => 'success',
			'message' => 'Profile updated successfully',
			'vendor' => $vendor
		], 200);
	}
}
