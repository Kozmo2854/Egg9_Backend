<?php

namespace App\Notifications;

use App\Notifications\Concerns\HasEmailFooter;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Password reset link, sent by the password broker through
 * User::sendPasswordResetNotification(). Not queued: the controller already
 * sends it after the response via defer().
 */
class PasswordResetNotification extends Notification
{
    use HasEmailFooter;

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
        // Same source as Laravel's stock ResetPassword notification.
        $expire = config('auth.passwords.'.config('auth.defaults.passwords').'.expire');

        $mail = (new MailMessage)
            ->subject('🔑 Reset Your Egg9 Password')
            ->greeting("Hi {$this->plainName($notifiable->name)}!")
            ->line('We got a request to reset the password for your Egg9 account. 🥚')
            ->line("This link expires in {$expire} minutes. If you didn't ask for it, no worries – just ignore this email and your password stays the same.")
            ->action('Reset Password', $this->resetUrl($notifiable))
            ->line('Thanks for being part of the Egg9 family! 🐣');

        return $this->withEmailFooter($mail);
    }

    /**
     * Laravel's documented reset link shape (token + email), pointing at the web app.
     */
    protected function resetUrl(object $notifiable): string
    {
        return rtrim(config('app.site_url'), '/').'/reset-password?'.http_build_query([
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ], '', '&', PHP_QUERY_RFC3986);
    }

    /**
     * Strip Markdown/HTML link characters from the user-chosen name so it can't
     * render as a link or tag in this security email.
     */
    protected function plainName(?string $name): string
    {
        return trim(str_replace(['[', ']', '(', ')', '<', '>', '`'], '', (string) $name));
    }
}
