<?php

namespace App\Services;

use App\Models\Order;
use App\Models\User;
use App\Models\Week;
use App\Notifications\DeliveryScheduledNotification;
use App\Notifications\OrderDeliveredNotification;
use App\Notifications\PaymentReminderNotification;
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

        Notification::send($users, new StockAvailableNotification($week));

        Log::info('Stock available notification sent', ['user_count' => $users->count()]);
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

        foreach ($orders as $order) {
            if ($order->user && $order->user->role !== 'admin') {
                $order->user->notify(new OrderDeliveredNotification(
                    $week,
                    $order->quantity,
                    $order->total
                ));
            }
        }

        Log::info('Order delivered notifications sent', ['order_count' => $orders->count()]);
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

        Notification::send($users, new DeliveryScheduledNotification($week));

        Log::info('Delivery scheduled notifications sent', ['user_count' => $users->count()]);
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

        foreach ($unpaidOrders as $order) {
            if ($order->user && $order->user->role !== 'admin') {
                $order->user->notify(new PaymentReminderNotification(
                    $order->quantity,
                    $order->total,
                    $order->week->week_start
                ));
            }
        }

        Log::info('Payment reminder notifications sent', ['order_count' => $unpaidOrders->count()]);
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

        $user->notify(new SubscriptionTrimmedNotification($originalQuantity, $newQuantity));
    }
}

