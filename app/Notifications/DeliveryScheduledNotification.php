<?php

namespace App\Notifications;

use App\Channels\ExpoPushChannel;
use App\Models\Week;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class DeliveryScheduledNotification extends Notification implements ShouldQueue
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
        $deliveryDate = $this->week->delivery_date?->format('l, F j, Y') ?? 'TBD';
        $deliveryTime = $this->week->delivery_time ?? 'TBD';

        return (new MailMessage)
            ->subject('🐔 Your Eggs Are On Their Way!')
            ->greeting("Hi {$notifiable->name}!")
            ->line("We're getting your eggs ready for delivery! 🥚")
            ->line("**When to expect them:**")
            ->line("📅 **{$deliveryDate}**")
            ->line("🕐 **{$deliveryTime}**")
            ->line("Make sure someone's around to welcome them!")
            ->action('View Your Order', url('/'))
            ->line('See you soon! 🐣');
    }

    /**
     * Get the Expo Push representation of the notification.
     */
    public function toExpoPush(object $notifiable): array
    {
        $date = $this->week->delivery_date?->format('M d') ?? 'soon';
        $time = $this->week->delivery_time ?? '';

        return [
            'title' => '🐔 Delivery Coming!',
            'body' => "Your eggs are on their way! Arriving {$date}" . ($time ? " at {$time}" : '') . " 🥚",
        ];
    }
}

