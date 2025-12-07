<?php

namespace App\Http\Controllers;

use Exception;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Database\QueryException;
use Illuminate\Validation\ValidationException;

class SignupControllerAPI extends Controller
{
    // ----------------------------------------User Signup----------------------------------------

    public function signup(Request $request)
    {
        // Move validation outside try-catch to allow Laravel's validation exception handler
        // to return proper 422 responses with field-specific errors
        $validatedData = $request->validate([
            'full_name' => 'required|string|max:255',
            'email' => 'required|string|max:255|unique:users,email',
            'password' => 'required|string|min:8|confirmed',
            'phone' => 'nullable|string|max:15|unique:users,phone',
            'terms' => 'accepted'
        ]);

        try {
            $user = User::create([
                'full_name' => $validatedData['full_name'],
                'email' => $validatedData['email'],
                'password' => Hash::make($validatedData['password']),
                'phone' => $validatedData['phone'] ?? null,
            ]);

            Auth::login($user);

            return response()->json([
                'status' => 'success',
                'message' => 'Registration successful!',
                'user' => $user
            ], 201);
        } catch (ValidationException $e) {
            // Re-throw validation exceptions to let Laravel handle them properly (422 response)
            throw $e;
        } catch (QueryException $e) {
            Log::error('Database error during signup: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Registration failed. Please try again.',
                'errors' => [
                    'general' => ['An error occurred while creating your account.']
                ]
            ], 500);
        } catch (Exception $e) {
            Log::error('Error during signup: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'An unexpected error occurred. Please try again.',
                'errors' => [
                    'general' => ['Registration failed. Please try again later.']
                ]
            ], 500);
        }
    }

    // ----------------------------------------Mail Verification Notice----------------------------------------

    // public function mailVerificationNotice()
    // {
    //     return view('mail.userVerification');
    // }

    // // ----------------------------------------Resend Emaill  ----------------------------------------


    // public function resendVerificationEmail(Request $request)
    // {
    //     if ($request->user()->hasVerifiedEmail()) {
    //         return redirect()->route('dashboard');
    //     }

    //     $request->user()->sendEmailVerificationNotification();

    //     return back()->with('message', 'Verification link sent!');
    // }
}