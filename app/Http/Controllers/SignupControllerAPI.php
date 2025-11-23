<?php

namespace App\Http\Controllers;

use Exception;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class SignupControllerAPI extends Controller
{
    // ----------------------------------------User Signup----------------------------------------

    public function signup(Request $request)
    {
        $validatedData = $request->validate([
            'full_name' => 'required|string|max:255',
            'email' => 'required|string|max:255|unique:users,email',
            'password' => 'required|string|min:8|confirmed',
            'phone' => 'nullable|string|max:15|unique:users,phone',
            'terms' => 'accepted'
        ]);

        $user = User::create([
            'full_name' => $validatedData['full_name'],
            'email' => $validatedData['email'],
            'password' => Hash::make($validatedData['password']),
            'phone' => $validatedData['phone'] ?? null,
        ]);

        Auth::login($user);

        try {
            $user->sendEmailVerificationNotification();
        } catch (Exception $e) {
            Log::error('Verification email failed to send: ' .$e->getMessage());
            return response()->json([
                'status' => 'warning',
                'message' => 'Registered, but verification email failed. Please try again later.'
            ], 200);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Registration successful! Please check your email to verify your account.',
            'user' => $user
        ], 201);
    }

    // ----------------------------------------Mail Verification Notice----------------------------------------


    public function mailVerificationNotice()
    {
        return view('mail.userVerification');
    }

    // ----------------------------------------Resend Emaill  ----------------------------------------


    public function resendVerificationEmail(Request $request)
    {
        if ($request->user()->hasVerifiedEmail()) {
            return redirect()->route('dashboard');
        }

        $request->user()->sendEmailVerificationNotification();

        return back()->with('message', 'Verification link sent!');
    }
}
