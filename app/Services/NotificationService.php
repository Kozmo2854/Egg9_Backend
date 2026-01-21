<?php

namespace App\Services;

use App\Models\Order;
use App\Models\User;
use App\Models\Week;
use App\Notifications\DeliveryScheduledNotification;
use App\Notifications\OrderDeliveredNotification;
use App\Notifications\PaymentReminderNotification;
use App\Notifications\PickupReminderNotification;
use App\Notifications\StockAvailableNotification;
use App\Notifications\SubscriptionTrimmedNotification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

/**
 * Service class for managing notifications (push + email)
 */
class NotificationService
{
    /**
     * Notify all users that stock is available
     */
    public function notifyStockAvailable(Week $week): void
    {
        Log::info('Sending stock available notification', ['week_id' => $week->id]);

        // Get all non-admin users
        $users = User::where('role', '!=', 'admin')->get();

        if ($users->isEmpty()) {
            Log::info('No users to notify about stock availability');
            return;
        }

        $successCount = 0;
        $failCount = 0;

        foreach ($users as $user) {
            try {
                $user->notify(new StockAvailableNotification($week));
                $successCount++;
            } catch (\Exception $e) {
                $failCount++;
                Log::error('Failed to send stock notification to user', [
                    'user_id' => $user->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        Log::info('Stock available notifications completed', [
            'success' => $successCount,
            'failed' => $failCount,
        ]);
    }

    /**
     * Notify users with orders that their orders have been delivered
     */
    public function notifyOrderDelivered(Week $week): void
    {
        Log::info('Sending order delivered notifications', ['week_id' => $week->id]);

        // Get all orders for this week with their users
        $orders = Order::where('week_id', $week->id)
            ->with('user')
            ->get();

        $successCount = 0;
        $failCount = 0;

        foreach ($orders as $order) {
            if ($order->user && $order->user->role !== 'admin') {
                try {
                    $order->user->notify(new OrderDeliveredNotification(
                        $week,
                        $order->quantity,
                        $order->total
                    ));
                    $successCount++;
                } catch (\Exception $e) {
                    $failCount++;
                    Log::error('Failed to send order delivered notification', [
                        'user_id' => $order->user->id,
                        'order_id' => $order->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        Log::info('Order delivered notifications completed', [
            'success' => $successCount,
            'failed' => $failCount,
        ]);
    }

    /**
     * Notify users with orders about the scheduled delivery
     */
    public function notifyDeliveryScheduled(Week $week): void
    {
        Log::info('Sending delivery scheduled notifications', ['week_id' => $week->id]);

        // Get users who have orders this week
        $userIds = Order::where('week_id', $week->id)->pluck('user_id')->unique();
        $users = User::whereIn('id', $userIds)
            ->where('role', '!=', 'admin')
            ->get();

        if ($users->isEmpty()) {
            Log::info('No users to notify about delivery schedule');
            return;
        }

        $successCount = 0;
        $failCount = 0;

        foreach ($users as $user) {
            try {
                $user->notify(new DeliveryScheduledNotification($week));
                $successCount++;
            } catch (\Exception $e) {
                $failCount++;
                Log::error('Failed to send delivery scheduled notification', [
                    'user_id' => $user->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        Log::info('Delivery scheduled notifications completed', [
            'success' => $successCount,
            'failed' => $failCount,
        ]);
    }

    /**
     * Send payment reminders to users with unpaid delivered orders
     */
    public function notifyPaymentReminder(): void
    {
        Log::info('Processing payment reminder notifications');

        // Find unpaid delivered orders from weeks where delivery has happened
        $unpaidOrders = Order::whereHas('week', function ($query) {
                $query->where('all_orders_delivered', true);
            })
            ->where('is_paid', false)
            ->where('status', 'delivered')
            ->with(['user', 'week'])
            ->get();

        if ($unpaidOrders->isEmpty()) {
            Log::info('No unpaid orders found for payment reminder');
            return;
        }

        $successCount = 0;
        $failCount = 0;

        foreach ($unpaidOrders as $order) {
            if ($order->user && $order->user->role !== 'admin') {
                try {
                    $order->user->notify(new PaymentReminderNotification(
                        $order->quantity,
                        $order->total,
                        $order->week->week_start
                    ));
                    $successCount++;
                } catch (\Exception $e) {
                    $failCount++;
                    Log::error('Failed to send payment reminder notification', [
                        'user_id' => $order->user->id,
                        'order_id' => $order->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        Log::info('Payment reminder notifications completed', [
            'success' => $successCount,
            'failed' => $failCount,
        ]);
    }

    /**
     * Notify a user that their subscription was trimmed due to limited stock
     */
    public function notifySubscriptionTrimmed(int $userId, int $originalQuantity, int $newQuantity): void
    {
        Log::info('Sending subscription trimmed notification', [
            'user_id' => $userId,
            'original' => $originalQuantity,
            'new' => $newQuantity,
        ]);

        $user = User::find($userId);

        if (!$user) {
            Log::warning('User not found for subscription trimmed notification', ['user_id' => $userId]);
            return;
        }

        try {
            $user->notify(new SubscriptionTrimmedNotification($originalQuantity, $newQuantity));
            Log::info('Subscription trimmed notification sent', ['user_id' => $userId]);
        } catch (\Exception $e) {
            Log::error('Failed to send subscription trimmed notification', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Send pickup reminders to users with delivered but not picked up orders from previous weeks
     */
    public function notifyPickupReminder(): void
    {
        Log::info('Processing pickup reminder notifications');

        // Find delivered orders that haven't been picked up from previous weeks
        $currentWeek = Week::getCurrentWeek();
        
        $unpickedOrders = Order::whereHas('week', function ($query) use ($currentWeek) {
                $query->where('all_orders_delivered', true);
                if ($currentWeek) {
                    $query->where('id', '!=', $currentWeek->id);
                }
            })
            ->where('status', 'delivered')
            ->where('picked_up', false)
            ->with(['user', 'week'])
            ->get();

        if ($unpickedOrders->isEmpty()) {
            Log::info('No unpicked orders found for pickup reminder');
            return;
        }

        $successCount = 0;
        $failCount = 0;

        foreach ($unpickedOrders as $order) {
            if ($order->user && $order->user->role !== 'admin') {
                try {
                    $order->user->notify(new PickupReminderNotification(
                        $order->quantity,
                        $order->week->week_start
                    ));
                    $successCount++;
                } catch (\Exception $e) {
                    $failCount++;
                    Log::error('Failed to send pickup reminder notification', [
                        'user_id' => $order->user->id,
                        'order_id' => $order->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        Log::info('Pickup reminder notifications completed', [
            'success' => $successCount,
            'failed' => $failCount,
        ]);
    }
}

