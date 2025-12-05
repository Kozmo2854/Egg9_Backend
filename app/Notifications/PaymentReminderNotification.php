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
            ->subject('💸 Payment Reminder - Egg9')
            ->greeting("Hi {$notifiable->name}!")
            ->line('This is a friendly reminder that you have an unpaid egg order.')
            ->line("**Order Details:**")
            ->line("• Quantity: {$this->quantity} eggs")
            ->line("• Amount Due: {$totalFormatted} RSD")
            ->action('Pay Now', url('/'))
            ->line('Please settle your payment at your earliest convenience.');
    }

    /**
     * Get the Expo Push representation of the notification.
     */
    public function toExpoPush(object $notifiable): array
    {
        return [
            'title' => '💸 Payment Reminder',
            'body' => 'You have unpaid orders. Please settle your payment soon!',
        ];
    }
}

