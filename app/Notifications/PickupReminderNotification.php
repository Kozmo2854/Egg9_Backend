<?php

namespace App\Notifications;

use App\Channels\ExpoPushChannel;
use App\Notifications\Concerns\HasEmailFooter;
use App\Notifications\Traits\ChannelFilterable;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

class PickupReminderNotification extends Notification implements ShouldQueue
{
    use ChannelFilterable, HasEmailFooter, Queueable;

    public int $quantity;

    public Carbon $weekStart;

    public int $orderId;

    /**
     * Create a new notification instance.
     */
    public function __construct(int $quantity, Carbon $weekStart, int $orderId)
    {
        $this->quantity = $quantity;
        $this->weekStart = $weekStart;
        $this->orderId = $orderId;
    }

    /**
     * Get the notification's delivery channels.
     */
    public function via(object $notifiable): array
    {
        if ($this->getOnlyChannel()) {
            return [$this->getOnlyChannel()];
        }

        $channels = [];

        if ($notifiable->push_notifications_enabled && $notifiable->pushToken) {
            $channels[] = ExpoPushChannel::class;
        }

        if ($notifiable->email_notifications_enabled) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $confirmUrl = URL::temporarySignedRoute(
            'orders.confirm-pickup-email',
            now()->addDays(14),
            ['order' => $this->orderId]
        );

        $mail = (new MailMessage)
            ->subject('🥚 Your Eggs Are Waiting!')
            ->greeting("Hey {$notifiable->name}!")
            ->line("Your eggs from the week of {$this->weekStart->format('F j')} are still waiting for you to pick them up!")
            ->line("**{$this->quantity} fresh eggs** are ready and waiting. 🐔")
            ->line("Don't leave them lonely – they miss you already!")
            ->action('I picked up my eggs — confirm', $confirmUrl)
            ->salutation("Cluck cluck! 🐣\nYour friends at Egg9");

        return $this->withEmailFooter($mail);
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
