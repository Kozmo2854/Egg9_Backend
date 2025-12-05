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
            ->subject('📦 Your Eggs Have Been Delivered!')
            ->greeting("Hi {$notifiable->name}!")
            ->line('Your egg order has been delivered and is ready for pickup!')
            ->line("**Order Details:**")
            ->line("• Quantity: {$this->quantity} eggs")
            ->line("• Total: {$totalFormatted} RSD")
            ->action('View Order', url('/'))
            ->line('Thank you for ordering from Egg9!');
    }

    /**
     * Get the Expo Push representation of the notification.
     */
    public function toExpoPush(object $notifiable): array
    {
        return [
            'title' => '📦 Order Delivered!',
            'body' => "Your {$this->quantity} eggs have been delivered.",
        ];
    }
}

