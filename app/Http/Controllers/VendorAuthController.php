<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use App\Models\Vendor;

class VendorAuthController extends Controller
{
    public function login(Request $request)
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

        if (!$vendor->email_verified_at) {
            return response()->json([
                'status' => 'error',
                'message' => 'Please verify your vendor email before logging in.',
                'actions' => [
                    'resend_verification' => route('vendor.verification.resend')
                ]
            ], 403);
        }

        $token = $vendor->createToken('vendor-token')->plainTextToken;

        return response()->json([
            'status' => 'success',
            'message' => 'Login successful',
            'vendor' => $vendor,
            'token' => $token
        ], 200);
    }
}
