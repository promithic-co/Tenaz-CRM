<?php

namespace App\Http\Controllers;

use App\Enums\TemplateKind;
use App\Http\Requests\StoreWhatsappTemplateRequest;
use App\Http\Requests\UpdateWhatsappTemplateRequest;
use App\Jobs\SyncMetaTemplatesJob;
use App\Models\Campaign;
use App\Models\WhatsappInstance;
use App\Models\WhatsappTemplate;
use App\Services\WhatsApp\MetaTemplateService;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class WhatsappTemplateController extends Controller
{
    public function __construct(private readonly MetaTemplateService $metaTemplateService) {}

    public function index(): Response
    {
        $tenantId = (string) auth()->user()->tenantId;

        $templates = WhatsappTemplate::ofKind(TemplateKind::MetaHsm)
            ->with('whatsappInstance')
            ->when(request('status'), fn ($q, $status) => $q->where('status', $status))
            ->orderByDesc('created_at')
            ->paginate(20);

        $instances = WhatsappInstance::where('tenant_id', $tenantId)
            ->get(['id', 'name', 'display_name', 'provider', 'meta_waba_id', 'meta_access_token'])
            ->map(fn (WhatsappInstance $instance): array => [
                'id' => $instance->id,
                'name' => $instance->name,
                'display_name' => $instance->display_name,
                'provider' => $instance->provider->value,
                'meta_waba_id' => $instance->meta_waba_id,
                'has_meta_access_token' => filled($instance->meta_access_token),
            ]);

        return Inertia::render('templates/Index', [
            'templates' => $templates,
            'instances' => $instances,
            'currentKind' => TemplateKind::MetaHsm->value,
            'flash' => session('success'),
            'error' => session('error'),
        ]);
    }

    public function store(StoreWhatsappTemplateRequest $request): RedirectResponse
    {
        $tenantId = (string) auth()->user()->tenantId;

        $instance = WhatsappInstance::where('tenant_id', $tenantId)
            ->whereKey($request->validated('whatsapp_instance_id'))
            ->firstOrFail();

        if (! filled($instance->meta_waba_id) || ! filled($instance->meta_access_token)) {
            return back()->withErrors([
                'whatsapp_instance_id' => 'A instancia Meta selecionada nao possui WABA ID ou token configurado.',
            ])->withInput();
        }

        try {
            $this->metaTemplateService->createAndStoreBodyTemplate(
                instance: $instance,
                tenantId: $tenantId,
                internalName: (string) $request->validated('name'),
                metaName: (string) $request->validated('meta_template_name'),
                category: strtoupper((string) $request->validated('category')),
                language: (string) $request->validated('language'),
                body: (string) ($request->validated('body') ?? ''),
                variableExamples: (array) ($request->validated('variable_examples') ?? []),
            );
        } catch (RequestException $exception) {
            $message = $exception->response?->json('error.message')
                ?? 'A Meta recusou a criacao do template. Verifique os dados e tente novamente.';

            return back()->withErrors(['meta_template' => $message])->withInput();
        }

        return back()->with('success', 'Template enviado para analise da Meta.');
    }

    public function update(UpdateWhatsappTemplateRequest $request, WhatsappTemplate $template): RedirectResponse
    {
        $template->update([
            'name' => $request->validated('name') ?? $template->name,
        ]);

        return back()->with('success', 'Template "'.$template->name.'" atualizado.');
    }

    public function syncMeta(Request $request): RedirectResponse
    {
        $tenantId = (string) auth()->user()->tenantId;

        $instanceId = $request->validate([
            'whatsapp_instance_id' => [
                'required',
                Rule::exists('whatsapp_instances', 'id')->where('tenant_id', $tenantId),
            ],
        ])['whatsapp_instance_id'];

        SyncMetaTemplatesJob::dispatch((int) $instanceId);

        return back()->with('success', 'Sincronização Meta agendada.');
    }

    public function destroy(WhatsappTemplate $template): RedirectResponse
    {
        $this->authorize('delete', $template);

        $hasActiveCampaigns = Campaign::where('whatsapp_template_id', $template->id)
            ->whereIn('status', ['sending', 'paused', 'scheduled'])
            ->exists();

        if ($hasActiveCampaigns) {
            return back()->withErrors(['template' => 'Não é possível remover um template com campanhas ativas.']);
        }

        $template->delete();

        return back()->with('success', 'Template removido.');
    }
}
