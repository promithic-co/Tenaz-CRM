<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreUraInboundLeadRequest;
use App\Http\Requests\StoreUraTriggerRequest;
use App\Jobs\SendInboundLeadWhatsAppJob;
use App\Jobs\SendUraTemplateJob;
use App\Models\UraApiKey;
use App\Models\VoiceInstance;
use Illuminate\Http\JsonResponse;

class UraInboundController extends Controller
{
    public function store(StoreUraInboundLeadRequest $request): JsonResponse
    {
        $voiceInstanceId = (int) $request->validated('voice_instance_id');

        if (! $this->mayDispatchForVoiceInstance($request, $voiceInstanceId)) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        SendInboundLeadWhatsAppJob::dispatch(
            $voiceInstanceId,
            $request->validated('phone'),
            $request->validated('name'),
        );

        return response()->json([
            'lead_id' => null,
            'status' => 'queued',
            'message' => 'Lead será processado em instantes.',
        ], 201);
    }

    public function trigger(StoreUraTriggerRequest $request): JsonResponse
    {
        $apiKey = $request->attributes->get('ura_api_key');

        if (! $apiKey instanceof UraApiKey) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        SendUraTemplateJob::dispatch(
            $apiKey->id,
            $request->validated('phone'),
            $request->validated('name'),
            $request->validated('variables') ?? [],
        );

        return response()->json([
            'status' => 'queued',
            'message' => 'Lead será processado em instantes.',
        ], 201);
    }

    /**
     * Tenant-bind the inbound lead to the calling key.
     *
     * Per-tenant UraApiKey callers may only target voice instances they own.
     * The legacy global config key (no attribute set) is a trusted broker and
     * remains able to route to any tenant for backward compatibility.
     */
    private function mayDispatchForVoiceInstance(StoreUraInboundLeadRequest $request, int $voiceInstanceId): bool
    {
        $apiKey = $request->attributes->get('ura_api_key');

        if (! $apiKey instanceof UraApiKey) {
            return true;
        }

        return VoiceInstance::withoutGlobalScopes()
            ->whereKey($voiceInstanceId)
            ->where('tenant_id', $apiKey->tenant_id)
            ->exists();
    }
}
