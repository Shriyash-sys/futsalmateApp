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

    public function verify(Request $request, $id, $hash)
    {
        // Signed URL middleware will have already validated signature and expiry
        // Additional check: ensure hash matches the user's email
        $user = User::findOrFail($id);

        if (!hash_equals((string) $hash, sha1($user->email))) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid verification link.'
            ], 403);
        }

        if ($user->hasVerifiedEmail()) {
            return response()->json([
                'status' => 'success',
                'message' => 'Email already verified.'
            ], 200);
        }

        $user->email_verified_at = now();
        $user->save();

        event(new Verified($user));

        return response()->json([
            'status' => 'success',
            'message' => 'Email verified successfully. You may now log in.'
        ], 200);
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
                'message' => 'Failed to send verification email.'
            ], 500);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Verification email sent.'
        ], 200);
    }

    // ---------------- Vendor verification ----------------
    public function verifyVendor(Request $request, $id, $hash)
    {
        $vendor = Vendor::findOrFail($id);

        if (!hash_equals((string) $hash, sha1($vendor->email))) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid verification link.'
            ], 403);
        }

        if ($vendor->email_verified_at) {
            return response()->json([
                'status' => 'success',
                'message' => 'Vendor email already verified.'
            ], 200);
        }

        $vendor->email_verified_at = now();
        $vendor->save();

        event(new Verified($vendor));

        // If an associated user exists, mark it verified too so vendor can log in
        $user = User::where('vendor_id', $vendor->id)->first();
        if ($user && !$user->hasVerifiedEmail()) {
            $user->email_verified_at = now();
            $user->save();
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Vendor email verified successfully.'
        ], 200);
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
                'message' => 'Failed to send verification email.'
            ], 500);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Verification email sent.'
        ], 200);
    }

}
