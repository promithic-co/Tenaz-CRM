<?php

namespace App\Ai\Agents;

use App\Ai\Tools\AtualizarStatusLeadTool;
use App\Ai\Tools\EscalarParaHumanoTool;
use App\Ai\Tools\RegistrarInformacaoContatoTool;
use App\Models\NicheTemplate;
use App\Services\AgentTemplateService;
use App\Services\PromptComposer;
use Stringable;

/**
 * Niche-agnostic runtime agent for template-created agents.
 *
 * Instructions come from PromptComposer (platform core + NicheTemplate
 * niche_sections + AgentConfig variables), unless the tenant has a DB
 * PromptTemplate of type `system` — that override path keeps parity with
 * the specialized agents.
 *
 * Toolset: platform tools (collect info, escalate, status) plus the
 * template's webhook ToolDefinitions. No hardcoded niche tool.
 */
class GenericAgent extends BaseCustomerServiceAgent
{
    public function instructions(): Stringable|string
    {
        $userId = $this->resolveUserId();
        $cfg = $this->config();

        $template = $this->loadPromptTemplate('system');

        $body = $template
            ? $template->render($this->buildPromptVariables($userId, $cfg))
            : app(PromptComposer::class)->compose($this->nicheTemplate($cfg), $this->composerVariables($cfg));

        return $body
            .$this->buildLeadContext()
            .$this->buildStatusHint()
            ."\n→ Horário atual (Brasília): {$this->saudacao()} ({$this->localTime()})";
    }

    public function tools(): iterable
    {
        $tools = [new RegistrarInformacaoContatoTool($this->lead)];

        if (! in_array($this->lead->status, ['escalado', 'convertido', 'optou_sair'])) {
            $tools[] = new EscalarParaHumanoTool($this->lead);
        }

        if (! in_array($this->lead->status, ['convertido', 'optou_sair'])) {
            $tools[] = new AtualizarStatusLeadTool($this->lead);
        }

        return [...$tools, ...$this->loadWebhookTools()];
    }

    /**
     * Resolve the agent's NicheTemplate from the config template_slug via the
     * registry cache (observer-busted). Missing slug or config-only template
     * composes the platform core alone — safety sections are never skipped.
     *
     * @param  array<string, mixed>  $cfg
     */
    private function nicheTemplate(array $cfg): NicheTemplate
    {
        $slug = (string) ($cfg['template_slug'] ?? '');
        $row = $slug !== '' ? app(AgentTemplateService::class)->find($slug) : null;

        return $row ? (new NicheTemplate)->forceFill($row) : new NicheTemplate;
    }

    /**
     * Composer variables: the resolved AgentConfig plus runtime time context.
     * Non-scalar entries are ignored by PromptComposer::render.
     *
     * @param  array<string, mixed>  $cfg
     * @return array<string, mixed>
     */
    private function composerVariables(array $cfg): array
    {
        return array_merge($cfg, [
            'saudacao' => $this->saudacao(),
            'local_time' => $this->localTime(),
        ]);
    }
}
