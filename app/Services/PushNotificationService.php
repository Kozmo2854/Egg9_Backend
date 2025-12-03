<?php

namespace App\Services;

use App\Jobs\Notifications\DeliveryScheduledNotification;
use App\Jobs\Notifications\OrderDeliveredNotification;
use App\Jobs\Notifications\PaymentReminderNotification;
use App\Jobs\Notifications\StockAvailableNotification;
use App\Models\Week;
use Illuminate\Support\Facades\Log;

/**
 * Service class for managing push notifications
 */
class PushNotificationService
{
    /**
     * Notify all users that stock is available
     *
     * @param Week $week The week with available stock
     * @return void
     */
    public function notifyStockAvailable(Week $week): void
    {
        Log::info('Dispatching stock available notification', ['week_id' => $week->id]);
        StockAvailableNotification::dispatch($week);
    }

    /**
     * Notify users with orders that their order has been delivered
     *
     * @param Week $week The week whose orders were delivered
     * @return void
     */
    public function notifyOrderDelivered(Week $week): void
    {
        Log::info('Dispatching order delivered notification', ['week_id' => $week->id]);
        OrderDeliveredNotification::dispatch($week);
    }

    /**
     * Notify users with orders about the scheduled delivery
     *
     * @param Week $week The week with scheduled delivery
     * @return void
     */
    public function notifyDeliveryScheduled(Week $week): void
    {
        Log::info('Dispatching delivery scheduled notification', ['week_id' => $week->id]);
        DeliveryScheduledNotification::dispatch($week);
    }

    /**
     * Send payment reminders to users with unpaid orders (delivered yesterday)
     *
     * @return void
     */
    public function notifyPaymentReminder(): void
    {
        Log::info('Dispatching payment reminder notifications');
        PaymentReminderNotification::dispatch();
    }
}

