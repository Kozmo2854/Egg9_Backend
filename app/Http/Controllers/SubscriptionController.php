<?php

namespace App\Http\Controllers;

use App\Models\Subscription;
use App\Models\WeeklyStock;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class SubscriptionController extends Controller
{
    /**
     * Get the user's active subscription
     */
    public function getCurrent(Request $request)
    {
        $subscription = Subscription::where('user_id', $request->user()->id)
            ->where('status', 'active')
            ->first();

        if (!$subscription) {
            return response()->json([
                'subscription' => null,
            ]);
        }

        return response()->json([
            'subscription' => [
                'id' => $subscription->id,
                'userId' => $subscription->user_id,
                'quantity' => $subscription->quantity,
                'frequency' => $subscription->frequency,
                'period' => $subscription->period,
                'weeksRemaining' => $subscription->weeks_remaining,
                'status' => $subscription->status,
                'nextDelivery' => $subscription->next_delivery ? $subscription->next_delivery->toISOString() : null,
                'createdAt' => $subscription->created_at->toISOString(),
                'updatedAt' => $subscription->updated_at->toISOString(),
            ],
        ]);
    }

    /**
     * Create a new subscription
     */
    public function store(Request $request)
    {
        $request->validate([
            'quantity' => 'required|integer|min:10|max:30',
            'period' => 'required|integer|min:4|max:12',
        ]);

        // Validate quantity is multiple of 10
        if ($request->quantity % 10 !== 0) {
            throw ValidationException::withMessages([
                'quantity' => ['Quantity must be a multiple of 10.'],
            ]);
        }

        $weeklyStock = WeeklyStock::getCurrentWeek();

        if (!$weeklyStock) {
            return response()->json([
                'message' => 'No weekly stock available for subscriptions',
            ], 400);
        }

        // Check available eggs
        $availableEggs = $weeklyStock->getAvailableEggsForUser($request->user()->id);
        if ($request->quantity > $availableEggs) {
            return response()->json([
                'message' => 'Insufficient stock available for subscription',
                'availableEggs' => $availableEggs,
            ], 400);
        }

        // Cancel any existing active subscription
        Subscription::where('user_id', $request->user()->id)
            ->where('status', 'active')
            ->update(['status' => 'cancelled']);

        // Calculate next delivery date (next Monday)
        $nextDelivery = now()->addWeek()->startOfWeek();

        // Create the subscription
        $subscription = Subscription::create([
            'user_id' => $request->user()->id,
            'quantity' => $request->quantity,
            'frequency' => 'weekly',
            'period' => $request->period,
            'weeks_remaining' => $request->period,
            'status' => 'active',
            'next_delivery' => $nextDelivery,
        ]);

        return response()->json([
            'subscription' => [
                'id' => $subscription->id,
                'userId' => $subscription->user_id,
                'quantity' => $subscription->quantity,
                'frequency' => $subscription->frequency,
                'period' => $subscription->period,
                'weeksRemaining' => $subscription->weeks_remaining,
                'status' => $subscription->status,
                'nextDelivery' => $subscription->next_delivery->toISOString(),
                'createdAt' => $subscription->created_at->toISOString(),
                'updatedAt' => $subscription->updated_at->toISOString(),
            ],
        ], 201);
    }

    /**
     * Cancel a subscription
     */
    public function destroy(Request $request, $id)
    {
        $subscription = Subscription::find($id);

        if (!$subscription) {
            return response()->json([
                'message' => 'Subscription not found',
            ], 404);
        }

        // Check ownership
        if ($subscription->user_id !== $request->user()->id) {
            return response()->json([
                'message' => 'Unauthorized to cancel this subscription',
            ], 403);
        }

        // Check if already cancelled or completed
        if ($subscription->status !== 'active') {
            return response()->json([
                'message' => 'Subscription is not active',
            ], 400);
        }

        $subscription->update(['status' => 'cancelled']);

        return response()->json([
            'message' => 'Subscription cancelled successfully',
        ]);
    }
}

