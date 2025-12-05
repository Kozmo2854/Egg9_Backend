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
        return (new MailMessage)
            ->subject('🐔 A Small Adjustment to Your Order')
            ->greeting("Hi {$notifiable->name}!")
            ->line("We need to share something with you – our chickens are going through a tough period right now and aren't producing as many eggs as usual.")
            ->line("Because of this, we had to slightly adjust your subscription order for this week:")
            ->line("**Your usual order:** {$this->originalQuantity} eggs")
            ->line("**This week's order:** {$this->newQuantity} eggs")
            ->line("We know this isn't ideal, and we really appreciate your understanding. The girls are doing their best! 🐣")
            ->line("Your subscription will automatically return to {$this->originalQuantity} eggs as soon as production picks back up.")
            ->action('View Your Order', url('/'))
            ->line('Thank you for being part of the Egg9 family!');
    }

    /**
     * Get the Expo Push representation of the notification.
     */
    public function toExpoPush(object $notifiable): array
    {
        return [
            'title' => '🐔 Order Adjusted This Week',
            'body' => "Our chickens are having a slow week! Your order was adjusted from {$this->originalQuantity} to {$this->newQuantity} eggs. Thanks for understanding! 🐣",
        ];
    }
}

