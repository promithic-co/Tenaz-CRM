<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        DB::table('agent_configs')
            ->whereNotNull('agent_id')
            ->whereNotNull('tenant_id')
            ->orderBy('id')
            ->lazy(500)
            ->each(function ($cfg) use ($now): void {
                // Translate legacy interval_days → min_interval_minutes when min_interval_minutes
                // is not directly available. If neither present, default to 60.
                $intervalMinutes = 60;
                if (! empty($cfg->followup_interval_days)) {
                    $intervalMinutes = (int) $cfg->followup_interval_days * 1440;
                }

                DB::table('agent_followup_settings')->updateOrInsert(
                    ['agent_id' => $cfg->agent_id],
                    [
                        'tenant_id' => (string) $cfg->tenant_id,
                        'enabled' => true,
                        'first_delay_minutes' => (int) ($cfg->followup_first_delay_minutes ?? 10),
                        'min_interval_minutes' => max(5, $intervalMinutes),
                        'max_attempts_within_window' => max(1, min(5, (int) ($cfg->followup_max_count ?? 2))),
                        'business_window_start' => $cfg->followup_window_start ?? '08:00',
                        'business_window_end' => $cfg->followup_window_end ?? '20:00',
                        'timezone' => 'America/Sao_Paulo',
                        'message_type' => $cfg->followup_message_type ?? 'contextual',
                        'tone' => $cfg->followup_tone ?? 'consultivo',
                        'persuasion_intensity' => max(1, min(5, (int) ($cfg->followup_persuasion_intensity ?? 2))),
                        'custom_instructions' => $cfg->followup_custom_instructions ?? '',
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]
                );
            });
    }

    public function down(): void
    {
        // Data-only migration; no schema rollback.
    }
};
