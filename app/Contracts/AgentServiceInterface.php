<?php

namespace App\Contracts;

use App\Ai\DTOs\MediaContext;
use App\Models\Lead;

/**
 * Interface for agent service to enable test mocking
 * and dependency inversion.
 */
interface AgentServiceInterface
{
    /**
     * Process a message with the ARIA agent.
     * Returns the response text or null (lead opt-out).
     */
    public function process(Lead $lead, string $message, ?MediaContext $mediaContext = null, ?string $interactionId = null): ?string;
}
