<?php

namespace App\Jobs\Notifications;

use App\Models\Order;
use App\Models\PushToken;
use App\Models\Week;

class OrderDeliveredNotification extends BaseNotificationJob
{
    /**
     * Create a new job instance.
     */
    public function __construct(
        public Week $week
    ) {}

    /**
     * Execute the job.
     * Sends notification to users who have orders this week.
     */
    public function handle(): void
    {
        // Get user IDs who have orders this week
        $userIds = Order::where('week_id', $this->week->id)
            ->pluck('user_id')
            ->unique()
            ->toArray();

        $tokens = PushToken::whereIn('user_id', $userIds)
            ->pluck('token')
            ->toArray();

        $this->sendBatch(
            $tokens,
            '📦 Order Delivered!',
            'Your eggs have been delivered. Please pick them up!',
            ['type' => 'order_delivered', 'week_id' => $this->week->id]
        );
    }
}

