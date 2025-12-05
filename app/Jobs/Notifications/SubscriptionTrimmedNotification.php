<?php

namespace App\Jobs\Notifications;

use App\Models\PushToken;
use Illuminate\Support\Facades\Log;

class SubscriptionTrimmedNotification extends BaseNotificationJob
{
    public function __construct(
        public int $userId,
        public int $originalQuantity,
        public int $newQuantity
    ) {}

    public function handle(): void
    {
        Log::info('Dispatching subscription trimmed notification', [
            'user_id' => $this->userId,
            'original' => $this->originalQuantity,
            'new' => $this->newQuantity,
        ]);

        $pushToken = PushToken::where('user_id', $this->userId)->first();

        if (!$pushToken) {
            Log::info('No push token found for user', ['user_id' => $this->userId]);
            return;
        }

        $reduction = $this->originalQuantity - $this->newQuantity;

        $this->sendBatch(
            [$pushToken->token],
            '⚠️ Subscription Adjusted',
            "Due to limited stock, your subscription was reduced from {$this->originalQuantity} to {$this->newQuantity} eggs this week ({$reduction} eggs less)."
        );
    }
}

