<?php

namespace App\Jobs\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

abstract class BaseNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * Backoff times in seconds for retries.
     */
    public array $backoff = [10, 60, 300];

    /**
     * Expo Push API endpoint
     */
    protected string $expoPushUrl = 'https://exp.host/--/api/v2/push/send';

    /**
     * Send push notifications to multiple tokens
     *
     * @param array $tokens Array of Expo push tokens
     * @param string $title Notification title
     * @param string $body Notification body
     * @param array $data Additional data payload
     * @return void
     */
    protected function sendBatch(array $tokens, string $title, string $body, array $data = []): void
    {
        if (empty($tokens)) {
            Log::info('No push tokens to send notifications to');
            return;
        }

        // Expo allows up to 100 tokens per request
        foreach (array_chunk($tokens, 100) as $batch) {
            $messages = array_map(fn($token) => [
                'to' => $token,
                'title' => $title,
                'body' => $body,
                'sound' => 'default',
                'data' => $data,
            ], $batch);

            try {
                $response = Http::post($this->expoPushUrl, $messages);

                if ($response->successful()) {
                    Log::info('Push notifications sent successfully', [
                        'count' => count($batch),
                        'title' => $title,
                    ]);
                } else {
                    Log::error('Failed to send push notifications', [
                        'status' => $response->status(),
                        'response' => $response->body(),
                    ]);
                }
            } catch (\Exception $e) {
                Log::error('Exception sending push notifications', [
                    'error' => $e->getMessage(),
                ]);
                throw $e; // Re-throw to trigger retry
            }
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Push notification job failed permanently', [
            'job' => static::class,
            'error' => $exception->getMessage(),
        ]);
    }
}

