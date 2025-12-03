<?php

namespace App\Http\Controllers;

use App\Models\PushToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PushTokenController extends Controller
{
    /**
     * Register or update a push token for the authenticated user
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'token' => 'required|string',
            'device_type' => 'sometimes|in:ios,android,web',
        ]);

        $user = $request->user();

        // Update or create the push token for this user
        PushToken::updateOrCreate(
            ['user_id' => $user->id],
            [
                'token' => $request->input('token'),
                'device_type' => $request->input('device_type', 'android'),
            ]
        );

        return response()->json([
            'message' => 'Push token registered successfully',
        ]);
    }

    /**
     * Remove the push token for the authenticated user (on logout)
     */
    public function destroy(Request $request): JsonResponse
    {
        $user = $request->user();
        
        PushToken::where('user_id', $user->id)->delete();

        return response()->json([
            'message' => 'Push token removed successfully',
        ]);
    }
}

