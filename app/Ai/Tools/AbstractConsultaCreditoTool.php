<?php

namespace App\Ai\Tools;

use App\Ai\Support\ToolResult;
use App\Models\AgentOperationalRule;
use App\Models\Lead;
use App\Services\FollowUpWindowService;
use App\Support\CpfValidator;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;
use Throwable;

/**
 * Shared orchestration for credit-consultation tools (INSS, SIAPE, ...).
 *
 * Subclasses describe only what differs between credit niches: the labels shown
 * to the agent, the circuit-breaker slug, the webhook config key, the
 * qualification service, and the niche-specific payload formatting.
 */
abstract class AbstractConsultaCreditoTool implements Tool
{
    public function __construct(protected readonly Lead $lead) {}

    /**
     * Uppercase niche label used in agent-facing messages (e.g. "INSS", "SIAPE").
     */
    abstract protected function nicheLabel(): string;

    /**
     * Word for the person being served, used in CPF-correction prompts
     * (e.g. "cliente", "servidor").
     */
    abstract protected function audienceWord(): string;

    /**
     * Slug for the circuit-breaker cache key: "circuit_breaker_{slug}_{tenant}".
     */
    abstract protected function circuitSlug(): string;

    /**
     * Config key holding the n8n consultation webhook URL.
     */
    abstract protected function webhookConfigKey(): string;

    /**
     * Env var name surfaced in the "not configured" log hint.
     */
    abstract protected function webhookEnvHint(): string;

    /**
     * Registered tool name, echoed in retry hints (e.g. "consultar_credito_inss").
     */
    abstract protected function toolName(): string;

    /**
     * Qualify a raw Promosys payload into the ARIA credit schema using the
     * niche-specific qualification service.
     *
     * @param  array<string, mixed>  $rawData
     * @param  Collection<int, AgentOperationalRule>  $rules
     * @return array<string, mixed>
     */
    abstract protected function qualify(array $rawData, $rules): array;

    /**
     * Format the ARIA credit schema as the exact text handed to the agent.
     *
     * Static so the Laboratory can replay "payload enviado ao agente".
     *
     * @param  array<string, mixed>  $data
     */
    abstract public static function formatPayloadForAgent(array $data): string;

    public function handle(Request $request): Stringable|string
    {
        $cpf = preg_replace('/\D/', '', $request['cpf']);
        $niche = $this->nicheLabel();
        $audience = $this->audienceWord();

        if (strlen($cpf) !== 11) {
            return ToolResult::blocked("CPF inválido: deve conter exatamente 11 dígitos. Peça ao {$audience} para reenviar.");
        }

        if (! CpfValidator::isValid($cpf)) {
            return ToolResult::blocked("CPF inválido (dígitos verificadores incorretos). Peça ao {$audience} para conferir e reenviar.");
        }

        // CPF já consultado para este lead — retornar cache sem chamar o webhook novamente
        if ($this->lead->cpf === $cpf && $this->lead->credito_json) {
            return ToolResult::success(static::formatPayloadForAgent($this->lead->credito_json));
        }

        $circuitKey = "circuit_breaker_{$this->circuitSlug()}_{$this->lead->tenant_id}";
        if ($this->circuitState($circuitKey) === 'open') {
            Log::warning('aria.tool.consulta_circuit_breaker_open', ['niche' => $niche, 'lead_id' => $this->lead->id]);

            return ToolResult::error(
                "Sistema {$niche} temporariamente indisponível (circuit breaker ativo).",
                'Não tente chamar esta ferramenta novamente neste turno. Conduza a conversa sem valores precisos e ofereça acionar escalar_para_humano se o cliente precisar de proposta agora.'
            );
        }

        $webhookUrl = config($this->webhookConfigKey());
        if (empty($webhookUrl)) {
            Log::error('aria.tool.consulta_config_missing', [
                'niche' => $niche,
                'lead_id' => $this->lead->id,
                'hint' => "Defina {$this->webhookEnvHint()} no .env com a URL do webhook n8n",
            ]);

            return ToolResult::error(
                "Sistema de consulta {$niche} não configurado.",
                "Informe ao {$audience} que há uma instabilidade técnica e acione escalar_para_humano com motivo problema_tecnico."
            );
        }

        try {
            // timeout reduzido para evitar derrubar os workers PHP aguardando n8n
            $response = Http::timeout(15)->post($webhookUrl, ['cpf' => $cpf]);

            if (! $response->successful()) {
                $this->incrementCircuitBreaker($circuitKey);

                Log::warning('aria.tool.consulta_http_error', [
                    'niche' => $niche,
                    'lead_id' => $this->lead->id,
                    'status' => $response->status(),
                ]);

                return ToolResult::error(
                    "Consulta {$niche} retornou erro HTTP ".$response->status().'.',
                    "Tente chamar {$this->toolName()} mais uma vez. Se falhar novamente, acione escalar_para_humano com motivo problema_tecnico."
                );
            }

            // Sucesso fecha o circuito (limpa contador, cooldown e probe half-open)
            $this->closeCircuit($circuitKey);

            $rawData = $response->json();

            // Webhook pode retornar array wrapper [{...}] ou objeto {...}
            if (isset($rawData[0]) && is_array($rawData[0])) {
                $rawData = $rawData[0];
            }

            // Carregar regras operacionais: preferência ao user autenticado, fallback ao tenant_id do lead
            $userId = Auth::id();
            $rules = $userId
                ? AgentOperationalRule::forUser($userId)
                : AgentOperationalRule::forTenant($this->lead->tenant_id ?? 'default');

            // Se já vem no formato ARIA (processado pelo n8n), usar direto.
            // Se vem bruto da Promosys, qualificar com as regras do corretor.
            $data = isset($rawData['status'], $rawData['resumoGeral'])
                ? $rawData
                : $this->qualify($rawData, $rules);

            $newStatus = match ($data['status'] ?? '') {
                'QUALIFICADO' => 'qualificado',
                'SEM_CREDITO' => 'sem_credito',
                'DESQUALIFICADO' => 'desqualificado',
                default => $this->lead->status,
            };

            $cliente = $data['cliente'] ?? [];
            $updateData = [
                'cpf' => $cpf,
                'nome' => $cliente['nome'] ?? $this->lead->nome,
                'idade' => $cliente['idade'] ?? $this->lead->idade,
                'credito_json' => $data,
                'status' => $newStatus,
                'last_interaction_at' => now(),
            ];

            // Ativar follow-up automático para leads qualificados — combinado em um único UPDATE
            if ($newStatus === 'qualificado') {
                $updateData['followup_status'] = app(FollowUpWindowService::class)
                    ->canSendFreeFormMessage($this->lead) ? 'active' : 'inactive';
                $updateData['followup_count'] = 0;
            }

            $this->lead->update($updateData);

            return ToolResult::success(static::formatPayloadForAgent($data));

        } catch (Throwable $e) {
            $this->incrementCircuitBreaker($circuitKey);

            Log::error('aria.tool.consulta_erro', [
                'niche' => $niche,
                'lead_id' => $this->lead->id,
                'cpf' => substr($cpf, 0, 3).'***',
                'error' => $e->getMessage(),
            ]);

            return ToolResult::error(
                "Consulta {$niche} falhou por instabilidade ou timeout.",
                "Tente chamar {$this->toolName()} mais uma vez. Se falhar novamente, acione escalar_para_humano com motivo problema_tecnico."
            );
        }
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'cpf' => $schema->string()
                ->description('CPF com 11 dígitos')
                ->required(),
        ];
    }

    /**
     * Resolve the breaker state for this request: 'closed' (let it through),
     * 'open' (fast-fail), or 'half_open' (this request is the single probe allowed
     * after the cooldown elapsed).
     */
    protected function circuitState(string $circuitKey): string
    {
        $threshold = (int) config('credflow.circuit_breaker.consultas_falhas_threshold', 5);

        if ((int) Cache::get($circuitKey, 0) < $threshold) {
            return 'closed';
        }

        // Threshold reached. While the cooldown key is alive the circuit is fully open.
        if (Cache::get("{$circuitKey}_open") !== null) {
            return 'open';
        }

        // Cooldown elapsed → let exactly one request through as a half-open probe.
        // Cache::add is atomic (SETNX): only the first concurrent caller wins.
        return Cache::add("{$circuitKey}_probe", 1, now()->addSeconds(30)) ? 'half_open' : 'open';
    }

    /**
     * Atomically register a failure. Cache::add (SETNX) seeds the counter with its
     * window TTL exactly once — concurrent callers can't reset the window — then
     * Cache::increment bumps it atomically. Crossing the threshold (re-)opens the
     * circuit with a per-tenant jittered cooldown to avoid synchronized retries.
     */
    protected function incrementCircuitBreaker(string $circuitKey): void
    {
        $threshold = (int) config('credflow.circuit_breaker.consultas_falhas_threshold', 5);
        $windowMinutes = (int) config('credflow.circuit_breaker.window_minutes', 5);

        Cache::add($circuitKey, 0, now()->addMinutes($windowMinutes));
        $count = (int) Cache::increment($circuitKey);

        // A failed half-open probe must re-open, so drop the spent probe claim.
        Cache::forget("{$circuitKey}_probe");

        if ($count >= $threshold) {
            $jitterSeconds = random_int(0, 30);
            Cache::put("{$circuitKey}_open", 1, now()->addMinutes($windowMinutes)->addSeconds($jitterSeconds));
        }
    }

    /**
     * Close the circuit: clear the failure counter, the cooldown gate, and any
     * outstanding half-open probe claim.
     */
    protected function closeCircuit(string $circuitKey): void
    {
        Cache::forget($circuitKey);
        Cache::forget("{$circuitKey}_open");
        Cache::forget("{$circuitKey}_probe");
    }

    protected static function brl(float $value): string
    {
        return 'R$ '.number_format($value, 2, ',', '.');
    }
}
