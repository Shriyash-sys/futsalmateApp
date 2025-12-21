<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\LoginControllerAPI;
use App\Http\Controllers\SignupControllerAPI;
use App\Http\Controllers\VendorAuthController;
use App\Http\Controllers\EmailVerificationController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// User signup
Route::post('/signup', [SignupControllerAPI::class, 'signup']);

// User authentication
Route::post('/login', [LoginControllerAPI::class, 'login']);

// Email verification (URL-based) removed — use OTP endpoints (POST /api/email/verify/otp and /api/email/verify/resend-otp).
Route::post('/email/resend', [EmailVerificationController::class, 'resend'])->name('verification.resend');

// OTP based verification endpoints
Route::post('/email/verify/otp', [EmailVerificationController::class, 'verifyOtp'])->name('verification.verify.otp');
Route::post('/email/verify/resend-otp', [EmailVerificationController::class, 'resend'])->name('verification.resend.otp');

// Vendor signup
Route::post('/vendor/signup', [SignupControllerAPI::class, 'vendorSignup']);

// Vendor authentication
Route::post('/vendor/login', [LoginControllerAPI::class, 'vendorLogin']);

// Vendor verification (URL-based) removed — use OTP endpoints (POST /api/vendor/email/verify/otp and /api/vendor/email/verify/resend-otp).
Route::post('/vendor/email/resend', [EmailVerificationController::class, 'resendVendor'])->name('vendor.verification.resend');

// Vendor OTP endpoints
Route::post('/vendor/email/verify/otp', [EmailVerificationController::class, 'verifyVendorOtp'])->name('vendor.verification.verify.otp');
Route::post('/vendor/email/verify/resend-otp', [EmailVerificationController::class, 'resendVendor'])->name('vendor.verification.resend.otp');

