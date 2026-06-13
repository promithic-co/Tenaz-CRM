<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreUraInboundLeadRequest;
use App\Http\Requests\StoreUraTriggerRequest;
use App\Jobs\SendInboundLeadWhatsAppJob;
use App\Jobs\SendUraTemplateJob;
use App\Models\UraApiKey;
use Illuminate\Http\JsonResponse;

class UraInboundController extends Controller
{
    public function store(StoreUraInboundLeadRequest $request): JsonResponse
    {
        SendInboundLeadWhatsAppJob::dispatch(
            $request->validated('voice_instance_id'),
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
}
