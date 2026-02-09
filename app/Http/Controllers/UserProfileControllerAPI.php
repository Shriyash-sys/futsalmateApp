<?php

namespace App\Http\Controllers;

use App\Models\Book;
use App\Models\User;
use App\Models\DeviceToken;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use App\Notifications\UserEmailOtp;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class UserProfileControllerAPI extends Controller
{
    // ----------------------------------------User Dashboard----------------------------------------
    public function userDashboard(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthenticated.'
            ], 401);
        }

        $totalBookings = Book::where('user_id', $user->id)->count();

        $today = now()->format('Y-m-d');
        $currentTime = now()->format('H:i:s');

        $upcomingBookings = Book::where('user_id', $user->id)
            ->with('court')
            ->where(function ($query) use ($today, $currentTime) {
                // Bookings in the future (date > today)
                $query->where('date', '>', $today)
                    // OR bookings today with start_time >= current time
                    ->orWhere(function ($q) use ($today, $currentTime) {
                        $q->where('date', '=', $today)
                            ->where('start_time', '>=', $currentTime);
                    });
            })
            ->whereNotIn('status', ['Cancelled', 'Rejected'])
            ->orderBy('date', 'asc')
            ->orderBy('start_time', 'asc')
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => [
                'user' => $user,
                'total_bookings' => $totalBookings,
                'upcoming_bookings' => $upcomingBookings,
            ]
        ], 200);
    }

    // ----------------------------------------Show Profile----------------------------------------
    public function show(Request $request)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthenticated.'
            ], 401);
        }

        $profile = null;
        if (method_exists($user, 'profile')) {
            $profile = $user->profile;
        }

        if ($profile) {
            return response()->json([
                'status' => 'success',
                'profile' => $profile
            ], 200);
        }

        return response()->json([
            'status' => 'success',
            'user' => $user
        ], 200);
    }

    // ----------------------------------------Edit Profile----------------------------------------
    public function editProfile(Request $request)
    {
        $user = $request->user();

        if (!$user || !($user instanceof User)) {
            return response()->json([
                'status' => 'error',
                'message' => 'User not authenticated or invalid model.'
            ], 403);
        }

        $validated = $request->validate([
            'full_name' => 'nullable|string|max:255',
            'email' => [
                'required',
                'email',
                Rule::unique('users', 'email')->ignore($user->id),
            ],
            'phone' => 'nullable|string|max:15',
        ]);

        // Only update fields that are present
        if (array_key_exists('full_name', $validated)) {
            $user->full_name = $validated['full_name'];
        }

        if (array_key_exists('email', $validated)) {
            $user->email = $validated['email'];
        }

        if (array_key_exists('phone', $validated)) {
            $user->phone = $validated['phone'];
        }

        $user->save();

        return response()->json([
            'status' => 'success',
            'message' => 'Profile updated successfully',
            'user' => $user
        ], 200);
    }

    // ----------------------------------------Add Profile Photo----------------------------------------
    public function addProfilePhoto(Request $request)
    {
        $validated = $request->validate([
            'profile_photo' => 'required|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        $user = $request->user();
        if (!$user || !($user instanceof User)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthenticated.'
            ], 401);
        }

        if ($request->hasFile('profile_photo')) {
            $file = $request->file('profile_photo');
            if (!$file || !$file->isValid()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Uploaded file is invalid.'
                ], 422);
            }

            // Delete old photo if exists
            if ($user->profile_photo_path) {
                Storage::disk('public')->delete($user->profile_photo_path);
            }

            // Store new photo (public disk)
            $path = $file->storePublicly('profile_photos', 'public');
            $user->profile_photo_path = $path;

            // Get URL for the stored photo
            $url = asset('storage/' . $path);

            $user->profile_photo_url = $url;
            $user->save();

            return response()->json([
                'status' => 'success',
                'message' => 'Profile photo updated.',
                'profile_photo_path' => $path,
                'profile_photo_url' => $user->profile_photo_url
            ], 200);
        }

        return response()->json([
            'status' => 'error',
            'message' => 'No photo uploaded.'
        ], 422);
    }

    // ----------------------------------------Delete Profile Photo----------------------------------------
    public function deleteProfilePhoto(Request $request)
    {
        $user = $request->user();
        if (!$user || !($user instanceof User)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthenticated.'
            ], 401);
        }

        if ($user->profile_photo_path) {
            Storage::disk('public')->delete($user->profile_photo_path);
            $user->profile_photo_path = null;
            $user->profile_photo_url = null;
            $user->save();

            return response()->json([
                'status' => 'success',
                'message' => 'Profile photo removed.'
            ], 200);
        }

        return response()->json([
            'status' => 'error',
            'message' => 'No profile photo found.'
        ], 404);
    }

    // ----------------------------------------Change Password----------------------------------------
    public function changePassword(Request $request)
    {
        $user = $request->user();

        if (!$user || !($user instanceof User)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthenticated.'
            ], 401);
        }

        $validated = $request->validate([
            'current_password' => 'required|string',
            'new_password' => 'required|string|min:8|confirmed',
            'new_password_confirmation' => 'required|string',
        ]);

        // Check if current password is correct
        if (!Hash::check($validated['current_password'], $user->password)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Current password is incorrect.'
            ], 422);
        }

        // Check if new password is same as current password
        if (Hash::check($validated['new_password'], $user->password)) {
            return response()->json([
                'status' => 'error',
                'message' => 'New password must be different from current password.'
            ], 422);
        }

        // Update password
        $user->password = Hash::make($validated['new_password']);
        $user->save();

        return response()->json([
            'status' => 'success',
            'message' => 'Password changed successfully.'
        ], 200);
    }

    // ----------------------------------------Register Device Token (FCM)----------------------------------------
    public function registerDeviceToken(Request $request)
    {
        $user = $request->user();

        if (!$user || !($user instanceof User)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthenticated.'
            ], 401);
        }

        $validated = $request->validate([
            'token' => 'required|string',
            'platform' => 'nullable|string|max:50',
        ]);

        DeviceToken::updateOrCreate(
            ['token' => $validated['token']],
            [
                'user_id' => $user->id,
                'platform' => $validated['platform'] ?? 'android',
            ]
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Device token registered.',
        ], 200);
    }

    // ----------------------------------------Delete Device Token (FCM)----------------------------------------
    public function deleteDeviceToken(Request $request)
    {
        $user = $request->user();

        if (!$user || !($user instanceof User)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthenticated.'
            ], 401);
        }

        $validated = $request->validate([
            'token' => 'required|string',
        ]);

        DeviceToken::where('user_id', $user->id)
            ->where('token', $validated['token'])
            ->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Device token deleted.',
        ], 200);
    }
}
