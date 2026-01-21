<?php

namespace App\Notifications\Traits;

/**
 * Allows notifications to be filtered to a specific channel.
 * Used to send push notifications and emails in separate phases.
 */
trait ChannelFilterable
{
    private ?string $onlyChannel = null;

    /**
     * Limit notification to a specific channel (for phased sending)
     */
    public function onlyVia(string $channel): self
    {
        $this->onlyChannel = $channel;
        return $this;
    }

    /**
     * Get the only channel filter (if set)
     */
    protected function getOnlyChannel(): ?string
    {
        return $this->onlyChannel;
    }
}

