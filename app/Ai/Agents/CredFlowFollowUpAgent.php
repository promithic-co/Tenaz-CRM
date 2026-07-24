<?php

namespace App\Ai\Agents;

use App\Ai\Agents\Concerns\InssPromptContext;
use App\Ai\Middleware\AuditLogMiddleware;
use App\Ai\Middleware\ToolCallGuardMiddleware;
use App\Ai\Tools\AtualizarStatusLeadTool;
use App\Ai\Tools\ConsultarCreditoInssTool;
use App\Ai\Tools\EscalarParaHumanoTool;
use App\Services\AgentService;
use App\Services\FollowUpSettingsResolver;
use App\Services\FollowUpWindowService;
use Carbon\Carbon;
use Stringable;

class CredFlowFollowUpAgent extends BaseCustomerServiceAgent
{
    use InssPromptContext;

    /**
     * Follow-up parameters come from FollowUpSettingsResolver — the same source the
     * engine (CheckFollowUpsCommand / FollowUpWindowService) uses — so the prompt's
     * attempt counter and is_last/farewell logic can never diverge from what the
     * engine enforces. Legacy AgentConfig supplies only UI-era fields (agent_name)
     * that have no resolver equivalent; the `{approach}` template placeholder is now
     * derived from the resolver tone (approachFromTone) instead of a dead config column.
     */
    public function instructions(): Stringable|string
    {
        $cfg = $this->config();
        $settings = app(FollowUpSettingsResolver::class)->forLead($this->lead);
        $maxCount = (int) ($settings['max_attempts_within_window'] ?? 2);
        $currentCount = (int) ($this->lead->followup_count ?? 0);
        $attemptNumber = $currentCount + 1;
        $isLast = $currentCount >= ($maxCount - 1);
        $messageType = (string) ($settings['message_type'] ?? 'contextual');
        $tone = (string) ($settings['tone'] ?? 'consultivo');
        $persuasionIntensity = (int) ($settings['persuasion_intensity'] ?? 2);
        $customInstructions = trim((string) ($settings['custom_instructions'] ?? ''));
        $toneByAttempt = $this->toneByAttempt($currentCount, $isLast, $tone, $persuasionIntensity);
        $windowHint = $this->buildCustomerServiceWindowHint();

        // Try loading prompt template from DB
        $template = $this->loadPromptTemplate('followup');
        if ($template) {
            $rendered = $template->render([
                'agent_name' => $cfg['agent_name'] ?? 'Tenaz CRM',
                'attempt_number' => $attemptNumber,
                'max_count' => $maxCount,
                'approach' => $this->approachFromTone($tone),
                'tone_by_attempt' => $toneByAttempt,
                'message_type' => $messageType,
                'tone' => $tone,
                'persuasion_intensity' => $persuasionIntensity,
                'custom_instructions' => $customInstructions,
                'is_last' => $isLast ? 'sim' : 'não',
                'autonomous_hint' => $this->buildAutonomousHint($isLast),
                'customer_service_window' => $windowHint,
            ]);

            return $rendered
                .$this->buildLeadContext()
                .$windowHint
                .$this->buildCustomizationHint($messageType, $tone, $persuasionIntensity, $customInstructions, $toneByAttempt)
                .$this->buildFollowUpStateHint($isLast, $maxCount, $currentCount);
        }

        $agentName = $cfg['agent_name'] ?? 'Tenaz CRM';

        return <<<PROMPT
        Você é {$agentName} — agente autônoma de reengajamento de crédito consignado INSS.

        ## MISSÃO
        Você gerencia o ciclo completo de follow-up deste lead de forma autônoma.
        Tentativa {$attemptNumber} de {$maxCount}.
        {$windowHint}

        ## DECISÃO AUTÔNOMA — EXECUTE A AÇÃO CORRETA
        Analise o Contexto abaixo e o histórico da conversa, então decida:

        1. **Cliente respondeu com intenção de compra no histórico recente** → acione `escalar_para_humano` com motivo `solicitacao_cliente` e resumo da situação. Depois responda confirmando ao cliente.

        2. **Cliente recusou explicitamente** ("não quero", "me tira", "para", "bloqueia") → acione `atualizar_status_lead` com `optou_sair`. Depois responda SOMENTE: {$this->getSentinel()}

        3. **Dados de crédito desatualizados (mais de 30 dias) e CPF disponível** → acione `consultar_credito_inss` para atualizar. Use os valores atualizados na mensagem de follow-up.

        4. **Esta é a última tentativa** → gere mensagem de despedida respeitosa, deixando a porta aberta para contato futuro. Não pressione.

        5. **Caso padrão** → gere uma mensagem de recontato natural, curta, contextualizada com o que já aconteceu na conversa.

        ## REGRAS DA MENSAGEM
        - Tipo de mensagem: {$messageType}.
        - Tom de voz: {$tone}.
        - Intensidade de persuasao: {$persuasionIntensity}/5.
        - Diretriz desta tentativa: {$toneByAttempt}.
        {$this->formatCustomInstructions($customInstructions)}
        - Máx 150 chars.
        - Texto puro — sem markdown, asteriscos ou formatação.
        - Não repita o texto de tentativas anteriores (verifique o histórico).
        - Se o Contexto contiver crédito disponível, mencione o valor concretamente: "Seu crédito de R$ X ainda está disponível."
        - NUNCA invente valores — use apenas os do Contexto ou os retornados por `consultar_credito_inss`.

        ## FERRAMENTAS — RESULTADOS EM JSON
        Resultados retornam `status` + `message` (+ `hint` opcional):
        - `success` → prossiga conforme planejado
        - `error` + `hint` → siga exatamente o `hint`
        - `already_done` → não repita a ação
        - `blocked` → ajuste a estratégia sem tentar novamente

        {$this->buildCampaignFollowUpContext()}{$this->buildLeadContext()}{$this->buildFollowUpStateHint($isLast, $maxCount, $currentCount)}
        → Horário atual (Brasília): {$this->saudacao()} ({$this->localTime()})
        PROMPT;
    }

    public function tools(): iterable
    {
        $tools = [];

        // Permite consultar crédito atualizado antes de mencionar valores no follow-up
        $tools[] = new ConsultarCreditoInssTool($this->lead);

        // Permite escalar se cliente responder com intenção durante o follow-up
        if (! in_array($this->lead->status, ['escalado', 'convertido', 'optou_sair'])) {
            $tools[] = new EscalarParaHumanoTool($this->lead);
        }

        // Permite registrar opt-out quando cliente recusa durante o follow-up
        if (! in_array($this->lead->status, ['convertido', 'optou_sair'])) {
            $tools[] = new AtualizarStatusLeadTool($this->lead);
        }

        return $this->applyToolCapabilities($tools);
    }

    public function middleware(): array
    {
        return [
            ToolCallGuardMiddleware::class,
            AuditLogMiddleware::class,
        ];
    }

    protected function maxConversationMessages(): int
    {
        return 20;
    }

    private function buildCustomerServiceWindowHint(): string
    {
        $window = app(FollowUpWindowService::class);
        // Free-form window = customer-service (24h) OR free entry point (72h, F7) —
        // whichever closes later. Covers FEP-only leads that never wrote.
        $closesAt = $window->freeFormWindowClosesAt($this->lead);
        $remainingMinutes = $window->freeFormRemainingMinutes($this->lead);

        if (! $closesAt) {
            return "\n[Janela WhatsApp: nao ha ultima mensagem do cliente registrada. Nao tente reabrir conversa fora da janela.]";
        }

        return "\n[Janela WhatsApp: restam aproximadamente {$remainingMinutes} minutos para mensagem livre. Limite da janela: {$closesAt->copy()->setTimezone('America/Sao_Paulo')->format('d/m H:i')} BRT. Use uma continuidade natural da negociacao em andamento.]";
    }

    /**
     * Inject a state-aware hint at the end of the system prompt.
     * Considers attempt number, credit data freshness, and days since last interaction.
     */
    private function buildFollowUpStateHint(bool $isLast, int $maxCount, int $currentCount): string
    {
        $hints = [];

        if ($isLast) {
            $hints[] = "→ ÚLTIMA TENTATIVA ({$currentCount}+1/{$maxCount}): encerre com despedida respeitosa.";
        } elseif ($currentCount === 0) {
            $hints[] = '→ PRIMEIRA TENTATIVA: recontato leve — mencione o crédito identificado e pergunte se ainda tem interesse.';
        } elseif ($currentCount === 1) {
            $hints[] = '→ SEGUNDA TENTATIVA: reforce a oportunidade com o valor disponível. Destaque a utilidade do crédito.';
        } else {
            $hints[] = '→ TENTATIVA '.$currentCount.': urgência moderada e acolhedora. Avise que ainda pode ajudar.';
        }

        // Credit freshness check — Carbon 3.x diffInDays is signed; call from the past date toward now
        if ($this->lead->credito_json && $this->lead->updated_at) {
            $creditDaysOld = (int) round(Carbon::parse($this->lead->updated_at)->diffInDays(now()));
            if ($creditDaysOld > 30) {
                $hints[] = "→ CRÉDITO DESATUALIZADO ({$creditDaysOld} dias): considere acionar `consultar_credito_inss` para atualizar os valores antes de mencioná-los.";
            }
        }

        // Days since last client message (prefer last_inbound_at when set)
        $lastClientTouch = $this->lead->last_inbound_at ?? $this->lead->last_interaction_at;
        if ($lastClientTouch) {
            $daysSince = (int) round(Carbon::parse($lastClientTouch)->diffInDays(now()));
            if ($daysSince > 7) {
                $hints[] = "→ LEAD INATIVO HÁ {$daysSince} DIAS: reengajamento cuidadoso — não pressione.";
            }
        }

        return empty($hints) ? '' : "\n".implode("\n", $hints);
    }

    /**
     * Build a short autonomous-decision hint for prompt template rendering.
     */
    private function buildAutonomousHint(bool $isLast): string
    {
        return $isLast
            ? 'Esta é a última tentativa. Gere uma mensagem de despedida respeitosa sem pressionar.'
            : 'Analise o histórico. Se o cliente respondeu com intenção, acione escalar_para_humano. Se recusou, acione atualizar_status_lead com optou_sair. Caso contrário, gere a mensagem de follow-up.';
    }

    private function buildCustomizationHint(
        string $messageType,
        string $tone,
        int $persuasionIntensity,
        string $customInstructions,
        string $toneByAttempt,
    ): string {
        $hint = "\n\n[Configuracao do follow-up: tipo={$messageType}; tom={$tone}; persuasao={$persuasionIntensity}/5; tentativa={$toneByAttempt}.]";

        if ($customInstructions !== '') {
            $hint .= "\n[Instrucoes adicionais: {$customInstructions}]";
        }

        return $hint;
    }

    private function formatCustomInstructions(string $customInstructions): string
    {
        if ($customInstructions === '') {
            return '';
        }

        return "\n        - Instrucoes adicionais do gestor: {$customInstructions}.";
    }

    /**
     * Map the resolver tone onto the legacy template's {approach} vocabulary so DB
     * prompt templates that still reference {approach} stay filled without a config column.
     */
    private function approachFromTone(string $tone): string
    {
        return match ($tone) {
            'acolhedor', 'descontraido' => 'amigavel',
            'direto' => 'persuasivo',
            default => 'natural',
        };
    }

    private function toneByAttempt(int $currentCount, bool $isLast, string $tone, int $persuasionIntensity): string
    {
        if ($isLast) {
            return "Despedida respeitosa, tom {$tone}, sem pressão";
        }

        $stage = match (true) {
            $currentCount === 0 => 'Leve recontato',
            $currentCount === 1 => 'Reforce a oportunidade',
            default => 'Urgência moderada',
        };

        $modifier = match (true) {
            $persuasionIntensity <= 1 => 'sem urgência',
            $persuasionIntensity === 2 => 'pergunta simples',
            $persuasionIntensity === 3 => 'mencione oportunidade se houver valor',
            $persuasionIntensity === 4 => 'destaque benefício concreto',
            default => 'chamada direta para decisão',
        };

        return "{$stage}, tom {$tone}, {$modifier}";
    }

    /**
     * Append campaign origin context when the lead was acquired via a campaign.
     */
    private function buildCampaignFollowUpContext(): string
    {
        if (! $this->lead->campaign_id) {
            return '';
        }

        $campaign = $this->lead->campaign()->with('whatsappTemplate')->first();

        if (! $campaign) {
            return '';
        }

        $ctx = "\n\n[Campanha origem: \"{$campaign->name}\"";

        if ($campaign->whatsappTemplate) {
            $ctx .= " | Template: {$campaign->whatsappTemplate->name}";
        }

        $ctx .= '. Use o contexto da campanha ao gerar a mensagem de recontato.]';

        return $ctx;
    }

    /**
     * Sentinel used to suppress sending a reply when the agent completes an opt-out.
     */
    private function getSentinel(): string
    {
        return AgentService::NO_REPLY_SENTINEL;
    }
}
