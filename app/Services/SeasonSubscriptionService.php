<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Subscription;
use App\Models\Week;
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
     * Skip the current week's delivery WITHOUT consuming subscription weeks.
     *
     * Transactional and idempotent: the week row is locked and re-read, and if it is
     * already skipped the call is a no-op. Refuses (returns ['blocked' => true]) if any
     * order for the week is already paid — checked BEFORE any mutation.
     *
     * State (a) subscriptions_processed == false: just flag the week as skipped.
     * State (b) subscriptions_processed == true: restore each subscription order
     * (weeks_remaining += 1, completed -> active), delete the order, then flag skipped.
     * One-time orders for the week are always hard-deleted.
     *
     * @return array{blocked?: bool, paid_count?: int, skipped?: bool, already?: bool, restored?: int, deleted_one_time?: int, affected_user_ids?: array<int>}
     */
    public function skipWeek(Week $week): array
    {
        return DB::transaction(function () use ($week) {
            // Lock and re-read the week row to guard against concurrent skip/stock updates
            $week = Week::where('id', $week->id)->lockForUpdate()->first();

            // Idempotency guard: already in the target state -> no-op
            if ($week->is_skipped) {
                return [
                    'skipped' => true,
                    'already' => true,
                    'restored' => 0,
                    'deleted_one_time' => 0,
                    'affected_user_ids' => [],
                ];
            }

            // BLOCK condition: refuse if ANY order for the week is already paid.
            // Checked before any mutation so a blocked skip leaves everything untouched.
            $paidCount = Order::where('week_id', $week->id)
                ->where('is_paid', true)
                ->count();

            if ($paidCount > 0) {
                return ['blocked' => true, 'paid_count' => $paidCount];
            }

            $affectedUserIds = [];

            // State (b): subscriptions were already processed -> roll them back
            $restored = 0;
            if ($week->subscriptions_processed) {
                $rollback = $this->rollbackProcessedSubscriptions($week);
                $restored = $rollback['restored'];
                $affectedUserIds = array_merge($affectedUserIds, $rollback['user_ids']);
            }

            // One-time orders (subscription_id == null): hard-delete and notify their owners
            $oneTimeOrders = Order::where('week_id', $week->id)
                ->whereNull('subscription_id')
                ->get();

            $deletedOneTime = 0;
            foreach ($oneTimeOrders as $order) {
                $affectedUserIds[] = $order->user_id;
                $order->delete();
                $deletedOneTime++;
            }

            $week->is_skipped = true;
            $week->save();

            Log::info('Week skipped', [
                'week_id' => $week->id,
                'restored_subscriptions' => $restored,
                'deleted_one_time_orders' => $deletedOneTime,
            ]);

            return [
                'skipped' => true,
                'already' => false,
                'restored' => $restored,
                'deleted_one_time' => $deletedOneTime,
                'affected_user_ids' => array_values(array_unique($affectedUserIds)),
            ];
        });
    }

    /**
     * Un-skip the current week.
     *
     * Clears is_skipped AND resets subscriptions_processed so the normal stock-set flow
     * re-processes subscriptions cleanly. Transactional and idempotent.
     *
     * @return array{unskipped: bool, already: bool}
     */
    public function unskipWeek(Week $week): array
    {
        return DB::transaction(function () use ($week) {
            $week = Week::where('id', $week->id)->lockForUpdate()->first();

            // Idempotency guard: not skipped -> no-op
            if (! $week->is_skipped) {
                return ['unskipped' => true, 'already' => true];
            }

            $week->is_skipped = false;
            $week->subscriptions_processed = false;
            $week->save();

            Log::info('Week un-skipped', ['week_id' => $week->id]);

            return ['unskipped' => true, 'already' => false];
        });
    }

    /**
     * Roll back subscription-generated orders for a week that was already processed.
     *
     * For each order with a non-null subscription_id: restore weeks_remaining (+1) and
     * revert a completed subscription back to active, then delete the order. Cancelled
     * subscriptions are NOT restored (their weeks_remaining is left untouched) but their
     * order for the week is still removed.
     *
     * Assumes it runs inside a transaction with the week row already locked.
     *
     * @return array{restored: int, user_ids: array<int>}
     */
    public function rollbackProcessedSubscriptions(Week $week): array
    {
        $userIds = [];
        $restored = 0;

        $subscriptionOrders = Order::where('week_id', $week->id)
            ->whereNotNull('subscription_id')
            ->with('subscription')
            ->get();

        foreach ($subscriptionOrders as $order) {
            $subscription = $order->subscription;

            if ($subscription && $subscription->status !== 'cancelled') {
                // Give the week back so skipping never consumes a subscription week
                $subscription->weeks_remaining += 1;

                // A subscription auto-completed at 0 becomes active again after restore
                if ($subscription->status === 'completed') {
                    $subscription->status = 'active';
                }

                $subscription->save();
                $restored++;
            }

            $userIds[] = $order->user_id;
            $order->delete();
        }

        return ['restored' => $restored, 'user_ids' => $userIds];
    }

    /**
     * Apply fair trim algorithm - reduce highest subscriptions first, rotating fairly
     *
     * @param  array<int, Order>  $orders  Orders indexed by subscription_id
     * @param  int  $availableStock  Maximum eggs available
     * @param  float  $pricePerDozen  Price per dozen for recalculating totals
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
            $maxOrders = collect($orders)->filter(fn ($order) => $order->quantity === $maxQuantity);

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
            if (! isset($trimmed[$subscriptionId])) {
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
            'subscriptions' => $activeSubscriptions->map(fn ($sub) => [
                'id' => $sub->id,
                'user_name' => $sub->user->name ?? 'Unknown',
                'quantity' => $sub->quantity,
            ]),
        ];
    }
}
