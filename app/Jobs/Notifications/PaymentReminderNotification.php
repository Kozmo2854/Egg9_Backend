<?php

namespace App\Jobs\Notifications;

use App\Models\Order;
use App\Models\PushToken;
use App\Models\Week;

class PaymentReminderNotification extends BaseNotificationJob
{
    /**
     * Create a new job instance.
     */
    public function __construct() {}

    /**
     * Execute the job.
     * Sends reminder to users with unpaid orders from weeks delivered yesterday.
     */
    public function handle(): void
    {
        // Find weeks that were marked as delivered yesterday
        $yesterday = now()->subDay()->toDateString();
        
        $weeksDeliveredYesterday = Week::where('all_orders_delivered', true)
            ->whereDate('updated_at', $yesterday)
            ->pluck('id')
            ->toArray();

        if (empty($weeksDeliveredYesterday)) {
            return;
        }

        // Get users with unpaid orders from these weeks
        $unpaidOrders = Order::whereIn('week_id', $weeksDeliveredYesterday)
            ->where('is_paid', false)
            ->with('user')
            ->get();

        // Group by user to send one notification per user with their total
        $userTotals = $unpaidOrders->groupBy('user_id')->map(function ($orders) {
            return [
                'user_id' => $orders->first()->user_id,
                'total' => $orders->sum('total'),
                'quantity' => $orders->sum('quantity'),
            ];
        });

        foreach ($userTotals as $userTotal) {
            $token = PushToken::where('user_id', $userTotal['user_id'])
                ->value('token');

            if ($token) {
                $this->sendBatch(
                    [$token],
                    '💳 Payment Reminder',
                    "Don't forget to pay for your {$userTotal['quantity']} eggs ({$userTotal['total']} RSD)",
                    [
                        'type' => 'payment_reminder',
                        'total' => $userTotal['total'],
                        'quantity' => $userTotal['quantity'],
                    ]
                );
            }
        }
    }
}

