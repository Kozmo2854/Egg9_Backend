<?php

namespace App\Services;

use App\Channels\ExpoPushChannel;
use App\Models\Order;
use App\Models\Subscription;
use App\Models\User;
use App\Models\Week;
use App\Notifications\DeliveryScheduledNotification;
use App\Notifications\OrderDeliveredNotification;
use App\Notifications\PaymentReminderNotification;
use App\Notifications\PickupReminderNotification;
use App\Notifications\StockAvailableNotification;
use App\Notifications\SubscriptionTrimmedNotification;
use App\Notifications\WeekSkippedNotification;
use Illuminate\Support\Facades\Log;

/**
 * Service class for managing notifications (push + email)
 *
 * IMPORTANT: Push notifications are sent FIRST, then emails.
 * This ensures push notifications are delivered quickly even if email is slow/failing.
 */
class NotificationService
{
    public function __construct(
        private ExpoPushChannel $pushChannel
    ) {}

    /**
     * Notify all users that stock is available
     */
    public function notifyStockAvailable(Week $week): void
    {
        Log::info('Sending stock available notification', ['week_id' => $week->id]);

        // Get all non-admin users with their push tokens
        $users = User::where('role', '!=', 'admin')->with('pushToken')->get();

        if ($users->isEmpty()) {
            Log::info('No users to notify about stock availability');

            return;
        }

        // PHASE 1: Send ALL push notifications first (fast)
        $pushSuccess = 0;
        $pushFail = 0;
        foreach ($users as $user) {
            if ($user->push_notifications_enabled && $user->pushToken) {
                try {
                    $this->pushChannel->send($user, new StockAvailableNotification($week));
                    $pushSuccess++;
                } catch (\Exception $e) {
                    $pushFail++;
                    Log::error('Push notification failed', ['user_id' => $user->id, 'error' => $e->getMessage()]);
                }
            }
        }

        // PHASE 2: Send ALL emails (slow, might timeout - but push is already done!)
        $emailSuccess = 0;
        $emailFail = 0;
        foreach ($users as $user) {
            if ($user->email_notifications_enabled && $user->email) {
                try {
                    $user->notify((new StockAvailableNotification($week))->onlyVia('mail'));
                    $emailSuccess++;
                } catch (\Exception $e) {
                    $emailFail++;
                    Log::error('Email notification failed', ['user_id' => $user->id, 'error' => $e->getMessage()]);
                }
            }
        }

        Log::info('Stock available notifications completed', [
            'push_success' => $pushSuccess,
            'push_failed' => $pushFail,
            'email_success' => $emailSuccess,
            'email_failed' => $emailFail,
        ]);
    }

    /**
     * Notify users with orders that their orders have been delivered
     */
    public function notifyOrderDelivered(Week $week): void
    {
        Log::info('Sending order delivered notifications', ['week_id' => $week->id]);

        $orders = Order::where('week_id', $week->id)
            ->with(['user', 'user.pushToken'])
            ->get();

        // PHASE 1: Push notifications first
        $pushSuccess = 0;
        $pushFail = 0;
        foreach ($orders as $order) {
            if ($order->user && $order->user->role !== 'admin' && $order->user->push_notifications_enabled && $order->user->pushToken) {
                try {
                    $this->pushChannel->send($order->user, new OrderDeliveredNotification($week, $order->quantity, $order->total));
                    $pushSuccess++;
                } catch (\Exception $e) {
                    $pushFail++;
                    Log::error('Push notification failed', ['user_id' => $order->user->id, 'error' => $e->getMessage()]);
                }
            }
        }

        // PHASE 2: Emails
        $emailSuccess = 0;
        $emailFail = 0;
        foreach ($orders as $order) {
            if ($order->user && $order->user->role !== 'admin' && $order->user->email_notifications_enabled) {
                try {
                    $order->user->notify((new OrderDeliveredNotification($week, $order->quantity, $order->total))->onlyVia('mail'));
                    $emailSuccess++;
                } catch (\Exception $e) {
                    $emailFail++;
                    Log::error('Email notification failed', ['user_id' => $order->user->id, 'error' => $e->getMessage()]);
                }
            }
        }

        Log::info('Order delivered notifications completed', [
            'push_success' => $pushSuccess, 'push_failed' => $pushFail,
            'email_success' => $emailSuccess, 'email_failed' => $emailFail,
        ]);
    }

    /**
     * Notify users with orders about the scheduled delivery
     */
    public function notifyDeliveryScheduled(Week $week): void
    {
        Log::info('Sending delivery scheduled notifications', ['week_id' => $week->id]);

        $userIds = Order::where('week_id', $week->id)->pluck('user_id')->unique();
        $users = User::whereIn('id', $userIds)
            ->where('role', '!=', 'admin')
            ->with('pushToken')
            ->get();

        if ($users->isEmpty()) {
            Log::info('No users to notify about delivery schedule');

            return;
        }

        // PHASE 1: Push notifications first
        $pushSuccess = 0;
        $pushFail = 0;
        foreach ($users as $user) {
            if ($user->push_notifications_enabled && $user->pushToken) {
                try {
                    $this->pushChannel->send($user, new DeliveryScheduledNotification($week));
                    $pushSuccess++;
                } catch (\Exception $e) {
                    $pushFail++;
                    Log::error('Push notification failed', ['user_id' => $user->id, 'error' => $e->getMessage()]);
                }
            }
        }

        // PHASE 2: Emails
        $emailSuccess = 0;
        $emailFail = 0;
        foreach ($users as $user) {
            if ($user->email_notifications_enabled) {
                try {
                    $user->notify((new DeliveryScheduledNotification($week))->onlyVia('mail'));
                    $emailSuccess++;
                } catch (\Exception $e) {
                    $emailFail++;
                    Log::error('Email notification failed', ['user_id' => $user->id, 'error' => $e->getMessage()]);
                }
            }
        }

        Log::info('Delivery scheduled notifications completed', [
            'push_success' => $pushSuccess, 'push_failed' => $pushFail,
            'email_success' => $emailSuccess, 'email_failed' => $emailFail,
        ]);
    }

    /**
     * Send payment reminders to users with unpaid delivered orders
     */
    public function notifyPaymentReminder(): void
    {
        Log::info('Processing payment reminder notifications');

        $unpaidOrders = Order::whereHas('week', function ($query) {
            $query->where('all_orders_delivered', true);
        })
            ->where('payment_submitted', false)
            ->where('status', 'delivered')
            ->with(['user', 'user.pushToken', 'week'])
            ->get();

        if ($unpaidOrders->isEmpty()) {
            Log::info('No unpaid orders found for payment reminder');

            return;
        }

        // PHASE 1: Push notifications first
        $pushSuccess = 0;
        $pushFail = 0;
        foreach ($unpaidOrders as $order) {
            if ($order->user && $order->user->role !== 'admin' && $order->user->push_notifications_enabled && $order->user->pushToken) {
                try {
                    $this->pushChannel->send($order->user, new PaymentReminderNotification($order->quantity, $order->total, $order->week->week_start));
                    $pushSuccess++;
                } catch (\Exception $e) {
                    $pushFail++;
                    Log::error('Push notification failed', ['user_id' => $order->user->id, 'error' => $e->getMessage()]);
                }
            }
        }

        // PHASE 2: Emails
        $emailSuccess = 0;
        $emailFail = 0;
        foreach ($unpaidOrders as $order) {
            if ($order->user && $order->user->role !== 'admin' && $order->user->email_notifications_enabled) {
                try {
                    $order->user->notify((new PaymentReminderNotification($order->quantity, $order->total, $order->week->week_start))->onlyVia('mail'));
                    $emailSuccess++;
                } catch (\Exception $e) {
                    $emailFail++;
                    Log::error('Email notification failed', ['user_id' => $order->user->id, 'error' => $e->getMessage()]);
                }
            }
        }

        Log::info('Payment reminder notifications completed', [
            'push_success' => $pushSuccess, 'push_failed' => $pushFail,
            'email_success' => $emailSuccess, 'email_failed' => $emailFail,
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

        $user = User::with('pushToken')->find($userId);

        if (! $user) {
            Log::warning('User not found for subscription trimmed notification', ['user_id' => $userId]);

            return;
        }

        // PHASE 1: Push first
        if ($user->push_notifications_enabled && $user->pushToken) {
            try {
                $this->pushChannel->send($user, new SubscriptionTrimmedNotification($originalQuantity, $newQuantity));
                Log::info('Subscription trimmed push sent', ['user_id' => $userId]);
            } catch (\Exception $e) {
                Log::error('Subscription trimmed push failed', ['user_id' => $userId, 'error' => $e->getMessage()]);
            }
        }

        // PHASE 2: Email
        if ($user->email_notifications_enabled) {
            try {
                $user->notify((new SubscriptionTrimmedNotification($originalQuantity, $newQuantity))->onlyVia('mail'));
                Log::info('Subscription trimmed email sent', ['user_id' => $userId]);
            } catch (\Exception $e) {
                Log::error('Subscription trimmed email failed', ['user_id' => $userId, 'error' => $e->getMessage()]);
            }
        }
    }

    /**
     * Notify EVERY customer that the current week has been skipped, with copy tailored
     * to each customer's status for the skipped week:
     *   - active subscription      -> paused-this-week copy (incl. weeks remaining)
     *   - had a one-time order      -> order-cancelled copy
     *   - neither                   -> generic "we'll be back next week" copy
     *
     * Subscription takes precedence over a one-time order if a customer had both.
     * Two-phase (push first, email later), gated on user prefs, failure-tolerant per user.
     *
     * @param  array<int>  $oneTimeUserIds  IDs of customers whose one-time order the skip cancelled
     *                                      (captured before deletion — their orders no longer exist)
     */
    public function notifyWeekSkipped(Week $week, array $oneTimeUserIds = []): void
    {
        Log::info('Sending week skipped notifications', ['week_id' => $week->id]);

        // ALL non-admin customers are notified, regardless of whether they ordered.
        $users = User::where('role', '!=', 'admin')
            ->with('pushToken')
            ->get();

        if ($users->isEmpty()) {
            Log::info('No customers to notify about skipped week');

            return;
        }

        $oneTimeUserIds = array_flip(array_values(array_unique($oneTimeUserIds)));

        // Weeks remaining per user, from their active subscription (absent = no subscription)
        $weeksRemainingByUser = Subscription::whereIn('user_id', $users->pluck('id'))
            ->where('status', 'active')
            ->pluck('weeks_remaining', 'user_id');

        // Resolve the notification (variant + copy) for a given user.
        $notificationFor = function (User $user) use ($weeksRemainingByUser, $oneTimeUserIds): WeekSkippedNotification {
            if (isset($weeksRemainingByUser[$user->id])) {
                return new WeekSkippedNotification(
                    WeekSkippedNotification::VARIANT_SUBSCRIPTION,
                    (int) $weeksRemainingByUser[$user->id]
                );
            }

            if (isset($oneTimeUserIds[$user->id])) {
                return new WeekSkippedNotification(WeekSkippedNotification::VARIANT_ORDER_CANCELLED);
            }

            return new WeekSkippedNotification(WeekSkippedNotification::VARIANT_NONE);
        };

        // PHASE 1: Push notifications first
        $pushSuccess = 0;
        $pushFail = 0;
        foreach ($users as $user) {
            if ($user->push_notifications_enabled && $user->pushToken) {
                try {
                    $this->pushChannel->send($user, $notificationFor($user));
                    $pushSuccess++;
                } catch (\Exception $e) {
                    $pushFail++;
                    Log::error('Push notification failed', ['user_id' => $user->id, 'error' => $e->getMessage()]);
                }
            }
        }

        // PHASE 2: Emails
        $emailSuccess = 0;
        $emailFail = 0;
        foreach ($users as $user) {
            if ($user->email_notifications_enabled) {
                try {
                    $user->notify($notificationFor($user)->onlyVia('mail'));
                    $emailSuccess++;
                } catch (\Exception $e) {
                    $emailFail++;
                    Log::error('Email notification failed', ['user_id' => $user->id, 'error' => $e->getMessage()]);
                }
            }
        }

        Log::info('Week skipped notifications completed', [
            'push_success' => $pushSuccess, 'push_failed' => $pushFail,
            'email_success' => $emailSuccess, 'email_failed' => $emailFail,
        ]);
    }

    /**
     * Send pickup reminders to users with delivered but not picked up orders from previous weeks
     */
    public function notifyPickupReminder(): void
    {
        Log::info('Processing pickup reminder notifications');

        $currentWeek = Week::getCurrentWeek();

        $unpickedOrders = Order::whereHas('week', function ($query) use ($currentWeek) {
            $query->where('all_orders_delivered', true);
            if ($currentWeek) {
                $query->where('id', '!=', $currentWeek->id);
            }
        })
            ->where('status', 'delivered')
            ->where('picked_up', false)
            ->with(['user', 'user.pushToken', 'week'])
            ->get();

        if ($unpickedOrders->isEmpty()) {
            Log::info('No unpicked orders found for pickup reminder');

            return;
        }

        // PHASE 1: Push notifications first
        $pushSuccess = 0;
        $pushFail = 0;
        foreach ($unpickedOrders as $order) {
            if ($order->user && $order->user->role !== 'admin' && $order->user->push_notifications_enabled && $order->user->pushToken) {
                try {
                    $this->pushChannel->send($order->user, new PickupReminderNotification($order->quantity, $order->week->week_start));
                    $pushSuccess++;
                } catch (\Exception $e) {
                    $pushFail++;
                    Log::error('Push notification failed', ['user_id' => $order->user->id, 'error' => $e->getMessage()]);
                }
            }
        }

        // PHASE 2: Emails
        $emailSuccess = 0;
        $emailFail = 0;
        foreach ($unpickedOrders as $order) {
            if ($order->user && $order->user->role !== 'admin' && $order->user->email_notifications_enabled) {
                try {
                    $order->user->notify((new PickupReminderNotification($order->quantity, $order->week->week_start))->onlyVia('mail'));
                    $emailSuccess++;
                } catch (\Exception $e) {
                    $emailFail++;
                    Log::error('Email notification failed', ['user_id' => $order->user->id, 'error' => $e->getMessage()]);
                }
            }
        }

        Log::info('Pickup reminder notifications completed', [
            'push_success' => $pushSuccess, 'push_failed' => $pushFail,
            'email_success' => $emailSuccess, 'email_failed' => $emailFail,
        ]);
    }
}
