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
            ->line("Our chickens have been busy this week and we've got fresh eggs waiting for you! 🥚")
            ->line("**{$this->week->available_eggs} eggs** available at **{$pricePerDozen} RSD** per dozen.")
            ->line('Don\'t miss out - order while supplies last!')
            ->action('Order Now', url('/'))
            ->line('Thank you for supporting our little farm! 🐣');
    }

    /**
     * Get the Expo Push representation of the notification.
     */
    public function toExpoPush(object $notifiable): array
    {
        return [
            'title' => '🐔 Fresh Eggs Ready!',
            'body' => "Our chickens have been busy! {$this->week->available_eggs} eggs available this week 🥚",
        ];
    }
}

