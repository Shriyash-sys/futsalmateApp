<?php

namespace App\Http\Controllers;

use Exception;
use App\Models\Vendor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Hash;

class VendorAuthController extends Controller
{
    // ---------------- Vendor Login ----------------
    public function vendorLogin(Request $request)
    {
        $validated = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string|min:8',
            'fcm_token' => 'nullable|string',
        ]);

        $vendor = Vendor::where('email', $validated['email'])->first();

        if (!$vendor || !Hash::check($validated['password'], $vendor->password)) {
            return response()->json([
                'status' => 'error',
                'message' => 'The provided credentials do not match our records.'
            ], 401);
        }

        // Update FCM token if provided
        if (!empty($validated['fcm_token'] ?? null)) {
            $vendor->fcm_token = $validated['fcm_token'];
            $vendor->save();
        }

        $token = $vendor->createToken('vendor-token')->plainTextToken;

        return response()->json([
            'status' => 'success',
            'message' => 'Login successful',
            'vendor' => $vendor,
            'token' => $token
        ], 200);
    }

        // ---------------- Vendor Logout ----------------
    public function vendorLogout(Request $request)
    {
        $actor = $request->user();

        if (!$actor) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthenticated.'
            ], 401);
        }

        if (!($actor instanceof Vendor)) {
            return response()->json([
                'status' => 'error',
                'message' => 'This endpoint is only for vendors.'
            ], 403);
        }

        // Revoke current token if present
        if ($actor->currentAccessToken()) {
            $actor->tokens()->where('id', $actor->currentAccessToken()->id)->delete();
        }

        // Fallback: remove all tokens
        // $actor->tokens()->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Vendor logged out successfully.'
        ], 200);
    }
}
