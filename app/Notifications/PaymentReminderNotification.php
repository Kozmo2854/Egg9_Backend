<?php

namespace App\Notifications;

use App\Channels\ExpoPushChannel;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PaymentReminderNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int $quantity,
        public float $total,
        public Carbon $weekStart
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
        $weekDate = $this->weekStart->format('F j, Y');

        return (new MailMessage)
            ->subject('🐔 Quick Reminder About Your Eggs!')
            ->greeting("Hi {$notifiable->name}!")
            ->line("Just a friendly nudge – we noticed your egg order hasn't been paid yet! 🥚")
            ->line("**Your order from week of {$weekDate}:**")
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
        $weekDate = $this->weekStart->format('M j');
        
        return [
            'title' => '🐔 Quick Reminder!',
            'body' => "Your order from {$weekDate} ({$this->quantity} eggs) is still unpaid 🥚",
        ];
    }
}

