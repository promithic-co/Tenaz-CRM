<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $users = DB::table('users')->get(['id', 'name']);

        foreach ($users as $user) {
            $agentId = DB::table('agents')
                ->where('user_id', $user->id)
                ->value('id');

            if (! $agentId) {
                $defaultName = 'Agente Principal';

                $agentId = DB::table('agents')->insertGetId([
                    'user_id' => $user->id,
                    'name' => $defaultName,
                    'slug' => Str::slug($defaultName).'-'.$user->id,
                    'description' => 'Agente principal migrado do modo legada.',
                    'is_active' => true,
                    'is_default' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $legacyConfig = $this->legacyConfig((int) $user->id);
            $existsConfig = DB::table('agent_configs')->where('agent_id', $agentId)->exists();

            if (! $existsConfig) {
                DB::table('agent_configs')->insert(array_merge($legacyConfig, [
                    'agent_id' => $agentId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]));
            }

            $preferredInstance = (string) DB::table('app_settings')
                ->where('key', 'evolution_instance_name')
                ->where('user_id', $user->id)
                ->value('value');

            $instanceId = DB::table('whatsapp_instances')
                ->where('user_id', $user->id)
                ->where('name', $preferredInstance)
                ->value('id');

            if (! $instanceId) {
                $instanceId = DB::table('whatsapp_instances')
                    ->where('user_id', $user->id)
                    ->orderBy('id')
                    ->value('id');
            }

            if ($instanceId) {
                DB::table('whatsapp_instances')
                    ->where('id', $instanceId)
                    ->update(['agent_id' => $agentId]);
            }

            DB::table('leads')
                ->where('tenant_id', (string) $user->id)
                ->whereNull('agent_id')
                ->update(['agent_id' => $agentId]);

            $instances = DB::table('whatsapp_instances')
                ->where('user_id', $user->id)
                ->whereNotNull('agent_id')
                ->get(['name', 'agent_id']);

            foreach ($instances as $instance) {
                DB::table('leads')
                    ->where('tenant_id', (string) $user->id)
                    ->where('evolution_instance', $instance->name)
                    ->whereNull('agent_id')
                    ->update(['agent_id' => $instance->agent_id]);
            }
        }

        $fallbackAgentId = DB::table('agents')->orderBy('id')->value('id');
        if ($fallbackAgentId) {
            DB::table('leads')->whereNull('agent_id')->update(['agent_id' => $fallbackAgentId]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('whatsapp_instances')->update(['agent_id' => null]);
        DB::table('leads')->update(['agent_id' => null]);
        DB::table('agent_configs')->delete();
        DB::table('agents')->delete();
    }

    private function legacyConfig(int $userId): array
    {
        return [
            'agent_name' => $this->setting('agent_name', 'ARIA', $userId),
            'company_name' => $this->setting('company_name', 'Amec', $userId),
            'agent_personality' => $this->setting('agent_personality', 'direta, acolhedora e profissional', $userId),
            'max_chars' => (int) $this->setting('max_chars', '300', $userId),
            'agent_greeting' => $this->setting('agent_greeting', 'Cumprimente pelo nome e apresente-se como consultora da empresa', $userId),
            'required_docs' => $this->setting('required_docs', 'RG/CNH, comprovante de residência, dados bancários (banco/agência/conta)', $userId),
            'extra_rules' => $this->setting('extra_rules', '', $userId),
            'agent_provider' => $this->setting('agent_provider', 'openai', $userId),
            'agent_model' => $this->setting('agent_model', 'gpt-4o-mini', $userId),
            'transcription_provider' => $this->setting('transcription_provider', 'openai', $userId),
            'transcription_model' => $this->setting('transcription_model', 'whisper-1', $userId),
            'vision_provider' => $this->setting('vision_provider', 'openai', $userId),
            'vision_model' => $this->setting('vision_model', 'gpt-4o', $userId),
            'escalation_whatsapp_number' => $this->setting('escalation_whatsapp_number', '', $userId),
            'temperature' => (float) $this->setting('temperature', '0.4', $userId),
            'max_tokens' => (int) $this->setting('max_tokens', '1024', $userId),
            'max_conversation_messages' => (int) $this->setting('max_conversation_messages', '24', $userId),
            'followup_first_delay_minutes' => (int) $this->setting('followup_first_delay_minutes', '10', $userId),
            'followup_daily_time' => $this->setting('followup_daily_time', '10:00', $userId),
            'followup_max_count' => (int) $this->setting('followup_max_count', '4', $userId),
            'followup_approach' => $this->setting('followup_approach', 'natural', $userId),
        ];
    }

    private function setting(string $key, string $default, int $userId): string
    {
        $value = DB::table('app_settings')
            ->where('key', $key)
            ->where('user_id', $userId)
            ->value('value');

        if ($value === null) {
            $value = DB::table('app_settings')
                ->where('key', $key)
                ->whereNull('user_id')
                ->value('value');
        }

        return (string) ($value ?? $default);
    }
};
