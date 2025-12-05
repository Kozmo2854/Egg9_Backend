<?php

namespace App\Http\Controllers;

use App\Models\AppSettings;
use App\Models\Order;
use App\Models\Subscription;
use App\Models\Week;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class SubscriptionController extends Controller
{
    /**
     * Get subscription availability info
     */
    public function getAvailability(Request $request): JsonResponse
    {
        $week = Week::getCurrentWeek();
        $settings = AppSettings::get();

        if (!$week) {
            return response()->json([
                'canSubscribe' => false,
                'reason' => 'no_week',
                'message' => 'No week available for subscriptions',
            ]);
        }

        // Check if low season
        if ($week->is_low_season) {
            return response()->json([
                'canSubscribe' => false,
                'reason' => 'low_season',
                'message' => "It's a quieter time for our chickens right now, and they're laying fewer eggs than usual. Because of this, we're not accepting new subscriptions at the moment.",
                'isLowSeason' => true,
            ]);
        }

        // Check subscription capacity
        $currentTotal = $settings->getTotalSubscriptionEggs();
        $remaining = $settings->getRemainingSubscriptionCapacity();
        $maxPerSub = $settings->max_per_subscription;

        if ($remaining <= 0) {
            return response()->json([
                'canSubscribe' => false,
                'reason' => 'capacity_full',
                'message' => 'Subscription limit has been reached for this period. Please try again later or place a one-time order.',
                'maxSubscriptionEggs' => $settings->max_subscription_eggs,
                'currentTotal' => $currentTotal,
            ]);
        }

        // Calculate max the user can subscribe to
        $maxAllowed = min($remaining, $maxPerSub);

        return response()->json([
            'canSubscribe' => true,
            'maxAllowed' => $maxAllowed,
            'maxPerSubscription' => $maxPerSub,
            'remainingCapacity' => $remaining,
            'currentTotal' => $currentTotal,
            'maxSubscriptionEggs' => $settings->max_subscription_eggs,
            'isLowSeason' => false,
        ]);
    }

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
        $settings = AppSettings::get();
        
        $request->validate([
            'quantity' => 'required|integer|min:10|max:' . $settings->max_per_subscription,
            'period' => 'required|integer|min:2|max:4',
            'start_next_week' => 'sometimes|boolean',
        ]);

        // Validate quantity is multiple of 10
        if ($request->quantity % 10 !== 0) {
            throw ValidationException::withMessages([
                'quantity' => ['Quantity must be a multiple of 10.'],
            ]);
        }

        $week = Week::getCurrentWeek();

        if (!$week) {
            return response()->json([
                'message' => 'No week available for subscriptions',
            ], 400);
        }

        // Check if low season - block new subscriptions
        if ($week->is_low_season) {
            return response()->json([
                'message' => 'low_season',
                'description' => "It's a quieter time for our chickens right now, and they're laying fewer eggs than usual. Because of this, we're not accepting new subscriptions at the moment.",
            ], 400);
        }

        // Check subscription capacity
        $remainingCapacity = $settings->getRemainingSubscriptionCapacity();
        if ($request->quantity > $remainingCapacity) {
            if ($remainingCapacity <= 0) {
                return response()->json([
                    'message' => 'subscription_capacity_full',
                    'description' => 'Subscription limit has been reached. Please try again later or place a one-time order.',
                    'maxSubscriptionEggs' => $settings->max_subscription_eggs,
                    'currentTotal' => $settings->getTotalSubscriptionEggs(),
                ], 400);
            }
            
            // Partial capacity available
            return response()->json([
                'message' => 'subscription_capacity_partial',
                'description' => "Only {$remainingCapacity} eggs available for new subscriptions.",
                'availableCapacity' => $remainingCapacity,
                'requestedQuantity' => $request->quantity,
                'suggestion' => "You can subscribe to {$remainingCapacity} eggs maximum.",
            ], 400);
        }

        // Check available eggs for current week (unless explicitly starting next week)
        $startNextWeek = $request->input('start_next_week', false);
        
        if (!$startNextWeek) {
            // Check if orders for this week are already delivered
            if ($week->all_orders_delivered) {
                $nextWeekStart = now()->addWeek()->startOfWeek();
                $nextWeekEnd = $nextWeekStart->copy()->addDays(6);
                
                return response()->json([
                    'message' => 'insufficient_stock_this_week',
                    'availableEggs' => 0,
                    'requiredEggs' => $request->quantity,
                    'nextWeekStart' => $nextWeekStart->toISOString(),
                    'nextWeekEnd' => $nextWeekEnd->toISOString(),
                    'suggestion' => 'Orders for this week have been delivered. Would you like to start your subscription from next week?',
                ], 409);
            }
            
            // When creating a NEW subscription, check actual available eggs (without adding back user's existing orders)
            // This is different from modifying an order where we add back the user's existing order
            $availableEggs = $week->getAvailableEggsForUser(null);
            
            // If no stock available, offer to start next week
            if ($request->quantity > $availableEggs) {
                $nextWeekStart = now()->addWeek()->startOfWeek();
                $nextWeekEnd = $nextWeekStart->copy()->addDays(6);
                
                return response()->json([
                    'message' => 'insufficient_stock_this_week',
                    'availableEggs' => $availableEggs,
                    'requiredEggs' => $request->quantity,
                    'nextWeekStart' => $nextWeekStart->toISOString(),
                    'nextWeekEnd' => $nextWeekEnd->toISOString(),
                    'suggestion' => 'There are no eggs left for this week. Would you like to start your subscription from next week?',
                ], 409);
            }
        }

        // Cancel any existing active subscription and delete its pending orders
        $oldSubscriptions = Subscription::where('user_id', $request->user()->id)
            ->where('status', 'active')
            ->get();
            
        foreach ($oldSubscriptions as $oldSub) {
            // Delete pending orders from old subscription
            Order::where('subscription_id', $oldSub->id)
                ->where('status', 'pending')
                ->delete();
            
            // Cancel the subscription
            $oldSub->update(['status' => 'cancelled']);
        }

        // Calculate next delivery date (next Monday)
        $nextDelivery = now()->addWeek()->startOfWeek();

        // If creating order for current week, reduce weeks_remaining by 1
        // since we're using one week immediately
        $weeksRemaining = $startNextWeek ? $request->period : $request->period - 1;

        // Create the subscription
        $subscription = Subscription::create([
            'user_id' => $request->user()->id,
            'quantity' => $request->quantity,
            'frequency' => 'weekly',
            'period' => $request->period,
            'weeks_remaining' => $weeksRemaining,
            'status' => 'active',
            'next_delivery' => $nextDelivery,
        ]);

        // Create an order for this week if not starting next week
        if (!$startNextWeek) {
            $total = \App\Models\Order::calculateTotal($request->quantity, $week->price_per_dozen);
            
            \App\Models\Order::create([
                'user_id' => $request->user()->id,
                'subscription_id' => $subscription->id,
                'week_id' => $week->id,
                'quantity' => $request->quantity,
                'total' => $total,
                'status' => 'pending',
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

        // Delete all pending orders associated with this subscription
        Order::where('subscription_id', $subscription->id)
            ->where('status', 'pending')
            ->delete();

        $subscription->update(['status' => 'cancelled']);

        return response()->json([
            'message' => 'Subscription cancelled successfully',
        ]);
    }
}

