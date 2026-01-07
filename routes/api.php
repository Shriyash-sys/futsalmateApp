<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\BookControllerAPI;
use App\Http\Controllers\CourtControllerAPI;
use App\Http\Controllers\LoginControllerAPI;
use App\Http\Controllers\SignupControllerAPI;
use App\Http\Controllers\VendorAuthController;
use App\Http\Controllers\UserProfileControllerAPI;
use App\Http\Controllers\EmailVerificationController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

//-----------------------------------For User APIs------------------------------------//
// User signup
Route::post('/signup', [SignupControllerAPI::class, 'signup']);

// User authentication
Route::post('/login', [LoginControllerAPI::class, 'login']);

// OTP based verification endpoints
Route::post('/email/verify/otp', [EmailVerificationController::class, 'verifyOtp'])->name('verification.verify.otp');
Route::post('/email/verify/resend-otp', [EmailVerificationController::class, 'resend'])->name('verification.resend.otp');

// User dashboard
Route::get('/user-dashboard', [UserProfileControllerAPI::class, 'userDashboard']);

//Show available courts for booking
Route::middleware('auth:sanctum')->get('/show-court', [CourtControllerAPI::class, 'showBookCourt']);

// Booking endpoints
Route::middleware('auth:sanctum')->post('/book', [BookControllerAPI::class, 'bookCourt']);
Route::get('/book/esewa/success', [BookControllerAPI::class, 'success']);
Route::get('/book/esewa/failure', [BookControllerAPI::class, 'failure']);

// Edit/Cancel Booking endpoints
Route::middleware('auth:sanctum')->match(['put','patch'], '/edit-booking/{id}', [BookControllerAPI::class, 'editBooking']);
Route::middleware('auth:sanctum')->delete('/cancel-booking/{id}', [BookControllerAPI::class, 'cancelBooking']);

// View Booking endpoints
Route::get('/book/booked-times', [BookControllerAPI::class, 'getBookedTimes']);
Route::get('/book/booking-confirmation/{id}', [BookControllerAPI::class, 'showBookingConfirmation']);
Route::get('/book/user-bookings/{id}', [BookControllerAPI::class, 'viewBooking']);

// User profile
Route::middleware('auth:sanctum')->get('/profile', [UserProfileControllerAPI::class, 'show']);
Route::middleware('auth:sanctum')->match(['put','patch'],'/profile', [UserProfileControllerAPI::class, 'editProfile']);

// Profile photo endpoints
Route::middleware('auth:sanctum')->post('/profile/photo', [UserProfileControllerAPI::class, 'addProfilePhoto']);
Route::middleware('auth:sanctum')->delete('/profile/photo', [UserProfileControllerAPI::class, 'deleteProfilePhoto']);

// Logout endpoints (require authentication)
Route::middleware('auth:sanctum')->post('/logout', [LoginControllerAPI::class, 'logout']);



//-------------------------------For Vendor APIs------------------------------------//
// Vendor authentication
Route::post('/vendor/login', [VendorAuthController::class, 'vendorLogin']);

// Vendor add-courts 
Route::middleware('auth:sanctum')->post('/vendor/add-courts', [CourtControllerAPI::class, 'vendorAddCourt']);

// Logout endpoints (require authentication)
Route::middleware('auth:sanctum')->post('/vendor/logout', [VendorAuthController::class, 'vendorLogout']);

// Vendor booking approval endpoints
Route::middleware('auth:sanctum')->post('/vendor/bookings/{id}/approve', [BookControllerAPI::class, 'vendorApproveBooking']);
Route::middleware('auth:sanctum')->post('/vendor/bookings/{id}/reject', [BookControllerAPI::class, 'vendorRejectBooking']);



