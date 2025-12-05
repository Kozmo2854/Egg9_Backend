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
            ->subject('🚚 Delivery Scheduled!')
            ->greeting("Hi {$notifiable->name}!")
            ->line('Your egg delivery has been scheduled!')
            ->line("**Delivery Date:** {$deliveryDate}")
            ->line("**Delivery Time:** {$deliveryTime}")
            ->action('View Order', url('/'))
            ->line('Make sure someone is available to receive your order.');
    }

    /**
     * Get the Expo Push representation of the notification.
     */
    public function toExpoPush(object $notifiable): array
    {
        $date = $this->week->delivery_date?->format('M d') ?? 'soon';
        $time = $this->week->delivery_time ?? '';

        return [
            'title' => '🚚 Delivery Scheduled',
            'body' => "Delivery on {$date}" . ($time ? " at {$time}" : ''),
        ];
    }
}

