<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\SignupControllerAPI;
use Illuminate\Foundation\Auth\EmailVerificationRequest;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/email/verify', [SignupControllerAPI::class, 'mailVerificationNotice'])
    ->middleware('auth')
    ->name('verification.notice');

Route::get('/email/verify/{id}/{hash}', function (EmailVerificationRequest $request) {
    $request->fulfill(); // ✅ This verifies the user
    return redirect()->route('userDashboard');
})->middleware(['auth', 'signed'])->name('verification.verify');

Route::post('/email/verification-notification', [SignupControllerAPI::class, 'resendVerificationEmail'])
    ->middleware(['auth', 'throttle:5,1'])
    ->name('verification.send');