<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateAgentFollowUpRequest;
use App\Models\Agent;
use App\Models\AgentConfig;
use App\Models\AgentFollowUpSetting;
use App\Services\FollowUpSettingsResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Cache;
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

        // Legacy daily_time / interval_days still live on AgentConfig (UI fields only,
        // not consumed by the follow-up engine which uses min_interval_minutes).
        $legacy = AgentConfig::firstOrCreate(
            ['agent_id' => $agent->id],
            ['tenant_id' => $agent->tenant_id, 'agent_name' => $agent->name]
        );

        $intervalDays = max(1, (int) round(($row?->min_interval_minutes ?? 1440) / 1440));

        return Inertia::render('agente/follow-up/Index', [
            'agent' => [
                'id' => $agent->id,
                'name' => $agent->name,
            ],
            'settings' => [
                'first_delay_minutes' => (int) ($row?->first_delay_minutes ?? 10),
                'daily_time' => $legacy->followup_daily_time,
                'max_count' => (int) ($row?->max_attempts_within_window ?? 2),
                'approach' => $legacy->followup_approach,
                'followup_window_start' => substr((string) ($row?->business_window_start ?? '08:00'), 0, 5),
                'followup_window_end' => substr((string) ($row?->business_window_end ?? '20:00'), 0, 5),
                'followup_interval_days' => $intervalDays,
                'message_type' => $row?->message_type ?? 'reengajamento',
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

        $intervalDays = (int) $request->validated('followup_interval_days', 1);
        $minIntervalMinutes = max(5, $intervalDays * 1440);

        AgentFollowUpSetting::withoutGlobalScope('tenant')->updateOrCreate(
            ['agent_id' => $agent->id],
            [
                'tenant_id' => (string) $agent->tenant_id,
                'enabled' => true,
                'first_delay_minutes' => (int) $request->validated('first_delay_minutes'),
                'min_interval_minutes' => $minIntervalMinutes,
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

        // Keep legacy UI-only fields (daily_time, approach) on AgentConfig for backward compat.
        AgentConfig::updateOrCreate(
            ['agent_id' => $agent->id],
            [
                'tenant_id' => $agent->tenant_id,
                'followup_daily_time' => $request->validated('daily_time'),
                'followup_approach' => $request->validated('approach'),
                'followup_interval_days' => $intervalDays,
                // Mirror primary fields too so any legacy consumer stays in sync.
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

        Cache::forget("agent_config_id_{$agent->id}");

        $resolver = app(FollowUpSettingsResolver::class);
        $resolver->forgetAgent($agent->id);
        $resolver->forget((string) $agent->tenant_id, $agent->id);

        return back()->with('success', 'Configurações salvas com sucesso.');
    }
}
