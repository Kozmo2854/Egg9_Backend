<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Carbon\Carbon;

class PickupReminderNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public int $quantity;
    public Carbon $weekStart;

    /**
     * Create a new notification instance.
     */
    public function __construct(int $quantity, Carbon $weekStart)
    {
        $this->quantity = $quantity;
        $this->weekStart = $weekStart;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        $channels = [];
        
        if ($notifiable->email_notifications_enabled) {
            $channels[] = 'mail';
        }
        
        if ($notifiable->push_notifications_enabled && $notifiable->pushToken) {
            $channels[] = \App\Channels\ExpoPushChannel::class;
        }
        
        return $channels;
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('🥚 Your Eggs Are Waiting!')
            ->greeting("Hey {$notifiable->name}!")
            ->line("Your eggs from the week of {$this->weekStart->format('F j')} are still waiting for you to pick them up!")
            ->line("**{$this->quantity} fresh eggs** are ready and waiting. 🐔")
            ->line("Don't leave them lonely – they miss you already!")
            ->salutation("Cluck cluck! 🐣\nYour friends at Egg9");
    }

    /**
     * Get the Expo Push notification representation.
     */
    public function toExpoPush(object $notifiable): array
    {
        return [
            'title' => '🥚 Your Eggs Miss You!',
            'body' => "{$this->quantity} eggs from {$this->weekStart->format('M d')} are waiting for pickup!",
            'data' => ['type' => 'pickup_reminder'],
        ];
    }
}

