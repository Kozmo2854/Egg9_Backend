<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Login user and return token
     */
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        // Revoke all existing tokens and create a new one
        $user->tokens()->delete();
        $token = $user->createToken('api-token')->plainTextToken;

        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phoneNumber' => $user->phone_number,
                'role' => $user->role,
                'pushNotificationsEnabled' => $user->push_notifications_enabled,
                'emailNotificationsEnabled' => $user->email_notifications_enabled,
                'createdAt' => $user->created_at->toISOString(),
            ],
            'token' => $token,
        ]);
    }

    /**
     * Register a new customer
     */
    public function register(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'phone_number' => 'required|string|max:20',
            'password' => 'required|string|min:6',
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'phone_number' => $request->phone_number,
            'password' => Hash::make($request->password),
            'role' => 'customer',
        ]);

        $token = $user->createToken('api-token')->plainTextToken;

        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phoneNumber' => $user->phone_number,
                'role' => $user->role,
                'pushNotificationsEnabled' => $user->push_notifications_enabled,
                'emailNotificationsEnabled' => $user->email_notifications_enabled,
                'createdAt' => $user->created_at->toISOString(),
            ],
            'token' => $token,
        ], 201);
    }

    /**
     * Logout user (revoke token)
     */
    public function logout(Request $request)
    {
        // Delete the current access token if it exists
        $token = $request->user()->currentAccessToken();
        if ($token) {
            $token->delete();
        }

        return response()->json([
            'message' => 'Logged out successfully',
        ]);
    }

    /**
     * Get current authenticated user
     */
    public function user(Request $request)
    {
        $user = $request->user();

        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phoneNumber' => $user->phone_number,
                'role' => $user->role,
                'pushNotificationsEnabled' => $user->push_notifications_enabled,
                'emailNotificationsEnabled' => $user->email_notifications_enabled,
                'createdAt' => $user->created_at->toISOString(),
            ],
        ]);
    }

    /**
     * Update user profile (name, phone)
     */
    public function updateProfile(Request $request)
    {
        $request->validate([
            'name' => 'sometimes|required|string|max:255|min:2',
            'phone_number' => 'sometimes|required|string|max:20|min:8',
        ]);

        $user = $request->user();
        
        if ($request->has('name')) {
            $user->name = $request->name;
        }
        if ($request->has('phone_number')) {
            $user->phone_number = $request->phone_number;
        }
        
        $user->save();

        return response()->json([
            'message' => 'Profile updated successfully',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phoneNumber' => $user->phone_number,
                'role' => $user->role,
                'pushNotificationsEnabled' => $user->push_notifications_enabled,
                'emailNotificationsEnabled' => $user->email_notifications_enabled,
                'createdAt' => $user->created_at->toISOString(),
            ],
        ]);
    }

    /**
     * Update user email (requires password confirmation)
     */
    public function updateEmail(Request $request)
    {
        $request->validate([
            'email' => 'required|email|unique:users,email,' . $request->user()->id,
            'password' => 'required|string',
        ]);

        $user = $request->user();

        if (!Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'password' => ['The password is incorrect.'],
            ]);
        }

        $user->email = $request->email;
        $user->save();

        return response()->json([
            'message' => 'Email updated successfully',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phoneNumber' => $user->phone_number,
                'role' => $user->role,
                'pushNotificationsEnabled' => $user->push_notifications_enabled,
                'emailNotificationsEnabled' => $user->email_notifications_enabled,
                'createdAt' => $user->created_at->toISOString(),
            ],
        ]);
    }

    /**
     * Update user password
     */
    public function updatePassword(Request $request)
    {
        $request->validate([
            'current_password' => 'required|string',
            'new_password' => 'required|string|min:6|confirmed',
        ]);

        $user = $request->user();

        if (!Hash::check($request->current_password, $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['The current password is incorrect.'],
            ]);
        }

        $user->password = Hash::make($request->new_password);
        $user->save();

        return response()->json([
            'message' => 'Password updated successfully',
        ]);
    }

    /**
     * Update notification preferences
     */
    public function updateNotifications(Request $request)
    {
        $request->validate([
            'push_notifications_enabled' => 'sometimes|boolean',
            'email_notifications_enabled' => 'sometimes|boolean',
        ]);

        $user = $request->user();
        
        if ($request->has('push_notifications_enabled')) {
            $user->push_notifications_enabled = $request->push_notifications_enabled;
        }
        if ($request->has('email_notifications_enabled')) {
            $user->email_notifications_enabled = $request->email_notifications_enabled;
        }
        
        $user->save();

        return response()->json([
            'message' => 'Notification preferences updated',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phoneNumber' => $user->phone_number,
                'role' => $user->role,
                'pushNotificationsEnabled' => $user->push_notifications_enabled,
                'emailNotificationsEnabled' => $user->email_notifications_enabled,
                'createdAt' => $user->created_at->toISOString(),
            ],
        ]);
    }

    /**
     * Delete user account (requires password confirmation)
     */
    public function deleteAccount(Request $request)
    {
        $request->validate([
            'password' => 'required|string',
        ]);

        $user = $request->user();

        if (!Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'password' => ['The password is incorrect.'],
            ]);
        }

        // Delete related data
        $user->tokens()->delete();
        $user->pushToken()->delete();
        $user->subscriptions()->delete();
        // Keep orders for record keeping but nullify user
        $user->orders()->update(['user_id' => null]);
        
        $user->delete();

        return response()->json([
            'message' => 'Account deleted successfully',
        ]);
    }
}

