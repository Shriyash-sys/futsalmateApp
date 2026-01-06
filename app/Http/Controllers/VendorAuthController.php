<?php

namespace App\Http\Controllers;

use Exception;
use App\Models\Vendor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Hash;

class VendorAuthController extends Controller
{
    // ----------------------------------------Vendor Signup - DISABLED----------------------------------------
    // Vendor signup is disabled. Vendors can only be created by admin through the admin panel.
    // Use the Filament VendorResource to create vendor accounts.

    /*
    public function vendorSignup(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:vendors,email',
            'password' => 'required|string|min:8|confirmed',
            'phone' => 'nullable|string|max:15|unique:vendors,phone',
            'address' => 'required|string|max:500',
            'owner_name' => 'required|string|max:255'
        ]);

        try {
            // create vendor
            $vendor = Vendor::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
                'phone' => $validated['phone'] ?? null,
                'address' => $validated['address'],
                'owner_name' => $validated['owner_name']
            ]);

            try {
                $vendor->sendEmailVerificationNotification();
            } catch (Exception $e) {
                Log::error('Verification email failed to send to vendor: ' . $e->getMessage());
                return response()->json([
                    'status' => 'warning',
                    'message' => 'Registered, but verification email failed. Please try again later.'
                ], 200);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Vendor registered successfully. A verification code (OTP) has been sent to your email.',
                'vendor' => $vendor
            ], 201);
        } catch (Exception $e) {
            Log::error('Vendor signup failed: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Vendor registration failed. Please try again.'
            ], 500);
        }
    }
    */

    // ---------------- Vendor Login ----------------
    public function vendorLogin(Request $request)
    {
        $validated = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string|min:8'
        ]);

        $vendor = Vendor::where('email', $validated['email'])->first();

        if (!$vendor || !Hash::check($validated['password'], $vendor->password)) {
            return response()->json([
                'status' => 'error',
                'message' => 'The provided credentials do not match our records.'
            ], 401);
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
