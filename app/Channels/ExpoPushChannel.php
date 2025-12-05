<?php

namespace App\Channels;

use App\Models\PushToken;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ExpoPushChannel
{
    /**
     * Expo Push API endpoint
     */
    protected string $expoPushUrl = 'https://exp.host/--/api/v2/push/send';

    /**
     * Send the given notification.
     */
    public function send(object $notifiable, Notification $notification): void
    {
        // Get push token for this user
        $pushToken = $notifiable->pushToken;

        if (!$pushToken) {
            Log::info('No push token for user', ['user_id' => $notifiable->id]);
            return;
        }

        // Get the push notification data from the notification class
        if (!method_exists($notification, 'toExpoPush')) {
            Log::warning('Notification does not have toExpoPush method', [
                'notification' => get_class($notification),
            ]);
            return;
        }

        $data = $notification->toExpoPush($notifiable);

        if (empty($data)) {
            return;
        }

        $this->sendToExpo($pushToken->token, $data, $notifiable->id);
    }

    /**
     * Send notification to Expo Push API
     */
    protected function sendToExpo(string $token, array $data, int $userId): void
    {
        $message = [
            'to' => $token,
            'title' => $data['title'] ?? '',
            'body' => $data['body'] ?? '',
            'sound' => $data['sound'] ?? 'default',
        ];

        // Only add data if present (must be object, not array)
        if (!empty($data['data'])) {
            $message['data'] = (object) $data['data'];
        }

        try {
            $response = Http::post($this->expoPushUrl, $message);
            $result = $response->json();

            if ($response->successful()) {
                // Check for ticket errors (Expo returns 200 but with error in ticket)
                if (isset($result['data']['status']) && $result['data']['status'] === 'error') {
                    $this->handleTicketError($result['data'], $token, $userId);
                } else {
                    Log::info('Push notification sent successfully', [
                        'user_id' => $userId,
                        'title' => $data['title'] ?? '',
                    ]);
                }
            } else {
                Log::error('Failed to send push notification', [
                    'user_id' => $userId,
                    'status' => $response->status(),
                    'response' => $response->body(),
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Exception sending push notification', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Handle Expo ticket errors and cleanup stale tokens
     */
    protected function handleTicketError(array $ticket, string $token, int $userId): void
    {
        $errorType = $ticket['details']['error'] ?? 'unknown';

        Log::warning('Expo push ticket error', [
            'user_id' => $userId,
            'error' => $errorType,
            'message' => $ticket['message'] ?? '',
        ]);

        // Delete stale tokens
        if ($errorType === 'DeviceNotRegistered') {
            PushToken::where('token', $token)->delete();
            Log::info('Deleted stale push token', [
                'user_id' => $userId,
                'reason' => 'DeviceNotRegistered',
            ]);
        }
    }
}

