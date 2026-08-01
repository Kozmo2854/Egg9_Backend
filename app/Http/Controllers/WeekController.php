<?php

namespace App\Http\Controllers;

use App\Models\Week;
use App\Services\NotificationService;
use App\Services\SeasonSubscriptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WeekController extends Controller
{
    public function __construct(
        private NotificationService $notificationService,
        private SeasonSubscriptionService $subscriptionService
    ) {}

    /**
     * Get current week's information
     */
    public function getCurrentWeek(Request $request): JsonResponse
    {
        $week = Week::getCurrentWeek();

        if (! $week) {
            return response()->json([
                'message' => 'No week available for ordering',
            ], 404);
        }

        return response()->json([
            'week' => $this->formatWeek($week),
        ]);
    }

    /**
     * Calculate available eggs for the current user
     */
    public function getAvailableEggs(Request $request): JsonResponse
    {
        $week = Week::getCurrentWeek();

        if (! $week) {
            return response()->json([
                'message' => 'No week available for ordering',
            ], 404);
        }

        // Don't pass userId - we want ACTUAL available eggs (minus ALL orders including this user's)
        $availableEggs = $week->getAvailableEggsForUser(null);

        return response()->json([
            'availableEggs' => $availableEggs,
        ]);
    }

    /**
     * Update current week's stock and/or delivery info (Admin only)
     */
    public function updateCurrentWeek(Request $request): JsonResponse
    {
        $request->validate([
            'available_eggs' => 'sometimes|integer|min:0',
            'price_per_dozen' => 'sometimes|numeric|min:0|max:999999.99',
            'delivery_date' => 'nullable|date',
            'delivery_time' => 'nullable|string|max:255',
            'is_low_season' => 'sometimes|boolean',
        ]);

        $week = Week::getCurrentWeek();

        if (! $week) {
            return response()->json([
                'message' => 'No current week found',
            ], 404);
        }

        $previousAvailableEggs = $week->available_eggs;
        $previousDeliveryDate = $week->delivery_date;
        $subscriptionsProcessed = $week->subscriptions_processed;

        if ($request->has('available_eggs')) {
            $week->available_eggs = $request->input('available_eggs');
            // Open ordering when stock is added
            if ($request->input('available_eggs') > 0) {
                $week->is_ordering_open = true;
            }
        }

        if ($request->has('price_per_dozen')) {
            $week->price_per_dozen = $request->input('price_per_dozen');
        }

        if ($request->has('delivery_date')) {
            $week->delivery_date = $request->input('delivery_date');
        }

        if ($request->has('delivery_time')) {
            $week->delivery_time = $request->input('delivery_time');
        }

        if ($request->has('is_low_season')) {
            $week->is_low_season = $request->input('is_low_season');
        }

        $week->save();

        // Process subscriptions when stock is first set (regardless of season)
        // Subscriptions are always honored, just can't create NEW ones in low season
        $subscriptionResult = null;
        if (! $subscriptionsProcessed && $week->available_eggs > 0 && ! $week->is_skipped) {
            $subscriptionResult = $this->subscriptionService->processSubscriptionsForWeek($week);
        }

        // Send notifications for relevant changes (don't break request if notification fails)
        // Stock available: only when setting stock for the first time (was 0, now > 0)
        if ($previousAvailableEggs == 0 && $week->available_eggs > 0) {
            try {
                $this->notificationService->notifyStockAvailable($week);
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error('Failed to send stock available notification', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }
        }

        // Delivery scheduled: only when setting delivery date for the first time
        if (! $previousDeliveryDate && $week->delivery_date) {
            try {
                $this->notificationService->notifyDeliveryScheduled($week);
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error('Failed to send delivery scheduled notification', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }
        }

        $response = [
            'message' => 'Week updated successfully',
            'week' => $this->formatWeek($week),
        ];

        if ($subscriptionResult) {
            $response['subscriptions'] = $subscriptionResult;
        }

        return response()->json($response);
    }

    /**
     * Get subscription preview before processing (Admin only)
     */
    public function getSubscriptionPreview(Request $request): JsonResponse
    {
        $request->validate([
            'available_eggs' => 'required|integer|min:0',
        ]);

        $preview = $this->subscriptionService->getSubscriptionPreview(
            $request->input('available_eggs')
        );

        return response()->json($preview);
    }

    /**
     * Mark all orders as delivered for the current week (Admin only)
     */
    public function markAllDelivered(): JsonResponse
    {
        $week = Week::getCurrentWeek();

        if (! $week) {
            return response()->json([
                'message' => 'No current week found',
            ], 404);
        }

        $week->all_orders_delivered = true;
        $week->save();

        // Also update all orders for this week to 'completed' status
        $week->orders()->where('status', 'approved')->update(['status' => 'completed']);

        // Notify users that their orders have been delivered (don't break request if notification fails)
        try {
            $this->notificationService->notifyOrderDelivered($week);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Failed to send order delivered notification', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }

        return response()->json([
            'message' => 'All orders marked as delivered',
            'week' => $this->formatWeek($week),
        ]);
    }

    /**
     * Skip the current week's delivery (Admin only)
     *
     * Skipping does NOT consume subscription weeks: any subscription order already
     * created for this week is removed and its weeks_remaining is restored, so the
     * subscription resumes on the next cycle. One-time orders for the week are removed.
     * Refuses (409) if any order for the week is already paid.
     */
    public function skipCurrentWeek(Request $request): JsonResponse
    {
        $week = Week::getCurrentWeek();

        if (! $week) {
            return response()->json([
                'message' => 'No current week found',
            ], 404);
        }

        $result = $this->subscriptionService->skipWeek($week);

        // Blocked because one or more orders for the week are already paid
        if (! empty($result['blocked'])) {
            return response()->json([
                'message' => "Cannot skip: {$result['paid_count']} order(s) already paid — handle those first",
            ], 409);
        }

        // Notify ALL customers AFTER the transaction has committed (failure-tolerant).
        // Skip re-notifying when the week was already skipped (idempotent no-op).
        if (empty($result['already'])) {
            try {
                $this->notificationService->notifyWeekSkipped($week->fresh(), $result['one_time_user_ids'] ?? []);
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error('Failed to send week skipped notification', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }
        }

        return response()->json([
            'message' => 'Week skipped successfully',
            'week' => $this->formatWeek($week->fresh()),
            'restoredSubscriptions' => $result['restored'] ?? 0,
            'deletedOneTimeOrders' => $result['deleted_one_time'] ?? 0,
        ]);
    }

    /**
     * Un-skip the current week (Admin only)
     *
     * Clears is_skipped and resets subscriptions_processed so the normal stock-set
     * flow re-processes subscriptions cleanly. Idempotent.
     */
    public function unskipCurrentWeek(Request $request): JsonResponse
    {
        $week = Week::getCurrentWeek();

        if (! $week) {
            return response()->json([
                'message' => 'No current week found',
            ], 404);
        }

        $this->subscriptionService->unskipWeek($week);

        return response()->json([
            'message' => 'Week un-skipped successfully',
            'week' => $this->formatWeek($week->fresh()),
        ]);
    }

    /**
     * Format week data for API response
     */
    private function formatWeek(Week $week): array
    {
        return [
            'id' => $week->id,
            'weekStart' => $week->week_start->toISOString(),
            'weekEnd' => $week->week_end->toISOString(),
            'availableEggs' => $week->available_eggs,
            'pricePerDozen' => (float) $week->price_per_dozen,
            'isOrderingOpen' => $week->is_ordering_open,
            'deliveryDate' => $week->delivery_date ? $week->delivery_date->toISOString() : null,
            'deliveryTime' => $week->delivery_time,
            'allOrdersDelivered' => $week->all_orders_delivered,
            'isLowSeason' => $week->is_low_season,
            'subscriptionsProcessed' => $week->subscriptions_processed,
            'isSkipped' => $week->is_skipped,
            'lowSeasonOrderCap' => $week->getLowSeasonOrderCap(),
        ];
    }
}
