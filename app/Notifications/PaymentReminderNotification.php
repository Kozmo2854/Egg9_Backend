<?php

namespace App\Notifications;

use App\Channels\ExpoPushChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PaymentReminderNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
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
            ->subject('🐔 Quick Reminder About Your Eggs!')
            ->greeting("Hi {$notifiable->name}!")
            ->line("Just a friendly nudge – we noticed your egg order hasn't been paid yet! 🥚")
            ->line("**Your order:**")
            ->line("• {$this->quantity} eggs")
            ->line("• **{$totalFormatted} RSD**")
            ->line("No rush, but our chickens would appreciate it when you get a chance! 😊")
            ->action('Complete Payment', url('/'))
            ->line('Thanks for being part of the Egg9 family! 🐣');
    }

    /**
     * Get the Expo Push representation of the notification.
     */
    public function toExpoPush(object $notifiable): array
    {
        return [
            'title' => '🐔 Quick Reminder!',
            'body' => "Don't forget about your eggs! Payment still pending 🥚",
        ];
    }
}

