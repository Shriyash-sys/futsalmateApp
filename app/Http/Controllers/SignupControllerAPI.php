<?php

namespace App\Http\Controllers;

use Exception;
use App\Models\User;
use App\Models\Vendor;
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

            // Do not log the user in until email is verified. Send verification email.
            try {
                $user->sendEmailVerificationNotification();
            } catch (Exception $e) {
                Log::error('Verification email failed to send: ' . $e->getMessage());
                return response()->json([
                    'status' => 'warning',
                    'message' => 'Registered, but verification email failed. Please try again later.'
                ], 200);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Registration successful! A verification email has been sent. Please verify your email before logging in.',
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

    // ----------------------------------------Vendor Signup----------------------------------------

    public function vendorSignup(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:vendors,email',
            'password' => 'required|string|min:8|confirmed',
            'phone' => 'nullable|string|max:15|unique:vendors,phone',
            'address' => 'required|string|max:500',
            'owner_name' => 'required|string|max:255',
            'terms' => 'accepted'
        ]);

        try {
            // create vendor
            $vendor = Vendor::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
                'phone' => $validated['phone'] ?? null,
                'address' => $validated['address'],
                'owner_name' => $validated['owner_name']
            ]);

            try {
                $vendor->sendEmailVerificationNotification();
            } catch (Exception $e) {
                Log::error('Verification email failed to send to vendor: ' . $e->getMessage());
                return response()->json([
                    'status' => 'warning',
                    'message' => 'Registered, but verification email failed. Please try again later.'
                ], 200);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Vendor registered successfully. A verification email has been sent.',
                'vendor' => $vendor
            ], 201);
        } catch (Exception $e) {
            Log::error('Vendor signup failed: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Vendor registration failed. Please try again.'
            ], 500);
        }
    }

    // (Note: only one vendorSignup implementation — vendors are separate entities and not represented in `users`)
}