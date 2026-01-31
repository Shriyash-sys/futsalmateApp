<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Vendor;
use Illuminate\Http\Request;
use App\Notifications\UserEmailOtp;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

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

    // ---------------- User Forgot Password (Send OTP) ----------------
    public function forgotPassword(Request $request)
    {
        $validatedData = $request->validate([
            'email' => 'required|email|exists:users,email'
        ]);

        Log::info('Password reset requested for email: ' . $validatedData['email']);

        $user = User::where('email', $validatedData['email'])->first();

        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => 'User not found.',
                'errors' => [
                    'email' => ['No account found with this email address.']
                ]
            ], 404);
        }

        // Generate 6-digit OTP
        $otp = random_int(100000, 999999);
        $expiresInMinutes = config('auth.otp_expire', 10);

        // Store OTP in database
        $user->email_otp = (string) $otp;
        $user->email_otp_expires_at = now()->addMinutes($expiresInMinutes);
        $user->save();

        // Send OTP via email
        $user->notify(new UserEmailOtp($otp, $expiresInMinutes));

        Log::info('Password reset OTP sent to user ID: ' . $user->id);

        return response()->json([
            'status' => 'success',
            'message' => 'Password reset OTP has been sent to your email address.',
            'expires_in_minutes' => $expiresInMinutes
        ], 200);
    }

    // ---------------- User Reset Password with OTP ----------------
    public function resetPasswordWithOtp(Request $request)
    {
        $validatedData = $request->validate([
            'email' => 'required|email|exists:users,email',
            'otp' => 'required|string|size:6',
            'password' => 'required|string|min:8|confirmed'
        ]);

        Log::info('Password reset verification for email: ' . $validatedData['email']);

        $user = User::where('email', $validatedData['email'])->first();

        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => 'User not found.',
                'errors' => [
                    'email' => ['No account found with this email address.']
                ]
            ], 404);
        }

        // Check if OTP exists
        if (!$user->email_otp || !$user->email_otp_expires_at) {
            return response()->json([
                'status' => 'error',
                'message' => 'No OTP found. Please request a new password reset.',
                'errors' => [
                    'otp' => ['No OTP found. Please request a new password reset.']
                ]
            ], 400);
        }

        // Check if OTP matches
        if ($user->email_otp !== $validatedData['otp']) {
            Log::warning('Invalid OTP attempt for user ID: ' . $user->id);
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid OTP.',
                'errors' => [
                    'otp' => ['The OTP you entered is incorrect.']
                ]
            ], 400);
        }

        // Check if OTP is expired
        if ($user->email_otp_expires_at->isPast()) {
            Log::warning('Expired OTP attempt for user ID: ' . $user->id);
            return response()->json([
                'status' => 'error',
                'message' => 'OTP has expired. Please request a new password reset.',
                'errors' => [
                    'otp' => ['The OTP has expired.']
                ]
            ], 400);
        }

        // Update password and clear OTP
        $user->password = Hash::make($validatedData['password']);
        $user->email_otp = null;
        $user->email_otp_expires_at = null;
        $user->save();

        // Revoke all existing tokens for security
        $user->tokens()->delete();

        Log::info('Password reset successful for user ID: ' . $user->id);

        return response()->json([
            'status' => 'success',
            'message' => 'Password has been reset successfully. Please login with your new password.'
        ], 200);
    }
}
