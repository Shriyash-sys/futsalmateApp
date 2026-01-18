<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Vendor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\PersonalAccessToken;

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
                    'resend_verification' => route('verification.resend.otp')
                ]
            ], 403);
        }

        $token = $user->createToken('auth-token')->plainTextToken;

        return response()->json([
            'status' => 'success',
            'message' => 'Login successful!',
            'user' => $user,
            'token' => $token
        ], 200);
    }

    // ---------------- User Logout ----------------
    public function logout(Request $request)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthenticated.'
            ], 401);
        }

        if (!($user instanceof User)) {
            return response()->json([
                'status' => 'error',
                'message' => 'This endpoint is only for users.'
            ], 403);
        }

        // Attempt to revoke by current access token
        if ($user->currentAccessToken()) {
            $user->tokens()->where('id', $user->currentAccessToken()->id)->delete();
        }
            
        // As a fallback remove all tokens for this user
        // $user->tokens()->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Logged out successfully.'
        ], 200);
    }
}
