<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreUraApiKeyRequest;
use App\Models\Agent;
use App\Models\UraApiKey;
use App\Models\WhatsappTemplate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class UraApiKeyController extends Controller
{
    public function index(Request $request): Response
    {
        $tenantId = (string) $request->user()->tenantId;

        $apiKeys = UraApiKey::with(['agent', 'whatsappTemplate'])
            ->where('tenant_id', $tenantId)
            ->latest()
            ->get();

        $agents = Agent::where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);

        $templates = WhatsappTemplate::where('tenant_id', $tenantId)
            ->approved()
            ->orderBy('name')
            ->get(['id', 'name', 'meta_template_name', 'language', 'variables_count', 'body', 'status']);

        return Inertia::render('ura/Index', [
            'apiKeys' => $apiKeys,
            'agents' => $agents,
            'templates' => $templates,
            'flash' => session('flash'),
            'createdKey' => session('created_key'),
        ]);
    }

    public function store(StoreUraApiKeyRequest $request): RedirectResponse
    {
        $tenantId = (string) $request->user()->tenantId;

        $generated = UraApiKey::generate();

        $apiKey = UraApiKey::create([
            'tenant_id' => $tenantId,
            'agent_id' => $request->validated('agent_id'),
            'whatsapp_template_id' => $request->validated('whatsapp_template_id'),
            'name' => $request->validated('name'),
            'key_hash' => $generated['key_hash'],
            'key_preview' => $generated['key_preview'],
            'active' => true,
        ]);

        return back()->with('created_key', [
            'id' => $apiKey->id,
            'plain' => $generated['key'],
            'name' => $apiKey->name,
        ]);
    }

    public function update(Request $request, UraApiKey $uraApiKey): RedirectResponse
    {
        $this->authorizeKey($request, $uraApiKey);
        $tenantId = (string) $request->user()->tenantId;

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'agent_id' => [
                'sometimes',
                'integer',
                Rule::exists('agents', 'id')->where(fn ($query) => $query->where('tenant_id', $tenantId)),
            ],
            'whatsapp_template_id' => [
                'nullable',
                'integer',
                Rule::exists('whatsapp_templates', 'id')->where(fn ($query) => $query->where('tenant_id', $tenantId)),
            ],
            'active' => ['sometimes', 'boolean'],
        ]);

        $uraApiKey->update($validated);

        return back()->with('flash', 'Integração atualizada.');
    }

    public function destroy(Request $request, UraApiKey $uraApiKey): RedirectResponse
    {
        $this->authorizeKey($request, $uraApiKey);

        $uraApiKey->delete();

        return back()->with('flash', 'Chave removida.');
    }

    private function authorizeKey(Request $request, UraApiKey $uraApiKey): void
    {
        abort_unless((string) $uraApiKey->tenant_id === (string) $request->user()->tenantId, 403);
    }
}
