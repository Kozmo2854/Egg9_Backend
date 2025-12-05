<?php

namespace App\Notifications;

use App\Channels\ExpoPushChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SubscriptionTrimmedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int $originalQuantity,
        public int $newQuantity
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
        $reduction = $this->originalQuantity - $this->newQuantity;

        return (new MailMessage)
            ->subject('⚠️ Subscription Adjusted - Egg9')
            ->greeting("Hi {$notifiable->name}!")
            ->line('Due to limited stock this week, we had to adjust your subscription order.')
            ->line("**Original quantity:** {$this->originalQuantity} eggs")
            ->line("**Adjusted quantity:** {$this->newQuantity} eggs")
            ->line("**Reduction:** {$reduction} eggs")
            ->line('Your subscription will return to normal quantity when stock allows.')
            ->action('View Order', url('/'))
            ->line('We apologize for any inconvenience. Our chickens are working hard!');
    }

    /**
     * Get the Expo Push representation of the notification.
     */
    public function toExpoPush(object $notifiable): array
    {
        $reduction = $this->originalQuantity - $this->newQuantity;

        return [
            'title' => '⚠️ Subscription Adjusted',
            'body' => "Due to limited stock, your subscription was reduced from {$this->originalQuantity} to {$this->newQuantity} eggs this week ({$reduction} eggs less).",
        ];
    }
}

