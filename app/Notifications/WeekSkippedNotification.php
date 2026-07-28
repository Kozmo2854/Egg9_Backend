<?php

namespace App\Notifications;

use App\Channels\ExpoPushChannel;
use App\Notifications\Traits\ChannelFilterable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class WeekSkippedNotification extends Notification implements ShouldQueue
{
    use ChannelFilterable, Queueable;

    /**
     * @param  int  $weeksRemaining  Weeks left on the affected subscription (0 for one-time customers)
     */
    public function __construct(
        public int $weeksRemaining = 0
    ) {}

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
     *
     * NOTE: Email delivery for this notification isn't wired into the customer flow yet.
     * This is a stub that mirrors the copy used for push so it can be enabled later.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $message = (new MailMessage)
            ->subject('🐔 This Week\'s Delivery Is Skipped')
            ->greeting("Hi {$notifiable->name}!")
            ->line("This week's delivery is skipped.");

        if ($this->weeksRemaining > 0) {
            $message->line("Your subscription is paused for this week — you still have {$this->weeksRemaining} week(s) left.");
        }

        return $message
            ->line('Nothing is lost — your subscription simply resumes on the next cycle.')
            ->line('Thank you for being part of the Egg9 family!');
    }

    /**
     * Get the Expo Push representation of the notification.
     */
    public function toExpoPush(object $notifiable): array
    {
        $body = $this->weeksRemaining > 0
            ? "This week's delivery is skipped. Your subscription is paused for this week — you still have {$this->weeksRemaining} week(s) left."
            : "This week's delivery is skipped.";

        return [
            'title' => '🐔 This Week Is Skipped',
            'body' => $body,
        ];
    }
}
