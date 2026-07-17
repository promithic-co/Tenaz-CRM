<?php

namespace App\Exceptions;

use RuntimeException;

final class CampaignConfigurationException extends RuntimeException
{
    /** @param list<string> $violations */
    public function __construct(public readonly array $violations)
    {
        parent::__construct('Campaign send configuration is invalid: '.implode(', ', $violations));
    }

    public function primaryViolation(): string
    {
        return $this->violations[0] ?? 'CAMPAIGN_SEND_CONFIGURATION_INVALID';
    }
}
