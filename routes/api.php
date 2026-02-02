<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\BookControllerAPI;
use App\Http\Controllers\CourtControllerAPI;
use App\Http\Controllers\LoginControllerAPI;
use App\Http\Controllers\SignupControllerAPI;
use App\Http\Controllers\VendorControllerAPI;
use App\Http\Controllers\VendorAuthController;
use App\Http\Controllers\CommunityControllerAPI;
use App\Http\Controllers\UserProfileControllerAPI;
use App\Http\Controllers\ManualBookingControllerAPI;
use App\Http\Controllers\EmailVerificationController;
use App\Http\Controllers\VendorBookingsControllerAPI;

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
Route::middleware('auth:sanctum')->get('/court-detail/{courtId}', [CourtControllerAPI::class, 'showCourtDetail']);


// Booking endpoints
Route::middleware('auth:sanctum')->controller(BookControllerAPI::class)->group(function () {
    Route::post('/book', 'bookCourt');
    Route::get('/book/esewa/success', 'success');
    Route::get('/book/esewa/failure', 'failure');
    Route::get('/book/booked-times', 'getBookedTimes');
    Route::match(['put','patch'], '/edit-booking/{id}', 'editBooking');
    Route::delete('/cancel-booking/{id}', 'cancelBooking');
    Route::get('/book/booking-confirmation/{id}', 'showBookingConfirmation');
    Route::get('/book/user-bookings/{id}', 'viewBooking');
});

// Community (team) registration
Route::middleware('auth:sanctum')->controller(CommunityControllerAPI::class)->group(function () {
    Route::post('/community/register-team', 'registerTeam');    
    Route::get('/community/user-communities', 'showTeams');
    Route::delete('/community/delete-team/{id}', 'deleteTeam');
    Route::match(['put', 'patch'], '/community/edit-team/{id}', 'editTeam');
});

// User profile
Route::middleware('auth:sanctum')->controller(UserProfileControllerAPI::class)->group(function () {
    Route::get('/profile', 'show');
    Route::match(['put','patch'],'/profile', 'editProfile');
    Route::post('/profile/photo', 'addProfilePhoto');
    Route::delete('/profile/photo', 'deleteProfilePhoto');
});

// Logout endpoints (require authentication)
Route::middleware('auth:sanctum')->post('/logout', [LoginControllerAPI::class, 'logout']);



//-------------------------------For Vendor APIs------------------------------------//
// Vendor authentication
Route::post('/vendor/login', [VendorAuthController::class, 'vendorLogin']);

// Vendor add-view-edit-delete courts 
Route::middleware('auth:sanctum')->controller(VendorControllerAPI::class)->group(function () {
        Route::get('/vendor/view-courts', 'viewVendorCourts');
        Route::post('/vendor/add-courts', 'vendorAddCourt');
        Route::match(['put','patch'],'/vendor/edit-courts/{id}', 'vendorEditCourt');
        Route::delete('/vendor/delete-courts/{id}', 'vendorDeleteCourt');
});

// Vendor Manual Booking
Route::middleware('auth:sanctum')->post('/vendor/manual-booking', [ManualBookingControllerAPI::class, 'manualBookCourt']);

// Vendor booking approval endpoints
Route::middleware('auth:sanctum')->controller(VendorBookingsControllerAPI::class)->group(function () {
    Route::post('/vendor/bookings/{id}/approve', 'vendorApproveBooking');
    Route::post('/vendor/bookings/{id}/reject', 'vendorRejectBooking');
});

Route::middleware('auth:sanctum')->controller(VendorControllerAPI::class)->group(function () {
    Route::get('/vendor/vendor-dashboard', 'vendorDashboard');
    Route::get('/vendor/view-customers', 'viewVendorCustomers');
});

// Logout endpoints (require authentication)
Route::middleware('auth:sanctum')->post('/vendor/logout', [VendorAuthController::class, 'vendorLogout']);


