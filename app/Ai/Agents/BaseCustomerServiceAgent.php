<?php

namespace App\Ai\Agents;

use App\Ai\Middleware\AuditLogMiddleware;
use App\Ai\Middleware\TokenBudgetMiddleware;
use App\Ai\Middleware\ToolCallGuardMiddleware;
use App\Ai\Tools\AtualizarStatusLeadTool;
use App\Ai\Tools\EscalarParaHumanoTool;
use App\Ai\Tools\GenericWebhookTool;
use App\Ai\Tools\RegistrarLeadSemCreditoTool;
use App\Models\AgentOperationalRule;
use App\Models\Lead;
use App\Models\PromptExperiment;
use App\Models\PromptTemplate;
use App\Models\ToolDefinition;
use App\Services\AgentConfigResolver;
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

        return ToolDefinition::forTenant($tenantId)
            ->forAgent($agentId)
            ->active()
            ->where('type', 'webhook')
            ->get()
            ->map(fn ($def) => new GenericWebhookTool($def))
            ->all();
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
     * escalation, lead-registration and status tools, gated by lead status.
     * Specialized agents (bulk, follow-up) override this with their own toolset.
     */
    public function tools(): iterable
    {
        $tools = [];

        if ($consulta = $this->consultaTool()) {
            $tools[] = $consulta;
        }

        if (! in_array($this->lead->status, ['escalado', 'convertido', 'optou_sair'])) {
            $tools[] = new EscalarParaHumanoTool($this->lead);
        }

        if (! in_array($this->lead->status, ['sem_credito', 'escalado', 'convertido', 'optou_sair'])) {
            $tools[] = new RegistrarLeadSemCreditoTool($this->lead);
        }

        if (! in_array($this->lead->status, ['convertido', 'optou_sair'])) {
            $tools[] = new AtualizarStatusLeadTool($this->lead);
        }

        return $tools;
    }

    /**
     * Build the shared variable map for prompt template rendering.
     * Concrete agents may merge niche-specific keys on top.
     *
     * @param  array<string, mixed>  $cfg
     * @return array<string, mixed>
     */
    protected function buildPromptVariables(?int $userId, array $cfg): array
    {
        $rules = $userId
            ? AgentOperationalRule::forUser($userId)
            : AgentOperationalRule::forTenant($this->lead->tenant_id ?? 'default');

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
            'min_novo' => number_format((float) $rules->regra('valor_minimo_liberado_novo'), 0, ',', '.'),
            'min_refin' => number_format((float) $rules->regra('valor_minimo_liberado_refin'), 0, ',', '.'),
            'min_parcela_port' => number_format((float) $rules->regra('valor_minimo_parcela_portabilidade'), 0, ',', '.'),
            'perc_min_port' => (int) round((float) $rules->regra('percentual_minimo_pago_portabilidade') * 100),
            'idade_max' => (int) $rules->regra('idade_maxima'),
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

        $experiment = PromptExperiment::forTenant($tenantId)
            ->active()
            ->ofType($type)
            ->first();

        if ($experiment) {
            $variantSlug = $experiment->assignVariant($this->lead);
            $templateSlug = $experiment->getTemplateSlug($variantSlug);

            if ($templateSlug) {
                $template = PromptTemplate::forTenant($tenantId)
                    ->active()
                    ->where('slug', $templateSlug)
                    ->first();

                if ($template) {
                    return $template;
                }
            }
        }

        return PromptTemplate::forTenant($tenantId)
            ->forAgent($agentId)
            ->active()
            ->ofType($type)
            ->orderByRaw('agent_id IS NULL ASC')
            ->first();
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
     * Generates a concise next-action hint based on lead status and collected documents.
     * Placed at the end of the system prompt, immediately before the conversation history,
     * so the LLM sees it as the most recent instruction.
     */
    protected function buildStatusHint(): string
    {
        $docs = array_filter($this->lead->documentos_coletados ?? []);
        $docsCount = count($docs);

        return match ($this->lead->status) {
            'novo' => "\n→ PRÓXIMO PASSO: solicite o CPF se ainda não tiver.",
            'qualificado' => match (true) {
                $docsCount >= 3 => "\n→ PRÓXIMO PASSO: documentação completa — acione `escalar_para_humano`.",
                $docsCount > 0 => "\n→ PRÓXIMO PASSO: continue coletando documentos (já recebidos: {$docsCount}). Não reapresente as ofertas.",
                default => "\n→ PRÓXIMO PASSO: apresente as ofertas disponíveis e inicie a coleta de documentos.",
            },
            'sem_credito' => "\n→ PRÓXIMO PASSO: acione `registrar_lead_sem_credito` se o cliente confirmar interesse futuro.",
            'desqualificado' => "\n→ PRÓXIMO PASSO: explique que os critérios mínimos do corretor não foram atingidos. Não há produto disponível no momento.",
            'escalado' => "\n→ LEAD JÁ ESCALADO: não acione `escalar_para_humano` novamente. Aguarde o especialista assumir.",
            default => '',
        };
    }

    /**
     * Build a compact lead context block for the system prompt.
     * Ensures valor_total and valor_parcela are always present when credit data exists.
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

        if ($this->lead->credito_json) {
            $c = $this->lead->credito_json;
            $ctx .= $this->creditContextExtras($c);

            $t = $c['resumoGeral']['totais'] ?? [];
            if (! empty($t)) {
                $ctx .= $this->creditValuesBlock($c, $t);
            } elseif (! empty($c['status'])) {
                $ctx .= "\nCrédito: {$c['status']}";
            }
        }

        if ($this->lead->documentos_coletados) {
            $coletados = array_keys(array_filter($this->lead->documentos_coletados));
            if (! empty($coletados)) {
                $ctx .= "\nDocs: ".implode(', ', $coletados);
            }
        }

        return $ctx;
    }

    /**
     * Niche-specific context appended right after the identity line and before the
     * credit-values block (e.g. SIAPE órgão/matrícula/renda). Default: none.
     *
     * @param  array<string, mixed>  $c  Decoded credito_json
     */
    protected function creditContextExtras(array $c): string
    {
        return '';
    }

    /**
     * Format the "Valores (use EXATAMENTE)" credit block. Default is the INSS
     * benefício-based shape; niche agents override for their product layout.
     *
     * @param  array<string, mixed>  $c  Decoded credito_json
     * @param  array<string, mixed>  $t  resumoGeral.totais
     */
    protected function creditValuesBlock(array $c, array $t): string
    {
        $ctx = "\nValores (use EXATAMENTE):";
        if (($t['margemLivre'] ?? 0) > 0) {
            $parcela = $c['beneficios'][0]['produtos']['emprestimoNovo']['parcelaMensal'] ?? 0;
            $ctx .= " Novo=libera {$this->brl($t['margemLivre'])} (parcela {$this->brl($parcela)}/mês)";
        }
        if (($t['refinanciamento'] ?? 0) > 0) {
            $ctx .= " | Refin=troco de {$this->brl($t['refinanciamento'])} (sem parcela nova)";
        }
        if (($t['cartoes'] ?? 0) > 0) {
            $cartoes = $c['beneficios'][0]['produtos']['cartoes'] ?? [];
            $partes = [];
            foreach ($cartoes as $cartao) {
                $tipo = $cartao['tipo'] ?? 'Cartão';
                $pc = ($cartao['parcelaMensal'] ?? 0) > 0 ? " parcela {$this->brl($cartao['parcelaMensal'])}/mês" : '';
                $saque = ($cartao['valorSaque'] ?? null) ? " saque {$this->brl((float) $cartao['valorSaque'])}" : '';
                $partes[] = "{$tipo}:{$saque}{$pc}";
            }
            $cartaoStr = ! empty($partes) ? ' ('.implode(' | ', $partes).')' : '';
            $ctx .= " | Cartões={$this->brl($t['cartoes'])}{$cartaoStr}";
        }
        $ctx .= " | Total={$this->brl($t['totalEstimado'] ?? 0)}";

        return $ctx;
    }
}
