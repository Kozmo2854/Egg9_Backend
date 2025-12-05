<?php

namespace App\Notifications;

use App\Channels\ExpoPushChannel;
use App\Models\Week;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OrderDeliveredNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Week $week,
        public int $quantity,
        public float $total
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
        $totalFormatted = number_format($this->total, 0);

        return (new MailMessage)
            ->subject('🐔 Your Eggs Have Arrived!')
            ->greeting("Hi {$notifiable->name}!")
            ->line("Great news – your eggs have made it safely to you! 🥚")
            ->line("**Your order:**")
            ->line("• {$this->quantity} fresh eggs")
            ->line("• Total: {$totalFormatted} RSD")
            ->line("They're ready and waiting for you to pick them up!")
            ->action('View Your Order', url('/'))
            ->line('Thank you for being part of the Egg9 family! 🐣');
    }

    /**
     * Get the Expo Push representation of the notification.
     */
    public function toExpoPush(object $notifiable): array
    {
        return [
            'title' => '🐔 Your Eggs Have Arrived!',
            'body' => "Your {$this->quantity} eggs are ready for pickup! 🥚",
        ];
    }
}

