<?php

namespace App\Http\Controllers;

use Exception;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Auth\Events\Verified;
use Illuminate\Support\Facades\Schema;

class EmailVerificationController extends Controller
{
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

    // ---------------- Resend User verification ----------------
    public function resend(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'force' => 'nullable|boolean'
        ]);

        $force = $request->boolean('force', false);

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

        // Throttle resend attempts (persistent on user model)
        $limit = config('auth.otp_resend_limit', 5);
        $interval = config('auth.otp_resend_interval', 60);

        // Reset if expired
        try {
            if (!$user->otp_resend_expires_at || $user->otp_resend_expires_at->isPast()) {
                $user->otp_resend_count = 0;
                $user->otp_resend_expires_at = null;
                $user->save();
            }
        } catch (Exception $e) {
            // ignore model timing issues
        }

        if ($user->otp_resend_count >= $limit) {
            return response()->json([
                'status' => 'error',
                'message' => 'Too many resend attempts. Try again later.'
            ], 429);
        }

        // If OTP still valid and not forced, inform client
        if (!$force && $user->email_otp && $user->email_otp_expires_at && $user->email_otp_expires_at->isFuture()) {
            return response()->json([
                'status' => 'success',
                'message' => 'OTP already sent and still valid.',
                'expires_in' => $user->email_otp_expires_at->diffInSeconds(now())
            ], 200);
        }

        try {
            $user->sendEmailVerificationNotification();

            // increment persistent resend counter
            $user->otp_resend_count = ($user->otp_resend_count ?? 0) + 1;
            $user->otp_resend_expires_at = now()->addMinutes($interval);
            $user->save();
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
                if (Schema::hasColumn('users', 'vendor_id')) {
                    $user = User::where('vendor_id', $vendor->id)->first();
                    if ($user && !$user->hasVerifiedEmail()) {
                        $user->email_verified_at = now();
                        $user->save();
                    }
                }
            } catch (Exception $e) {
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

    // ---------------- Resend Vendor verification ----------------
    public function resendVendor(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'force' => 'nullable|boolean'
        ]);

        $force = $request->boolean('force', false);

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

        // Throttle resend attempts (persistent on vendor model)
        $limit = config('auth.otp_resend_limit', 5);
        $interval = config('auth.otp_resend_interval', 60);

        try {
            if (!$vendor->otp_resend_expires_at || $vendor->otp_resend_expires_at->isPast()) {
                $vendor->otp_resend_count = 0;
                $vendor->otp_resend_expires_at = null;
                $vendor->save();
            }
        } catch (Exception $e) {
            // ignore
        }

        // check resend count
        if ($vendor->otp_resend_count >= $limit) {
            return response()->json([
                'status' => 'error',
                'message' => 'Too many resend attempts. Try again later.'
            ], 429);
        }

        // If OTP still valid and not forced, inform client
        if (!$force && $vendor->email_otp && $vendor->email_otp_expires_at && $vendor->email_otp_expires_at->isFuture()) {
            return response()->json([
                'status' => 'success',
                'message' => 'OTP already sent and still valid.',
                'expires_in' => $vendor->email_otp_expires_at->diffInSeconds(now())
            ], 200);
        }

        try {
            $vendor->sendEmailVerificationNotification();

            // increment persistent resend counter on vendor
            $vendor->otp_resend_count = ($vendor->otp_resend_count ?? 0) + 1;
            $vendor->otp_resend_expires_at = now()->addMinutes($interval);
            $vendor->save();
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

}
