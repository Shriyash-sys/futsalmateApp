<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\LoginControllerAPI;
use App\Http\Controllers\SignupControllerAPI;
use App\Http\Controllers\EmailVerificationController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::post('/signup', [SignupControllerAPI::class, 'signup']);

// Vendor signup (mobile)
Route::post('/vendor/signup', [\App\Http\Controllers\SignupControllerAPI::class, 'vendorSignup']);
Route::post('/vendor-signup', [SignupControllerAPI::class, 'vendorSignup']);

// Vendor authentication
Route::post('/vendor/login', [\App\Http\Controllers\VendorAuthController::class, 'login']);

// Vendor verification routes
Route::get('/vendor/email/verify/{id}/{hash}', [EmailVerificationController::class, 'verifyVendor'])
    ->name('vendor.verification.verify')
    ->middleware('signed');

Route::post('/vendor/email/resend', [EmailVerificationController::class, 'resendVendor'])->name('vendor.verification.resend');

Route::post('/login', [LoginControllerAPI::class, 'login']);

// Email verification routes for API/mobile clients
Route::get('/email/verify/{id}/{hash}', [EmailVerificationController::class, 'verify'])
    ->name('verification.verify')
    ->middleware('signed');

Route::post('/email/resend', [EmailVerificationController::class, 'resend'])->name('verification.resend');

Route::get('/vendor/email/verify/{id}/{hash}', [EmailVerificationController::class, 'verifyVendor'])
    ->name('vendor.verification.verify')
    ->middleware('signed');

Route::post('/vendor/email/resend', [EmailVerificationController::class, 'resendVendor'])->name('vendor.verification.resend');

