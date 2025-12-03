<?php

namespace App\Jobs\Notifications;

use App\Models\PushToken;
use App\Models\Week;

class StockAvailableNotification extends BaseNotificationJob
{
    /**
     * Create a new job instance.
     */
    public function __construct(
        public Week $week
    ) {}

    /**
     * Execute the job.
     * Sends notification to ALL users when stock is available.
     */
    public function handle(): void
    {
        $tokens = PushToken::pluck('token')->toArray();

        $this->sendBatch(
            $tokens,
            '🥚 Stock Available!',
            "This week's eggs are ready! {$this->week->available_eggs} eggs available.",
            ['type' => 'stock_available', 'week_id' => $this->week->id]
        );
    }
}

