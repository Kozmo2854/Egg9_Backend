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

    /** Customer has an active subscription that is paused for the skipped week. */
    public const VARIANT_SUBSCRIPTION = 'subscription';

    /** Customer had a one-time order this week that the skip cancels. */
    public const VARIANT_ORDER_CANCELLED = 'order_cancelled';

    /** Customer has neither a subscription nor an order this week. */
    public const VARIANT_NONE = 'none';

    /**
     * @param  string  $variant  One of the VARIANT_* constants — selects the copy.
     * @param  int  $weeksRemaining  Weeks left on the active subscription (subscription variant only).
     */
    public function __construct(
        public string $variant = self::VARIANT_NONE,
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
     * Push notification title (shared by all variants).
     */
    protected function title(): string
    {
        return '🐔 This Week Is Skipped';
    }

    /**
     * The single-line body copy for this variant.
     *
     * Copy is intentionally kept together here so it is easy to edit in one place.
     */
    protected function body(): string
    {
        return match ($this->variant) {
            self::VARIANT_SUBSCRIPTION => "This week's delivery is skipped. Your subscription is paused for this week — you still have {$this->weeksRemaining} week(s) left.",
            self::VARIANT_ORDER_CANCELLED => "This week's delivery is skipped, so your order for this week has been cancelled — we'll be back next week.",
            default => "This week's delivery is skipped — we'll be back next week.",
        };
    }

    /**
     * Get the mail representation of the notification.
     *
     * NOTE: Email delivery for this notification isn't wired into the customer flow yet.
     * This is a stub that mirrors the push copy so it can be enabled later (Brevo).
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('🐔 This Week\'s Delivery Is Skipped')
            ->greeting("Hi {$notifiable->name}!")
            ->line($this->body())
            ->line('Thank you for being part of the Egg9 family!');
    }

    /**
     * Get the Expo Push representation of the notification.
     */
    public function toExpoPush(object $notifiable): array
    {
        return [
            'title' => $this->title(),
            'body' => $this->body(),
        ];
    }
}
