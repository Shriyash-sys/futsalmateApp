<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Vendor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Hash;

class LoginControllerAPI extends Controller
{
    // ---------------- User Login ----------------

    public function login(Request $request)
    {
        $validatedData = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string|min:8',
            'remember' => 'nullable|boolean'
        ]);

        Log::info('Trying login for email: ' . $validatedData['email']);

        $user = User::where('email', $validatedData['email'])->first();

        if (!$user || !Hash::check($validatedData['password'], $user->password)) {
            Log::warning('Login failed for email: ' . $validatedData['email']);
            return response()->json([
                'status' => 'error',
                'message' => 'The provided credentials do not match our records.',
                'errors' => [
                    'email' => ['The provided credentials do not match our records.']
                ]
            ], 401);
        }

        Log::info('Login successful for user ID: ' . $user->id);
        
        // Ensure email verified before allowing login
        if (!$user->hasVerifiedEmail()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Please verify your email address before logging in.',
                'actions' => [
                    'resend_verification' => route('verification.resend')
                ]
            ], 403);
        }

        $token = $user->createToken('auth-token')->plainTextToken;

        return response()->json([
            'status' => 'success',
            'message' => 'Login successful!',
            'user' => $user,
            'token' => $token
            // 'redirect' => route('userDashboard')
        ], 200);
    }

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
