<?php

namespace App\Http\Controllers\Backoffice;

use App\Http\Controllers\Controller;
use App\Http\Requests\Backoffice\UpdateAgentPromptRequest;
use App\Models\Agent;
use App\Models\AgentConfig;
use App\Models\NicheTemplate;
use App\Models\PromptTemplate;
use App\Services\AgentPromptComposer;
use App\Services\AgentTemplateService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Prompt tab of the agent cockpit.
 *
 * Writes an agent-scoped PromptTemplate of type `system`, which GenericAgent
 * loads INSTEAD of composing from PromptComposer. The head and the
 * FERRAMENTAS / SEGURANÇA / ENCERRAMENTO tail are therefore re-attached on
 * every save by AgentPromptComposer — an override can change the middle of the
 * prompt, never its protections.
 *
 * Every save is a new version (PromptTemplate::saveNewVersion), and the screen
 * can deactivate the override to fall back to the composed default.
 *
 * Cross-tenant isolation comes from route-model binding: while a company is
 * active the tenant global scope makes another company's agent 404.
 */
class BackofficeAgentPromptController extends Controller
{
    private const TYPE = 'system';

    private const SLUG = 'aria-system';

    public function __construct(private readonly AgentPromptComposer $composer) {}

    public function edit(Agent $agent): Response
    {
        $template = $this->activeTemplateFor($agent);
        $capabilities = $this->toolCapabilitiesFor($agent);

        return Inertia::render('backoffice/agents/Prompt', [
            'agent' => [
                'id' => $agent->id,
                'name' => $agent->name,
                'slug' => $agent->slug,
            ],
            'editor' => $this->editorState($agent, $template),
            'template' => $template === null ? null : [
                'id' => $template->id,
                'version' => (int) $template->version,
                'updated_at' => $template->updated_at?->toIso8601String(),
            ],
            /** Read-only blocks the operator cannot edit, shown so the whole prompt stays visible. */
            'head' => $this->composer->head(),
            'tail' => $this->composer->tailSections($capabilities),
            /** The tail freezes at save time, so changing tools later needs a re-save. */
            'toolsRestricted' => $capabilities !== null,
            'history' => $this->historyFor($agent),
        ]);
    }

    public function update(UpdateAgentPromptRequest $request, Agent $agent): RedirectResponse
    {
        $validated = $request->validated();
        $capabilities = $this->toolCapabilitiesFor($agent);
        $isRaw = $validated['editor_mode'] === 'raw';

        $content = $isRaw
            ? $this->composer->fromRaw((string) $validated['raw_content'], $capabilities)
            : $this->composer->fromSections($validated['sections'] ?? [], $capabilities);

        $payload = [
            'content' => $content,
            /** Editor state, so reopening shows what was typed instead of the composed text. */
            'sections' => $isRaw
                ? ['raw' => (string) $validated['raw_content']]
                : array_values($validated['sections'] ?? []),
            'editor_mode' => $validated['editor_mode'],
        ];

        $template = $this->activeTemplateFor($agent);

        if ($template !== null) {
            $version = $template->version + 1;
            $template->saveNewVersion($payload);

            return back()->with('success', "Prompt salvo na versão {$version}.");
        }

        PromptTemplate::create(array_merge($payload, [
            'tenant_id' => (string) $agent->tenant_id,
            'agent_id' => $agent->id,
            'name' => "Prompt do agente {$agent->name}",
            'slug' => self::SLUG,
            'type' => self::TYPE,
            'version' => 1,
            'is_active' => true,
        ]));

        return back()->with('success', 'Prompt salvo na versão 1.');
    }

    /**
     * Deactivates the override so the agent goes back to the composed default
     * (PromptComposer + the niche sections of its template). History is kept.
     */
    public function destroy(Agent $agent): RedirectResponse
    {
        $template = $this->activeTemplateFor($agent);

        if ($template === null) {
            return back()->with('success', 'Este agente já usa o prompt padrão.');
        }

        $template->update(['is_active' => false]);

        return back()->with('success', 'Prompt personalizado desativado — o agente voltou ao padrão.');
    }

    /**
     * The agent's own active system template. Company-wide templates
     * (`agent_id` null) are deliberately excluded: editing here must never
     * rewrite a prompt shared by every agent of the company.
     */
    private function activeTemplateFor(Agent $agent): ?PromptTemplate
    {
        return PromptTemplate::query()
            ->where('tenant_id', (string) $agent->tenant_id)
            ->where('agent_id', $agent->id)
            ->where('type', self::TYPE)
            ->where('is_active', true)
            ->orderByDesc('version')
            ->first();
    }

    /**
     * Editor payload: what the operator typed last time, or the same middle the
     * runtime composes today when nothing was ever saved.
     *
     * @return array{mode: string, sections: list<array{title: string, content: string}>, raw_content: string, is_override: bool}
     */
    private function editorState(Agent $agent, ?PromptTemplate $template): array
    {
        $stored = is_array($template?->sections) ? $template->sections : [];
        $mode = $template?->editor_mode === 'raw' ? 'raw' : 'structured';
        $storedSections = array_values(array_filter($stored, is_array(...)));

        return [
            'mode' => $mode,
            'sections' => $mode === 'structured' && $storedSections !== []
                ? $storedSections
                : $this->composer->defaultSections($this->nicheTemplateFor($agent)),
            'raw_content' => $mode === 'raw' ? (string) ($stored['raw'] ?? '') : '',
            'is_override' => $template !== null,
        ];
    }

    /**
     * Versions of this agent's prompt, newest first.
     *
     * @return list<array{version: int, editor_mode: string|null, is_active: bool, created_at: string|null}>
     */
    private function historyFor(Agent $agent): array
    {
        return PromptTemplate::query()
            ->where('tenant_id', (string) $agent->tenant_id)
            ->where('agent_id', $agent->id)
            ->where('type', self::TYPE)
            ->orderByDesc('version')
            ->limit(10)
            ->get(['version', 'editor_mode', 'is_active', 'created_at'])
            ->map(fn (PromptTemplate $row): array => [
                'version' => (int) $row->version,
                'editor_mode' => $row->editor_mode,
                'is_active' => (bool) $row->is_active,
                'created_at' => $row->created_at?->toIso8601String(),
            ])
            ->all();
    }

    /** The niche template behind the agent, used only to seed a fresh editor. */
    private function nicheTemplateFor(Agent $agent): ?NicheTemplate
    {
        $slug = (string) ($this->configRowFor($agent)?->template_slug ?? '');

        if ($slug === '') {
            return null;
        }

        $row = app(AgentTemplateService::class)->find($slug);

        return $row ? (new NicheTemplate)->forceFill($row) : null;
    }

    /**
     * Native tools in force, so the composed tail never orders a tool the agent
     * no longer has. Null means no selection was saved — no restriction.
     *
     * @return list<string>|null
     */
    private function toolCapabilitiesFor(Agent $agent): ?array
    {
        $stored = $this->configRowFor($agent)?->tool_capabilities;

        return is_array($stored) ? array_values(array_map(strval(...), $stored)) : null;
    }

    /**
     * The agent is already tenant-checked by route-model binding, so the config
     * row is fetched by agent_id alone — a legacy row predating `tenant_id`
     * would be invisible to the scoped query.
     */
    private function configRowFor(Agent $agent): ?AgentConfig
    {
        return AgentConfig::query()
            ->withoutGlobalScope('tenant')
            ->where('agent_id', $agent->id)
            ->first();
    }
}
