<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReevaluateLeadAutoTagRequest;
use App\Jobs\TagLeadFromConversationJob;
use App\Models\AppSetting;
use App\Models\Lead;
use App\Models\Tag;
use Illuminate\Http\RedirectResponse;

class LeadAutoTagController extends Controller
{
    /**
     * Dispatch a manual AI re-evaluation job for the given Lead.
     *
     * Guards: feature enabled, at least one ai_detectable tag exists, user has Lead update access.
     */
    public function store(ReevaluateLeadAutoTagRequest $request, Lead $lead): RedirectResponse
    {
        $this->authorize('update', $lead);

        $tenantId = (string) $lead->tenant_id;

        if (! AppSetting::getForTenant($tenantId, 'auto_tagging_enabled', false)) {
            return back()->withErrors(['auto_tag' => 'A funcionalidade de auto-tag por IA está desativada nas configurações.']);
        }

        $hasDetectable = Tag::query()
            ->where('tenant_id', $tenantId)
            ->where('ai_detectable', true)
            ->exists();

        if (! $hasDetectable) {
            return back()->with('status', 'Nenhuma tag detectável por IA configurada.');
        }

        TagLeadFromConversationJob::dispatch($lead->id, 'manual', $request->user()?->id);

        return back()->with('status', 'Reavaliação com IA agendada.');
    }
}
