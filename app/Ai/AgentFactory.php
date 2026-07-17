<?php

namespace App\Ai;

use App\Ai\Agents\BaseCustomerServiceAgent;
use App\Ai\Agents\GenericAgent;
use App\Ai\Agents\GenericFollowUpAgent;
use App\Models\Lead;
use App\Services\AgentConfigResolver;
use App\Services\AgentTemplateService;
use InvalidArgumentException;

/**
 * Factory for creating agent instances for a lead.
 *
 * Resolution order:
 *  1. NicheTemplate.agent_class of the config's template_slug (registry override)
 *  2. Legacy niche map config('credflow.agents') — niche.modo → niche.receptivo
 *  3. GenericAgent (PromptComposer-driven runtime)
 *
 * Leads without a niche in config keep the historical INSS default; only
 * unknown/generic niches fall through to GenericAgent.
 */
class AgentFactory
{
    public function __construct(
        private readonly AgentConfigResolver $configResolver,
        private readonly AgentTemplateService $templateService,
    ) {}

    /**
     * @throws InvalidArgumentException If the resolved agent class is invalid
     */
    public function make(Lead $lead): BaseCustomerServiceAgent
    {
        $config = $this->configResolver->forLead($lead);

        $class = $this->templateAgentClass($config)
            ?? $this->legacyNicheClass($config, $lead)
            ?? GenericAgent::class;

        if (! class_exists($class)) {
            throw new InvalidArgumentException("Agent class not found: {$class}");
        }

        if (! is_subclass_of($class, BaseCustomerServiceAgent::class)) {
            throw new InvalidArgumentException(
                "Agent class '{$class}' must extend ".BaseCustomerServiceAgent::class
            );
        }

        return new $class($lead);
    }

    /**
     * Resolve the follow-up agent for a lead.
     *
     * Niches known to the legacy map keep the historical behavior (their own
     * `followup` entry, falling back to the INSS follow-up that the job used
     * to hardcode for everyone). Unknown/generic niches get the neutral
     * GenericFollowUpAgent.
     *
     * @throws InvalidArgumentException If the resolved agent class is invalid
     */
    public function makeFollowUp(Lead $lead): BaseCustomerServiceAgent
    {
        $config = $this->configResolver->forLead($lead);
        $niche = $config['agent_niche'] ?? 'inss';
        $agents = config('credflow.agents', []);

        $class = isset($agents[$niche])
            ? ($agents[$niche]['followup'] ?? $agents['inss']['followup'] ?? GenericFollowUpAgent::class)
            : GenericFollowUpAgent::class;

        if (! class_exists($class) || ! is_subclass_of($class, BaseCustomerServiceAgent::class)) {
            throw new InvalidArgumentException("Invalid follow-up agent class: {$class}");
        }

        return new $class($lead);
    }

    /**
     * Registry override: an active NicheTemplate may pin a concrete agent FQCN.
     * Invalid or missing classes fall through to the next resolution layer.
     *
     * @param  array<string, mixed>  $config
     */
    private function templateAgentClass(array $config): ?string
    {
        $slug = $config['template_slug'] ?? null;

        if (! $slug) {
            return null;
        }

        $class = $this->templateService->find((string) $slug)['agent_class'] ?? null;

        if (! $class || ! class_exists($class) || ! is_subclass_of($class, BaseCustomerServiceAgent::class)) {
            return null;
        }

        return $class;
    }

    /**
     * Legacy niche map lookup: niche.modo → niche.receptivo. A config without
     * a niche keeps the historical INSS default (pre-registry rows); an unknown
     * niche returns null so the caller falls through to GenericAgent.
     *
     * @param  array<string, mixed>  $config
     */
    private function legacyNicheClass(array $config, Lead $lead): ?string
    {
        $niche = $config['agent_niche'] ?? 'inss';
        $agents = config('credflow.agents', []);

        return $agents[$niche][$lead->modo]
            ?? $agents[$niche]['receptivo']
            ?? null;
    }
}
