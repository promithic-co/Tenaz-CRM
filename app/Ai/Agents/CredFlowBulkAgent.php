<?php

namespace App\Ai\Agents;

use App\Ai\Tools\AtualizarStatusLeadTool;
use App\Ai\Tools\ConsultarCreditoInssTool;
use App\Ai\Tools\EscalarParaHumanoTool;
use App\Models\AgentOperationalRule;
use Stringable;

class CredFlowBulkAgent extends BaseCustomerServiceAgent
{
    public function instructions(): Stringable|string
    {
        $userId = $this->resolveUserId();
        $cfg = $this->config();

        // Try loading prompt template from DB
        $template = $this->loadPromptTemplate('system_bulk');
        if ($template) {
            return $template->render($this->buildPromptVariables($userId, $cfg))
                .$this->buildLeadContext()
                .$this->buildStatusHint()
                ."\n→ Horário atual (Brasília): {$this->saudacao()} ({$this->localTime()})";
        }

        // Fallback: hardcoded prompt
        $rules = $userId
            ? AgentOperationalRule::forUser($userId)
            : AgentOperationalRule::forTenant($this->lead->tenant_id ?? 'default');

        $minNovo = number_format((float) $rules->regra('valor_minimo_liberado_novo'), 0, ',', '.');
        $minRefin = number_format((float) $rules->regra('valor_minimo_liberado_refin'), 0, ',', '.');
        $idadeMax = (int) $rules->regra('idade_maxima');
        $aceitaLoas = $rules->especie('aceita_loas_emprestimo') ? 'sim' : 'não';

        $modalidadeFraming = $this->buildModalidadeFraming();
        $campaignContext = $this->buildCampaignContext();
        $experimentVariant = $this->buildExperimentContext();

        $extra = $cfg['extra_rules'] ? "\n- {$cfg['extra_rules']}" : '';

        return <<<PROMPT
        Você é {$cfg['agent_name']} — consultora virtual de crédito consignado INSS da {$cfg['company_name']}. Tom: {$cfg['agent_personality']}.

        ## CONTEXTO DE CAMPANHA
        O lead recebeu uma mensagem de campanha e respondeu. Ele NÃO veio até você — você foi até ele. Seja respeitoso, breve e direto.
        {$campaignContext}

        ## FORMATO
        Máx 200 chars/mensagem. Uma ideia por vez. Pt-BR simples (público: aposentados e pensionistas).
        NUNCA use formatação markdown. Texto puro apenas.

        ## FLUXO (campanha)
        1. Confirme identidade → apresente-se brevemente
        2. Peça CPF → `consultar_credito_inss`
        3. Qualificado: apresente opções (máx 200 chars) → interesse confirmado → `escalar_para_humano`
           (motivo: exatamente proposta_aceita, solicitacao_cliente, problema_tecnico ou outro; resumo: produto, valor, pendências)
        4. Sem crédito: informe e encerre

        ## REGRA ABSOLUTA
        Qualquer sinal de desinteresse → `atualizar_status_lead` com "optou_sair" → responda [CREDFLOW_NAO_RESPONDER]
        Sinais: "não quero", "para", "sai", "me bloqueia", grosseria, recusa.
        {$modalidadeFraming}

        ## CRITÉRIOS
        Novo: min R\${$minNovo} | Refin: min R\${$minRefin}/contrato | Idade máx: {$idadeMax} | LOAS: {$aceitaLoas}

        ## REGRAS
        - Modo bulk: NÃO use registrar_lead_sem_credito
        - NUNCA invente valores
        - Se ferramenta retornou erro 2x seguidas, PARE e informe instabilidade
        - NÃO insista após recusa{$extra}
        {$experimentVariant}
        {$this->buildLeadContext()}{$this->buildStatusHint()}
        → Horário atual (Brasília): {$this->saudacao()} ({$this->localTime()})
        PROMPT;
    }

    /**
     * Build the variable map for prompt template rendering.
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
            'saudacao' => $this->saudacao(),
            'local_time' => $this->localTime(),
            'extra_rules' => $cfg['extra_rules'] ? "\n- {$cfg['extra_rules']}" : '',
            'min_novo' => number_format((float) $rules->regra('valor_minimo_liberado_novo'), 0, ',', '.'),
            'min_refin' => number_format((float) $rules->regra('valor_minimo_liberado_refin'), 0, ',', '.'),
            'idade_max' => (int) $rules->regra('idade_maxima'),
            'aceita_loas' => $rules->especie('aceita_loas_emprestimo') ? 'sim' : 'não',
            'campaign_context' => $this->buildCampaignContext(),
            'modalidade_framing' => $this->buildModalidadeFraming(),
        ];
    }

    /** Build campaign context block when lead.campaign_id is set. */
    private function buildCampaignContext(): string
    {
        if (! $this->lead->campaign_id) {
            return '';
        }

        $campaign = $this->lead->campaign()->with('whatsappTemplate')->first();

        if (! $campaign) {
            return '';
        }

        $ctx = "\nCampanha origem: {$campaign->name}";

        if ($campaign->whatsappTemplate) {
            $ctx .= " | Template: {$campaign->whatsappTemplate->name}";
        }

        return $ctx;
    }

    /** Build product-specific framing based on modalidade derived from credito_json. */
    private function buildModalidadeFraming(): string
    {
        $credito = $this->lead->credito_json;

        if (! $credito) {
            return '';
        }

        $totais = $credito['resumoGeral']['totais'] ?? [];
        $framings = [];

        if (($totais['margemLivre'] ?? 0) > 0) {
            $framings[] = 'Crédito Novo: "você recebe em mãos, com desconto automático no benefício".';
        }

        if (($totais['refinanciamento'] ?? 0) > 0) {
            $framings[] = 'Refinanciamento/Portabilidade: "você recebe troco dos contratos existentes, sem parcela nova".';
        }

        if (($totais['cartoes'] ?? 0) > 0) {
            $framings[] = 'Cartão RMC/RCC: "limite disponível no cartão, com parcela no benefício".';
        }

        if (empty($framings)) {
            return '';
        }

        return "\n## FRAMING POR MODALIDADE\n".implode("\n", $framings);
    }

    /** Build experiment context for A/B testing. */
    private function buildExperimentContext(): string
    {
        $slug = $this->lead->experiment_slug;
        $variant = $this->lead->experiment_variant;

        if (! $slug || ! $variant) {
            return '';
        }

        return "\n[Experimento: {$slug} | Variante: {$variant}]";
    }

    public function tools(): iterable
    {
        $tools = [];

        if (! $this->lead->credito_json) {
            $tools[] = new ConsultarCreditoInssTool($this->lead);
        }

        if (! in_array($this->lead->status, ['escalado', 'convertido', 'optou_sair'])) {
            $tools[] = new EscalarParaHumanoTool($this->lead);
        }

        if (! in_array($this->lead->status, ['convertido', 'optou_sair'])) {
            $tools[] = new AtualizarStatusLeadTool($this->lead);
        }

        return $tools;
    }

    protected function maxConversationMessages(): int
    {
        $cfg = $this->config();

        return isset($cfg['max_conversation_messages'])
            ? (int) $cfg['max_conversation_messages']
            : 20;
    }
}
