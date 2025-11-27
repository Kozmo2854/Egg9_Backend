<?php

namespace App\Services;

use App\Models\Subscription;
use App\Models\Order;
use App\Models\Week;
use App\Models\User;

/**
 * Service class for subscription-related business logic
 */
class SubscriptionService
{
    /**
     * Format subscription data for API response
     *
     * @param Subscription $subscription
     * @return array
     */
    public function formatSubscription(Subscription $subscription): array
    {
        return [
            'id' => $subscription->id,
            'quantity' => $subscription->quantity,
            'period' => $subscription->period,
            'weeksRemaining' => $subscription->weeks_remaining,
            'status' => $subscription->status,
        ];
    }

    /**
     * Calculate weeks remaining based on period and whether subscription starts next week
     *
     * @param int $period Total subscription period in weeks
     * @param bool $startNextWeek Whether subscription starts next week (true) or this week (false)
     * @return int Remaining weeks after initial order
     */
    public function calculateWeeksRemaining(int $period, bool $startNextWeek): int
    {
        return $startNextWeek ? $period : $period - 1;
    }

    /**
     * Delete all pending orders associated with a subscription
     *
     * @param Subscription $subscription
     * @return int Number of orders deleted
     */
    public function deletePendingOrders(Subscription $subscription): int
    {
        return Order::where('subscription_id', $subscription->id)
            ->where('status', 'pending')
            ->delete();
    }

    /**
     * Create initial order for subscription if starting this week
     *
     * @param Subscription $subscription
     * @param Week $week
     * @param float $total
     * @return Order|null
     */
    public function createInitialOrder(Subscription $subscription, Week $week, float $total): ?Order
    {
        return Order::create([
            'user_id' => $subscription->user_id,
            'week_id' => $week->id,
            'subscription_id' => $subscription->id,
            'quantity' => $subscription->quantity,
            'total' => $total,
            'status' => 'pending',
        ]);
    }

    /**
     * Check if user has enough stock for subscription
     *
     * @param User $user
     * @param Week $week
     * @param int $requiredQuantity
     * @return array ['hasStock' => bool, 'available' => int]
     */
    public function checkStockAvailability(User $user, Week $week, int $requiredQuantity): array
    {
        $availableEggs = $week->getAvailableEggsForUser($user->id);
        
        return [
            'hasStock' => $availableEggs >= $requiredQuantity,
            'available' => $availableEggs,
        ];
    }

    /**
     * Get active subscription for a user
     *
     * @param int $userId
     * @return Subscription|null
     */
    public function getActiveSubscription(int $userId): ?Subscription
    {
        return Subscription::where('user_id', $userId)
            ->where('status', 'active')
            ->first();
    }
}

