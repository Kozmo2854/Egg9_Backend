<?php

namespace App\Notifications;

use App\Notifications\Concerns\HasEmailFooter;
use App\Notifications\Traits\ChannelFilterable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Password reset link, sent by the password broker through
 * User::sendPasswordResetNotification(). Not queued: the controller already
 * sends it after the response via defer().
 */
class PasswordResetNotification extends Notification
{
    use ChannelFilterable, HasEmailFooter;

    public function __construct(
        public string $token
    ) {}

    /**
     * Get the notification's delivery channels.
     *
     * Mail only, regardless of the user's email notification preference:
     * this is account recovery, not a marketing/update message.
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject('🔑 Reset Your Egg9 Password')
            ->greeting("Hi {$notifiable->name}!")
            ->line('We got a request to reset the password for your Egg9 account. 🥚')
            ->line("This link expires in 60 minutes. If you didn't ask for it, no worries – just ignore this email and your password stays the same.")
            ->action('Reset Password', $this->resetUrl($notifiable))
            ->line('Thanks for being part of the Egg9 family! 🐣');

        return $this->withEmailFooter($mail);
    }

    /**
     * Laravel's documented reset link shape (token + email), pointing at the web app.
     */
    protected function resetUrl(object $notifiable): string
    {
        return rtrim(config('app.site_url'), '/')
            .'/reset-password?token='.$this->token
            .'&email='.urlencode($notifiable->getEmailForPasswordReset());
    }
}
