<?php

namespace App\Http\Controllers;

use Exception;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Auth\Events\Verified;

class EmailVerificationController extends Controller
{
    // ---------------- User verification ----------------

    public function verify(Request $request, $id = null, $hash = null)
    {
        return response()->json([
            'status' => 'error',
            'message' => 'URL-based verification has been removed. Use OTP endpoint: /api/email/verify/otp'
        ], 410);
    }

    // ---------------- Resend User verification ----------------

    public function resend(Request $request)
    {
        $request->validate([
            'email' => 'required|email'
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => 'User not found.'
            ], 404);
        }

        if ($user->hasVerifiedEmail()) {
            return response()->json([
                'status' => 'success',
                'message' => 'Email already verified.'
            ], 200);
        }

        try {
            $user->sendEmailVerificationNotification();
        } catch (Exception $e) {
            Log::error('Verification resend failed: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to send verification code.'
            ], 500);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Verification code sent.'
        ], 200);
    }

    // ---------------- User OTP verification ----------------
    public function verifyOtp(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'otp' => 'required|string'
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => 'User not found.'
            ], 404);
        }

        if ($user->hasVerifiedEmail()) {
            return response()->json([
                'status' => 'success',
                'message' => 'Email already verified.'
            ], 200);
        }

        if ($user->verifyEmailOtp($request->otp)) {
            event(new Verified($user));
            return response()->json([
                'status' => 'success',
                'message' => 'Email verified successfully. You may now log in.'
            ], 200);
        }

        return response()->json([
            'status' => 'error',
            'message' => 'Invalid or expired OTP.'
        ], 403);
    }

    // ---------------- Vendor verification ----------------
    public function verifyVendor(Request $request, $id = null, $hash = null)
    {
        return response()->json([
            'status' => 'error',
            'message' => 'URL-based vendor verification has been removed. Use OTP endpoint: /api/vendor/email/verify/otp'
        ], 410);
    }

    // ---------------- Resend Vendor verification ----------------

    public function resendVendor(Request $request)
    {
        $request->validate([
            'email' => 'required|email'
        ]);

        $vendor = Vendor::where('email', $request->email)->first();

        if (!$vendor) {
            return response()->json([
                'status' => 'error',
                'message' => 'Vendor not found.'
            ], 404);
        }

        if ($vendor->email_verified_at) {
            return response()->json([
                'status' => 'success',
                'message' => 'Email already verified.'
            ], 200);
        }

        try {
            $vendor->sendEmailVerificationNotification();
        } catch (Exception $e) {
            Log::error('Vendor verification resend failed: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to send verification code.'
            ], 500);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Verification code sent.'
        ], 200);
    }

    // ---------------- Vendor OTP verification ----------------
    public function verifyVendorOtp(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'otp' => 'required|string'
        ]);

        $vendor = Vendor::where('email', $request->email)->first();

        if (!$vendor) {
            return response()->json([
                'status' => 'error',
                'message' => 'Vendor not found.'
            ], 404);
        }

        if ($vendor->email_verified_at) {
            return response()->json([
                'status' => 'success',
                'message' => 'Vendor email already verified.'
            ], 200);
        }

        if ($vendor->verifyEmailOtp($request->otp)) {
            event(new Verified($vendor));

            // If an associated user exists (via users.vendor_id), mark it verified too so vendor can log in
            try {
                if (\Illuminate\Support\Facades\Schema::hasColumn('users', 'vendor_id')) {
                    $user = \App\Models\User::where('vendor_id', $vendor->id)->first();
                    if ($user && !$user->hasVerifiedEmail()) {
                        $user->email_verified_at = now();
                        $user->save();
                    }
                }
            } catch (\Exception $e) {
                // If checking schema fails for any reason, skip the associated user update
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Vendor email verified successfully.'
            ], 200);
        }

        return response()->json([
            'status' => 'error',
            'message' => 'Invalid or expired OTP.'
        ], 403);
    }

}
