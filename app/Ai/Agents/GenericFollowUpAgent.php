<?php

namespace App\Ai\Agents;

use App\Ai\Middleware\AuditLogMiddleware;
use App\Ai\Middleware\ToolCallGuardMiddleware;
use App\Ai\Tools\AtualizarStatusLeadTool;
use App\Ai\Tools\EscalarParaHumanoTool;
use App\Services\AgentService;
use App\Services\FollowUpSettingsResolver;
use App\Services\FollowUpWindowService;
use Carbon\Carbon;
use Stringable;

/**
 * Niche-agnostic follow-up agent for template-driven (GenericAgent) tenants.
 *
 * Mirrors CredFlowFollowUpAgent's engine contract (FollowUpSettingsResolver as
 * the single source for attempt/window parameters, `followup` PromptTemplate
 * override, opt-out sentinel) but carries zero credit/INSS vocabulary and no
 * consultation tool — the re-engagement message is grounded only in the
 * conversation history and the collected contact information.
 */
class GenericFollowUpAgent extends BaseCustomerServiceAgent
{
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
        $windowHint = $this->buildCustomerServiceWindowHint();

        $template = $this->loadPromptTemplate('followup');
        if ($template) {
            $rendered = $template->render([
                'agent_name' => $cfg['agent_name'] ?? 'Tenaz CRM',
                'company_name' => $cfg['company_name'] ?? '',
                'attempt_number' => $attemptNumber,
                'max_count' => $maxCount,
                'message_type' => $messageType,
                'tone' => $tone,
                'persuasion_intensity' => $persuasionIntensity,
                'custom_instructions' => $customInstructions,
                'is_last' => $isLast ? 'sim' : 'não',
                'customer_service_window' => $windowHint,
            ]);

            return $rendered
                .$this->buildLeadContext()
                .$windowHint
                .$this->buildFollowUpStateHint($isLast, $maxCount, $currentCount);
        }

        $agentName = $cfg['agent_name'] ?? 'Tenaz CRM';
        $companyName = $cfg['company_name'] ?? '';

        return <<<PROMPT
        Você é {$agentName}, agente de reengajamento da {$companyName} no WhatsApp.

        ## MISSÃO
        Retomar o contato com este cliente de forma natural e útil.
        Tentativa {$attemptNumber} de {$maxCount}.
        {$windowHint}

        ## DECISÃO AUTÔNOMA — EXECUTE A AÇÃO CORRETA
        Analise o Contexto abaixo e o histórico da conversa, então decida:

        1. **Cliente respondeu com interesse no histórico recente** → acione `escalar_para_humano` com motivo `solicitacao_cliente` e resumo da situação. Depois responda confirmando ao cliente.

        2. **Cliente recusou explicitamente** ("não quero", "me tira", "para", "bloqueia") → acione `atualizar_status_lead` com `optou_sair`. Depois responda SOMENTE: {$this->getSentinel()}

        3. **Esta é a última tentativa** → gere mensagem de despedida respeitosa, deixando a porta aberta para contato futuro. Não pressione.

        4. **Caso padrão** → gere uma mensagem de recontato natural, curta, contextualizada com o que já aconteceu na conversa.

        ## REGRAS DA MENSAGEM
        - Tipo de mensagem: {$messageType}.
        - Tom de voz: {$tone}.
        - Intensidade de persuasão: {$persuasionIntensity}/5.
        {$this->formatCustomInstructions($customInstructions)}
        - Máx 150 chars.
        - Texto puro — sem markdown, asteriscos ou formatação.
        - Não repita o texto de tentativas anteriores (verifique o histórico).
        - NUNCA invente informações, valores ou promessas — use apenas o que consta no Contexto e no histórico.

        ## FERRAMENTAS — RESULTADOS EM JSON
        Resultados retornam `status` + `message` (+ `hint` opcional):
        - `success` → prossiga conforme planejado
        - `error` + `hint` → siga exatamente o `hint`
        - `already_done` → não repita a ação
        - `blocked` → ajuste a estratégia sem tentar novamente

        {$this->buildLeadContext()}{$this->buildFollowUpStateHint($isLast, $maxCount, $currentCount)}
        → Horário atual (Brasília): {$this->saudacao()} ({$this->localTime()})
        PROMPT;
    }

    public function tools(): iterable
    {
        $tools = [];

        if (! in_array($this->lead->status, ['escalado', 'convertido', 'optou_sair'])) {
            $tools[] = new EscalarParaHumanoTool($this->lead);
        }

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
        $closesAt = $window->freeFormWindowClosesAt($this->lead);
        $remainingMinutes = $window->freeFormRemainingMinutes($this->lead);

        if (! $closesAt) {
            return "\n[Janela WhatsApp: nao ha ultima mensagem do cliente registrada. Nao tente reabrir conversa fora da janela.]";
        }

        return "\n[Janela WhatsApp: restam aproximadamente {$remainingMinutes} minutos para mensagem livre. Limite da janela: {$closesAt->copy()->setTimezone('America/Sao_Paulo')->format('d/m H:i')} BRT. Use uma continuidade natural da conversa em andamento.]";
    }

    private function buildFollowUpStateHint(bool $isLast, int $maxCount, int $currentCount): string
    {
        $hints = [];

        if ($isLast) {
            $hints[] = "→ ÚLTIMA TENTATIVA ({$currentCount}+1/{$maxCount}): encerre com despedida respeitosa.";
        } elseif ($currentCount === 0) {
            $hints[] = '→ PRIMEIRA TENTATIVA: recontato leve — retome o assunto da conversa e pergunte se ainda tem interesse.';
        } else {
            $hints[] = '→ TENTATIVA '.($currentCount + 1).': urgência moderada e acolhedora. Avise que ainda pode ajudar.';
        }

        $lastClientTouch = $this->lead->last_inbound_at ?? $this->lead->last_interaction_at;
        if ($lastClientTouch) {
            $daysSince = (int) round(Carbon::parse($lastClientTouch)->diffInDays(now()));
            if ($daysSince > 7) {
                $hints[] = "→ LEAD INATIVO HÁ {$daysSince} DIAS: reengajamento cuidadoso — não pressione.";
            }
        }

        return empty($hints) ? '' : "\n".implode("\n", $hints);
    }

    private function formatCustomInstructions(string $customInstructions): string
    {
        if ($customInstructions === '') {
            return '';
        }

        return "\n        - Instruções adicionais do gestor: {$customInstructions}.";
    }

    private function getSentinel(): string
    {
        return AgentService::NO_REPLY_SENTINEL;
    }
}
