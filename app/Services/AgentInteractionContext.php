<?php

namespace App\Services;

class AgentInteractionContext
{
    /** @var array<string, mixed> */
    private array $context = [];

    /**
     * @param  array<string, mixed>  $context
     */
    public function set(array $context): void
    {
        $this->context = $context;
    }

    public function clear(): void
    {
        $this->context = [];
    }

    public function interactionId(): ?string
    {
        $value = $this->context['interaction_id'] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return $this->context;
    }
}
