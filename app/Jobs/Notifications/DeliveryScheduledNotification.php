<?php

namespace App\Jobs\Notifications;

use App\Models\Order;
use App\Models\PushToken;
use App\Models\Week;
use Carbon\Carbon;

class DeliveryScheduledNotification extends BaseNotificationJob
{
    /**
     * Create a new job instance.
     */
    public function __construct(
        public Week $week
    ) {}

    /**
     * Execute the job.
     * Sends notification to users who have orders this week about delivery schedule.
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

        // Format delivery date nicely
        $deliveryDate = Carbon::parse($this->week->delivery_date)->format('l, F j');
        $deliveryTime = $this->week->delivery_time ?? 'TBD';

        $this->sendBatch(
            $tokens,
            '🚚 Delivery Scheduled',
            "Delivery on {$deliveryDate} at {$deliveryTime}",
            [
                'type' => 'delivery_scheduled',
                'week_id' => $this->week->id,
                'delivery_date' => $this->week->delivery_date,
                'delivery_time' => $deliveryTime,
            ]
        );
    }
}

