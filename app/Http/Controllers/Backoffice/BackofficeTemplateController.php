<?php

namespace App\Http\Controllers\Backoffice;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAgentTemplateConfigRequest;
use App\Models\AgentTemplateConfig;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class BackofficeTemplateController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('backoffice/templates/Index', [
            'templates' => AgentTemplateConfig::query()
                ->orderBy('template_slug')
                ->get([
                    'id', 'template_slug', 'agent_provider', 'agent_model',
                    'transcription_provider', 'transcription_model',
                    'vision_provider', 'vision_model',
                    'temperature', 'max_tokens', 'max_conversation_messages',
                ]),
        ]);
    }

    public function edit(string $template_slug): Response
    {
        $template = AgentTemplateConfig::query()
            ->where('template_slug', $template_slug)
            ->firstOrFail();

        return Inertia::render('backoffice/templates/Edit', [
            'template' => $template,
            'providerWhitelist' => config('credflow.agent.provider_whitelist'),
        ]);
    }

    public function update(StoreAgentTemplateConfigRequest $request, string $template_slug): RedirectResponse
    {
        $template = AgentTemplateConfig::query()
            ->where('template_slug', $template_slug)
            ->firstOrFail();

        $template->update($request->validated());

        return back()->with('success', 'Configuração do template atualizada.');
    }
}
