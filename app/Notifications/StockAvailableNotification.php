<?php

namespace App\Notifications;

use App\Channels\ExpoPushChannel;
use App\Models\Week;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class StockAvailableNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Week $week
    ) {}

    /**
     * Get the notification's delivery channels.
     */
    public function via(object $notifiable): array
    {
        $channels = ['mail'];

        if ($notifiable->pushToken) {
            $channels[] = ExpoPushChannel::class;
        }

        return $channels;
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $pricePerDozen = number_format($this->week->price_per_dozen, 0);

        return (new MailMessage)
            ->subject('🥚 Fresh Eggs Available This Week!')
            ->greeting("Hi {$notifiable->name}!")
            ->line("Great news! This week's eggs are ready for ordering.")
            ->line("**{$this->week->available_eggs} eggs** available at **{$pricePerDozen} RSD** per dozen.")
            ->action('Order Now', url('/'))
            ->line('Don\'t miss out - order while supplies last!');
    }

    /**
     * Get the Expo Push representation of the notification.
     */
    public function toExpoPush(object $notifiable): array
    {
        return [
            'title' => '🥚 Stock Available!',
            'body' => "{$this->week->available_eggs} eggs available this week!",
        ];
    }
}

