<?php

namespace App\Notifications\Concerns;

use Illuminate\Notifications\Messages\MailMessage;

/**
 * Appends a shared, branded footer line to a notification email.
 *
 * The footer renders the public site URL (config('app.site_url')) so the
 * link can be swapped via the SITE_URL env var — never hardcoded per template.
 */
trait HasEmailFooter
{
    /**
     * Append the shared Egg9 footer line to the given MailMessage.
     */
    protected function withEmailFooter(MailMessage $mail): MailMessage
    {
        $siteUrl = config('app.site_url');

        return $mail->line("Egg9 · [{$siteUrl}]({$siteUrl})");
    }
}
