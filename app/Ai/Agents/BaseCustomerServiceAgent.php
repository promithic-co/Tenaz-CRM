<?php

namespace App\Ai\Agents;

use App\Ai\Middleware\AuditLogMiddleware;
use App\Ai\Middleware\TokenBudgetMiddleware;
use App\Ai\Middleware\ToolCallGuardMiddleware;
use App\Ai\Tools\AtualizarStatusLeadTool;
use App\Ai\Tools\EscalarParaHumanoTool;
use App\Ai\Tools\GenericWebhookTool;
use App\Ai\Tools\RegistrarInformacaoContatoTool;
use App\Ai\Tools\RegistrarLeadSemCreditoTool;
use App\Enums\AgentToolCapability;
use App\Models\Lead;
use App\Models\PromptExperiment;
use App\Models\PromptTemplate;
use App\Models\ToolDefinition;
use App\Services\AgentConfigResolver;
use App\Support\PromptLayerCache;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Laravel\Ai\Concerns\RemembersConversations;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Contracts\HasMiddleware;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Promptable;

abstract class BaseCustomerServiceAgent implements Agent, Conversational, HasMiddleware, HasTools
{
    use Promptable, RemembersConversations;

    protected ?string $modelOverride = null;

    protected ?string $providerOverride = null;

    private ?array $resolvedConfig = null;

    public function __construct(public readonly Lead $lead) {}

    public function withModelOverride(?string $provider, ?string $model): static
    {
        if ($provider !== null && $provider !== '') {
            $this->providerOverride = $provider;
        }

        if ($model !== null && $model !== '') {
            $this->modelOverride = $model;
        }

        return $this;
    }

    /** Resolve the effective user ID: authenticated user (web/playground) or tenant owner (webhook). */
    protected function resolveUserId(): ?int
    {
        $authId = Auth::id();
        if ($authId !== null) {
            return $authId;
        }

        $tenantId = $this->lead->tenant_id ?? null;

        return ($tenantId !== null && is_numeric($tenantId)) ? (int) $tenantId : null;
    }

    /**
     * Resolve the provider for this prompt. Returns a single provider string normally, or a
     * [provider => model] failover chain consumed by laravel/ai's withModelFailover() when
     * runtime failover is enabled and no explicit override is in effect.
     *
     * @return string|array<string, string|null>
     */
    public function provider(): string|array
    {
        if ($this->providerOverride) {
            return $this->providerOverride;
        }

        $primaryProvider = (string) $this->config()['agent_provider'];

        return $this->failoverChain($primaryProvider) ?? $primaryProvider;
    }

    /**
     * Build a [provider => model] failover chain for the vendor failover path, or null when
     * runtime failover is disabled, unconfigured, or would be a no-op (same provider).
     *
     * @return array<string, string|null>|null
     */
    private function failoverChain(string $primaryProvider): ?array
    {
        if (! config('credflow.agent.failover.enabled', false)) {
            return null;
        }

        $fallbackProvider = config('credflow.agent.failover.provider');
        if (! $fallbackProvider || (string) $fallbackProvider === $primaryProvider) {
            return null;
        }

        $fallbackModel = config('credflow.agent.failover.model');

        return [
            $primaryProvider => $this->model(),
            (string) $fallbackProvider => $fallbackModel ? (string) $fallbackModel : null,
        ];
    }

    public function model(): ?string
    {
        if ($this->modelOverride) {
            return $this->modelOverride;
        }

        $model = $this->config()['agent_model'] ?? null;

        return $model ? (string) $model : null;
    }

    public function temperature(): ?float
    {
        $cfg = $this->config();

        return isset($cfg['temperature']) ? (float) $cfg['temperature'] : null;
    }

    public function maxTokens(): ?int
    {
        $cfg = $this->config();

        return isset($cfg['max_tokens']) ? (int) $cfg['max_tokens'] : null;
    }

    public function middleware(): array
    {
        return [
            ToolCallGuardMiddleware::class,
            AuditLogMiddleware::class,
            TokenBudgetMiddleware::class,
        ];
    }

    protected function maxConversationMessages(): int
    {
        $cfg = $this->config();

        return isset($cfg['max_conversation_messages'])
            ? (int) $cfg['max_conversation_messages']
            : config('credflow.agent.max_conversation_messages', 24);
    }

    /**
     * @return array<string, mixed>
     */
    protected function config(): array
    {
        return $this->resolvedConfig ??= app(AgentConfigResolver::class)->forLead($this->lead);
    }

    /**
     * Load webhook tool definitions from DB for this tenant/agent.
     *
     * @return list<GenericWebhookTool>
     */
    protected function loadWebhookTools(): array
    {
        $tenantId = $this->lead->tenant_id ?? 'default';
        $agentId = $this->lead->agent_id;

        // SCALE-8: tool definitions are immutable between operator edits; cache the rows
        // (version-busted on any ToolDefinition write) and rebuild the wrappers per turn.
        $definitions = PromptLayerCache::remember(
            $tenantId,
            'webhook_tools:'.($agentId ?? '_'),
            fn () => ToolDefinition::forTenant($tenantId)
                ->forAgent($agentId)
                ->active()
                ->where('type', 'webhook')
                ->get()
        );

        return $definitions
            ->map(fn ($def) => new GenericWebhookTool($def))
            ->all();
    }

    /**
     * The native tools the operator left enabled for this agent, or null when
     * no selection was ever saved — in which case nothing is filtered out.
     *
     * @return list<string>|null
     */
    protected function enabledToolCapabilities(): ?array
    {
        $capabilities = $this->config()['tool_capabilities'] ?? null;

        if (! is_array($capabilities)) {
            return null;
        }

        return array_values(array_map(strval(...), $capabilities));
    }

    /**
     * Drop the native tools disabled for this agent in the backoffice.
     *
     * Tools with no matching capability — webhook tools, and any tool added
     * without an AgentToolCapability entry — always pass through: webhooks are
     * governed by ToolDefinition::$is_active, and a new tool must never vanish
     * from an existing toolset because nobody listed it yet.
     *
     * @param  list<object>  $tools
     * @return list<object>
     */
    protected function applyToolCapabilities(array $tools): array
    {
        $enabled = $this->enabledToolCapabilities();

        if ($enabled === null) {
            return $tools;
        }

        return array_values(array_filter($tools, function (object $tool) use ($enabled): bool {
            $capability = AgentToolCapability::fromToolClass($tool::class);

            return $capability === null || in_array($capability->value, $enabled, true);
        }));
    }

    /**
     * The niche credit-consultation tool registered first in the toolset.
     * Override in concrete agents (INSS, SIAPE, ...); null skips it.
     */
    protected function consultaTool(): ?object
    {
        return null;
    }

    /**
     * Standard customer-service toolset: niche consultation tool plus the shared
     * escalation, lead-registration and status tools, gated by lead status and
     * then by the operator's capability selection.
     * Specialized agents (bulk, follow-up) override this with their own toolset.
     */
    public function tools(): iterable
    {
        $tools = [];

        if ($consulta = $this->consultaTool()) {
            $tools[] = $consulta;
        }

        $tools[] = new RegistrarInformacaoContatoTool($this->lead);

        if (! in_array($this->lead->status, ['escalado', 'convertido', 'optou_sair'])) {
            $tools[] = new EscalarParaHumanoTool($this->lead);
        }

        if (! in_array($this->lead->status, ['sem_credito', 'escalado', 'convertido', 'optou_sair'])) {
            $tools[] = new RegistrarLeadSemCreditoTool($this->lead);
        }

        if (! in_array($this->lead->status, ['convertido', 'optou_sair'])) {
            $tools[] = new AtualizarStatusLeadTool($this->lead);
        }

        return $this->applyToolCapabilities($tools);
    }

    /**
     * Build the shared, niche-agnostic variable map for prompt template rendering.
     * Concrete agents (or the InssPromptContext concern) merge niche keys on top.
     *
     * @param  array<string, mixed>  $cfg
     * @return array<string, mixed>
     */
    protected function buildPromptVariables(?int $userId, array $cfg): array
    {
        return [
            'agent_name' => $cfg['agent_name'],
            'company_name' => $cfg['company_name'],
            'agent_personality' => $cfg['agent_personality'] ?? $cfg['personality'] ?? 'profissional',
            'max_chars' => $cfg['max_chars'],
            'agent_greeting' => $cfg['agent_greeting'] ?? $cfg['greeting'] ?? '',
            'saudacao' => $this->saudacao(),
            'local_time' => $this->localTime(),
            'required_docs' => $cfg['required_docs'] ?? '',
            'extra_rules' => $cfg['extra_rules'] ? "\n- {$cfg['extra_rules']}" : '',
        ];
    }

    /**
     * Load an active prompt template from DB.
     * If an active A/B experiment exists for this type, assigns a variant and loads its template.
     */
    protected function loadPromptTemplate(string $type): ?PromptTemplate
    {
        $tenantId = $this->lead->tenant_id ?? 'default';
        $agentId = $this->lead->agent_id;

        // SCALE-8: cache the experiment lookup and resolved templates per tenant version.
        // The per-lead variant assignment (assignVariant writes a sticky variant to the lead)
        // still runs live on the cached experiment row — only the DB reads are cached.
        $experiment = PromptLayerCache::remember(
            $tenantId,
            "experiment:{$type}",
            fn () => PromptExperiment::forTenant($tenantId)->active()->ofType($type)->first()
        );

        if ($experiment) {
            $variantSlug = $experiment->assignVariant($this->lead);
            $templateSlug = $experiment->getTemplateSlug($variantSlug);

            if ($templateSlug) {
                $template = PromptLayerCache::remember(
                    $tenantId,
                    "template_slug:{$templateSlug}",
                    fn () => PromptTemplate::forTenant($tenantId)->active()->where('slug', $templateSlug)->first()
                );

                if ($template) {
                    return $template;
                }
            }
        }

        return PromptLayerCache::remember(
            $tenantId,
            'template:'.($agentId ?? '_').":{$type}",
            fn () => PromptTemplate::forTenant($tenantId)
                ->forAgent($agentId)
                ->active()
                ->ofType($type)
                ->orderByRaw('agent_id IS NULL ASC')
                ->first()
        );
    }

    /** Format a float as BRL currency string. */
    protected function brl(float $value): string
    {
        return 'R$ '.number_format($value, 2, ',', '.');
    }

    /** Returns current time formatted as HH:MM in Brazil (São Paulo) timezone. */
    protected function localTime(): string
    {
        return Carbon::now('America/Sao_Paulo')->format('H:i');
    }

    /** Returns the correct Portuguese time-of-day greeting based on Brazil (São Paulo) time. */
    protected function saudacao(): string
    {
        $hour = Carbon::now('America/Sao_Paulo')->hour;

        return match (true) {
            $hour < 12 => 'Bom dia',
            $hour < 18 => 'Boa tarde',
            default => 'Boa noite',
        };
    }

    /**
     * Generates a concise next-action hint based on lead status.
     * Placed at the end of the system prompt, immediately before the conversation history,
     * so the LLM sees it as the most recent instruction.
     *
     * Base version covers only the platform-universal statuses; niche concerns
     * (e.g. InssPromptContext) override with pipeline-specific hints.
     */
    protected function buildStatusHint(): string
    {
        return match ($this->lead->status) {
            'escalado' => "\n→ LEAD JÁ ESCALADO: não acione `escalar_para_humano` novamente. Aguarde o especialista assumir.",
            default => '',
        };
    }

    /**
     * Build a compact lead context block for the system prompt:
     * identity, status and collected contact information, followed by the
     * niche-specific extension point (nicheLeadContext).
     */
    protected function buildLeadContext(): string
    {
        if (! $this->lead->cpf && ! $this->lead->nome) {
            return '';
        }

        $ctx = "\n## Contexto (não pergunte de novo)";
        $ctx .= "\nStatus: {$this->lead->status}";

        if ($this->lead->nome) {
            $ctx .= " | Nome: {$this->lead->nome}";
        }
        if ($this->lead->cpf) {
            $ctx .= " | CPF: {$this->lead->cpf}";
        }
        if ($this->lead->idade) {
            $ctx .= " | Idade: {$this->lead->idade}";
        }

        $ctx .= $this->nicheLeadContext();

        return $ctx;
    }

    /**
     * Niche-specific lead context appended after the shared block (e.g. INSS
     * credit values via InssPromptContext). Default: none.
     */
    protected function nicheLeadContext(): string
    {
        return '';
    }
}
