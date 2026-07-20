<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateAgentFollowUpRequest;
use App\Models\Agent;
use App\Models\AgentConfig;
use App\Models\AgentFollowUpSetting;
use App\Services\FollowUpSettingsResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class AgentFollowUpController extends Controller
{
    public function show(Agent $agent): Response
    {
        $this->authorize('manage', $agent);

        $row = AgentFollowUpSetting::withoutGlobalScope('tenant')
            ->where('agent_id', $agent->id)
            ->first();

        return Inertia::render('agente/follow-up/Index', [
            'agent' => [
                'id' => $agent->id,
                'name' => $agent->name,
            ],
            'settings' => [
                'enabled' => (bool) ($row?->enabled ?? true),
                'first_delay_minutes' => (int) ($row?->first_delay_minutes ?? 10),
                'max_count' => (int) ($row?->max_attempts_within_window ?? 2),
                'followup_window_start' => substr((string) ($row?->business_window_start ?? '08:00'), 0, 5),
                'followup_window_end' => substr((string) ($row?->business_window_end ?? '20:00'), 0, 5),
                'min_interval_minutes' => (int) ($row?->min_interval_minutes ?? 60),
                'message_type' => $row?->message_type ?? 'contextual',
                'tone' => $row?->tone ?? 'consultivo',
                'persuasion_intensity' => (int) ($row?->persuasion_intensity ?? 2),
                'custom_instructions' => $row?->custom_instructions ?? '',
            ],
            'flash' => session('success'),
        ]);
    }

    public function update(Agent $agent, UpdateAgentFollowUpRequest $request): RedirectResponse
    {
        $this->authorize('manage', $agent);

        DB::transaction(function () use ($agent, $request): void {
            AgentFollowUpSetting::withoutGlobalScope('tenant')->updateOrCreate(
                ['agent_id' => $agent->id],
                [
                    'tenant_id' => (string) $agent->tenant_id,
                    'enabled' => (bool) $request->validated('enabled'),
                    'first_delay_minutes' => (int) $request->validated('first_delay_minutes'),
                    'min_interval_minutes' => (int) $request->validated('min_interval_minutes'),
                    'max_attempts_within_window' => (int) $request->validated('max_count'),
                    'business_window_start' => $request->validated('followup_window_start'),
                    'business_window_end' => $request->validated('followup_window_end'),
                    'timezone' => 'America/Sao_Paulo',
                    'message_type' => $request->validated('message_type', 'contextual'),
                    'tone' => $request->validated('tone', 'consultivo'),
                    'persuasion_intensity' => (int) $request->validated('persuasion_intensity', 2),
                    'custom_instructions' => $request->validated('custom_instructions', ''),
                ]
            );

            // Mirror the primary fields the legacy AgentConfig resolver still reads.
            // daily_time / approach / interval_days are no longer written — dead UI fields.
            AgentConfig::updateOrCreate(
                ['agent_id' => $agent->id],
                [
                    'tenant_id' => $agent->tenant_id,
                    'followup_first_delay_minutes' => (int) $request->validated('first_delay_minutes'),
                    'followup_max_count' => (int) $request->validated('max_count'),
                    'followup_window_start' => $request->validated('followup_window_start'),
                    'followup_window_end' => $request->validated('followup_window_end'),
                    'followup_message_type' => $request->validated('message_type', 'contextual'),
                    'followup_tone' => $request->validated('tone', 'consultivo'),
                    'followup_persuasion_intensity' => (int) $request->validated('persuasion_intensity', 2),
                    'followup_custom_instructions' => $request->validated('custom_instructions', ''),
                ]
            );
        });

        Cache::forget("agent_config_id_{$agent->id}");

        $resolver = app(FollowUpSettingsResolver::class);
        $resolver->forgetAgent($agent->id);
        $resolver->forget((string) $agent->tenant_id, $agent->id);

        return back()->with('success', 'Configurações salvas com sucesso.');
    }
}
