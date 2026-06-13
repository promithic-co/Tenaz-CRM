<?php

namespace App\Http\Controllers;

use App\Models\Contact;
use App\Models\Lead;
use App\Services\AgentService;
use App\Services\ContactSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AgentController extends Controller
{
    public function __construct(private readonly AgentService $agent) {}

    /**
     * POST /api/tenaz
     * { "whatsapp", "message", "tenant_id"?, "modo"? }
     * Returns { "response": "texto" } or { "response": null } (opt-out)
     */
    public function tenaz(Request $request): JsonResponse
    {
        return $this->handle($request);
    }

    /**
     * POST /api/aria legacy endpoint.
     *
     * Superseded by POST /api/tenaz. Emits RFC 8594 deprecation signals so
     * existing integrations get a migration cue while the alias remains live.
     */
    public function aria(Request $request): JsonResponse
    {
        return $this->handle($request)
            ->header('Deprecation', 'true')
            ->header('Link', '<'.route('api.tenaz').'>; rel="successor-version"');
    }

    public function handle(Request $request): JsonResponse
    {
        $request->validate([
            'whatsapp' => ['required', 'string', 'regex:/^\d{10,15}$/'],
            'message' => ['required', 'string', 'max:2000'],
            'tenant_id' => ['nullable', 'string', 'max:50'],
            'agent_id' => ['nullable', 'integer', 'exists:agents,id'],
            'modo' => ['nullable', 'in:receptivo,bulk'],
        ]);

        $tenantId = $request->tenant_id ?? 'default';
        $agentId = $request->integer('agent_id') ?: null;

        $lead = Lead::query()
            ->where('whatsapp', $request->whatsapp)
            ->where('tenant_id', $tenantId)
            ->first();

        if (! $lead) {
            $lead = Lead::create([
                'whatsapp' => $request->whatsapp,
                'tenant_id' => $tenantId,
                'agent_id' => $agentId,
                'modo' => $request->modo ?? 'receptivo',
            ]);
        } elseif ($lead->agent_id === null && $agentId !== null) {
            $lead->update(['agent_id' => $agentId]);
        }

        app(ContactSyncService::class)->syncFromLead($lead, Contact::SOURCE_AGENT_API);
        $lead->refresh();

        $response = $this->agent->process($lead, $request->message);

        return response()->json(['response' => $response]);
    }
}
