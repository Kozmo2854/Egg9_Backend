<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Subscription;
use App\Models\Week;
use App\Services\NotificationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SeasonSubscriptionService
{
    public function __construct(
        protected NotificationService $notificationService
    ) {}

    /**
     * Process subscriptions for a week when admin sets stock
     * This should only be called once per week (when subscriptions_processed is false)
     * 
     * @return array{processed: int, trimmed: array, total_eggs: int}
     */
    public function processSubscriptionsForWeek(Week $week): array
    {
        if ($week->subscriptions_processed) {
            Log::info('Subscriptions already processed for this week', ['week_id' => $week->id]);
            return ['processed' => 0, 'trimmed' => [], 'total_eggs' => 0];
        }

        $activeSubscriptions = Subscription::where('status', 'active')->get();

        if ($activeSubscriptions->isEmpty()) {
            $week->update(['subscriptions_processed' => true]);
            return ['processed' => 0, 'trimmed' => [], 'total_eggs' => 0];
        }

        $result = DB::transaction(function () use ($week, $activeSubscriptions) {
            $orders = [];
            $totalDemand = $activeSubscriptions->sum('quantity');

            // Create initial orders for all subscriptions
            foreach ($activeSubscriptions as $subscription) {
                $order = Order::create([
                    'user_id' => $subscription->user_id,
                    'subscription_id' => $subscription->id,
                    'week_id' => $week->id,
                    'quantity' => $subscription->quantity,
                    'total' => Order::calculateTotal($subscription->quantity, $week->price_per_dozen),
                    'status' => 'pending',
                    'is_paid' => false,
                ]);
                $orders[$subscription->id] = $order;

                // Decrement weeks remaining
                $subscription->weeks_remaining--;
                $subscription->next_delivery = now()->addWeek()->startOfWeek();

                if ($subscription->weeks_remaining <= 0) {
                    $subscription->status = 'completed';
                }
                $subscription->save();
            }

            // If demand exceeds stock, apply fair trim algorithm
            $trimmed = [];
            if ($totalDemand > $week->available_eggs) {
                $trimmed = $this->applyFairTrim($orders, $week->available_eggs, $week->price_per_dozen);
            }

            $week->update(['subscriptions_processed' => true]);

            return [
                'processed' => count($orders),
                'trimmed' => $trimmed,
                'total_eggs' => collect($orders)->sum('quantity'),
            ];
        });

        // Send notifications to trimmed users
        foreach ($result['trimmed'] as $trim) {
            $this->notifyUserOfTrim($trim['user_id'], $trim['original'], $trim['new']);
        }

        return $result;
    }

    /**
     * Apply fair trim algorithm - reduce highest subscriptions first, rotating fairly
     * 
     * @param array<int, Order> $orders Orders indexed by subscription_id
     * @param int $availableStock Maximum eggs available
     * @param float $pricePerDozen Price per dozen for recalculating totals
     * @return array Trimmed orders info
     */
    private function applyFairTrim(array &$orders, int $availableStock, float $pricePerDozen): array
    {
        $trimmed = [];
        $currentTotal = collect($orders)->sum('quantity');

        while ($currentTotal > $availableStock) {
            // Find the maximum quantity
            $maxQuantity = collect($orders)->max('quantity');

            // Find all orders at max quantity
            $maxOrders = collect($orders)->filter(fn($order) => $order->quantity === $maxQuantity);

            if ($maxOrders->isEmpty() || $maxQuantity <= 10) {
                // Can't reduce further (minimum is 10 eggs = 1 dozen)
                break;
            }

            // Pick the first one at max (round-robin effect through iterations)
            $orderToReduce = $maxOrders->first();
            $subscriptionId = $orderToReduce->subscription_id;
            $originalQuantity = $orderToReduce->quantity;
            $newQuantity = $originalQuantity - 10;

            // Track the trim
            if (!isset($trimmed[$subscriptionId])) {
                $trimmed[$subscriptionId] = [
                    'order_id' => $orderToReduce->id,
                    'user_id' => $orderToReduce->user_id,
                    'original' => $originalQuantity,
                    'new' => $newQuantity,
                ];
            } else {
                $trimmed[$subscriptionId]['new'] = $newQuantity;
            }

            // Update the order
            $orderToReduce->quantity = $newQuantity;
            $orderToReduce->total = Order::calculateTotal($newQuantity, $pricePerDozen);
            $orderToReduce->save();

            // Update our tracking array
            $orders[$subscriptionId] = $orderToReduce;

            $currentTotal -= 10;

            Log::info('Trimmed subscription order', [
                'order_id' => $orderToReduce->id,
                'subscription_id' => $subscriptionId,
                'from' => $originalQuantity,
                'to' => $newQuantity,
                'remaining_total' => $currentTotal,
            ]);
        }

        return array_values($trimmed);
    }

    /**
     * Notify user that their subscription was trimmed
     */
    private function notifyUserOfTrim(int $userId, int $originalQuantity, int $newQuantity): void
    {
        $this->notificationService->notifySubscriptionTrimmed($userId, $originalQuantity, $newQuantity);
    }

    /**
     * Calculate total subscription demand from active subscriptions
     */
    public function getTotalSubscriptionDemand(): int
    {
        return Subscription::where('status', 'active')->sum('quantity');
    }

    /**
     * Check if stock is sufficient for all subscriptions
     */
    public function isStockSufficientForSubscriptions(int $stock): bool
    {
        return $stock >= $this->getTotalSubscriptionDemand();
    }

    /**
     * Get subscription processing preview (before actually processing)
     */
    public function getSubscriptionPreview(int $availableStock): array
    {
        $activeSubscriptions = Subscription::where('status', 'active')
            ->with('user:id,name')
            ->get();

        $totalDemand = $activeSubscriptions->sum('quantity');
        $willTrim = $totalDemand > $availableStock;
        $deficit = max(0, $totalDemand - $availableStock);

        return [
            'subscription_count' => $activeSubscriptions->count(),
            'total_demand' => $totalDemand,
            'available_stock' => $availableStock,
            'will_trim' => $willTrim,
            'deficit' => $deficit,
            'remaining_for_orders' => max(0, $availableStock - $totalDemand),
            'subscriptions' => $activeSubscriptions->map(fn($sub) => [
                'id' => $sub->id,
                'user_name' => $sub->user->name ?? 'Unknown',
                'quantity' => $sub->quantity,
            ]),
        ];
    }
}

