<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

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
     * Send a password reset link to the given email (if it belongs to an account)
     */
    public function forgotPassword(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        $email = $request->email;

        // The send runs after the response has been delivered (defer()), so known and
        // unknown emails cost the client the same time.
        defer(function () use ($email) {
            // The client may already have disconnected; finish the send anyway.
            ignore_user_abort(true);

            try {
                Password::sendResetLink(['email' => $email]);
            } catch (Throwable $e) {
                // Class + code only: mailer messages can contain the recipient address.
                Log::error('Password reset email failed', [
                    'exception_class' => get_class($e),
                    'code' => $e->getCode(),
                ]);
            }
        });

        // Deliberate deviation from the docs: one uniform response (sent / unknown user / throttled) to avoid account enumeration.
        $response = response()->json([
            'message' => 'Check your mailbox for a password reset link.',
        ]);

        // Deliberate addition: under Apache/mod_php (no fastcgi_finish_request) the deferred send runs on the
        // still-open connection; Content-Length + Connection: close let the client finish reading first.
        $response->headers->set('Content-Length', (string) strlen($response->getContent()));
        $response->headers->set('Connection', 'close');

        return $response;
    }

    /**
     * Reset the password using an emailed reset token
     */
    public function resetPassword(Request $request)
    {
        $request->validate([
            'token' => 'required|string',
            'email' => 'required|email',
            'password' => 'required|string|min:6|confirmed',
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) {
                $user->forceFill([
                    'password' => Hash::make($password),
                    'remember_token' => Str::random(60),
                ])->save();

                // Sign the user out everywhere
                $user->tokens()->delete();

                event(new PasswordReset($user));
            }
        );

        // Deliberate deviation from the docs: one uniform error (unknown user / bad / expired / used token) to avoid account enumeration.
        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages([
                'token' => ['This password reset link is invalid or has expired.'],
            ]);
        }

        return response()->json([
            'message' => 'Password has been reset successfully.',
        ]);
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
        // Drop any pending password reset token for this account
        Password::deleteToken($user);
        
        $user->delete();

        return response()->json([
            'message' => 'Account deleted successfully',
        ]);
    }
}

