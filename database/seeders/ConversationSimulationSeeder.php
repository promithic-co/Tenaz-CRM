<?php

namespace Database\Seeders;

use App\Models\Agent;
use App\Models\Lead;
use App\Models\User;
use App\Models\WhatsappInstance;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Seeds one lead with a conversation and sample messages per user, so whoever
 * is logged in can open /conversas and see their lead to test the chat panel.
 *
 * Run: php artisan db:seed --class=ConversationSimulationSeeder
 */
class ConversationSimulationSeeder extends Seeder
{
    public function run(): void
    {
        $users = User::query()->get();
        if ($users->isEmpty()) {
            $this->command->warn('No user found. Run php artisan db:seed first to create an admin user.');

            return;
        }

        foreach ($users as $user) {
            $this->seedLeadForUser($user);
        }

        $this->command->info('Conversation simulation ready. Open /conversas and click a lead to test the chat panel.');
    }

    private function seedLeadForUser(User $user): void
    {
        $agent = Agent::query()->where('user_id', $user->id)->first();
        if (! $agent) {
            $agent = Agent::create([
                'user_id' => $user->id,
                'name' => 'Agente Simulação',
                'slug' => 'agente-simulacao-'.Str::random(6),
                'is_active' => true,
                'is_default' => true,
            ]);
        } else {
            $agent->update(['is_default' => true]);
        }

        $existingLead = Lead::query()
            ->where('tenant_id', $user->tenantId)
            ->where('whatsapp', '5511987654003')
            ->first();

        $conversationId = $existingLead?->conversation_id ?: (string) Str::uuid();
        $now = now();
        $instanceValues = [
            'user_id' => $user->id,
            'agent_id' => $agent->id,
            'display_name' => 'Tenaz CRM Demo',
            'api_url' => 'https://evolution.local',
            'api_key' => 'local-demo',
        ];

        if (Schema::hasColumn('whatsapp_instances', 'default_ai_mode')) {
            $instanceValues['default_ai_mode'] = 'automatic';
        }

        $instance = WhatsappInstance::query()->updateOrCreate(
            [
                'tenant_id' => $user->tenantId,
                'name' => 'tenaz-crm-demo',
            ],
            $instanceValues,
        );

        $leadValues = [
            'agent_id' => $agent->id,
            'nome' => 'Ana Paula Ferreira',
            'status' => 'escalado',
            'modo' => 'receptivo',
            'is_sandbox' => false,
            'conversation_id' => $conversationId,
            'last_interaction_at' => $now,
            'evolution_instance' => $instance->name,
            'followup_status' => 'paused',
        ];

        if (Schema::hasColumn('leads', 'operational_stage')) {
            $leadValues['operational_stage'] = 'human_pending';
        }

        $lead = Lead::query()->updateOrCreate(
            [
                'tenant_id' => $user->tenantId,
                'whatsapp' => '5511987654003',
            ],
            $leadValues,
        );

        DB::table('agent_conversations')->insertOrIgnore([
            'id' => $conversationId,
            'user_id' => $user->id,
            'title' => $lead->nome ?? $lead->whatsapp,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $messages = [
            ['role' => 'user', 'content' => 'Oi, recebi a mensagem sobre a proposta. Pode me explicar melhor?', 'agent' => 'user'],
            ['role' => 'assistant', 'content' => 'Claro, Ana. Eu vou verificar as informacoes e te orientar nos proximos passos.', 'agent' => 'assistant'],
            ['role' => 'user', 'content' => 'Prefiro falar com uma pessoa para finalizar.', 'agent' => 'user'],
        ];

        $exists = DB::table('agent_conversation_messages')
            ->where('conversation_id', $conversationId)
            ->exists();

        if (! $exists) {
            foreach ($messages as $i => $m) {
                DB::table('agent_conversation_messages')->insert([
                    'id' => (string) Str::uuid(),
                    'conversation_id' => $conversationId,
                    'user_id' => $m['role'] === 'user' ? null : $user->id,
                    'agent' => $m['agent'],
                    'role' => $m['role'],
                    'content' => $m['content'],
                    'attachments' => '',
                    'tool_calls' => '',
                    'tool_results' => '',
                    'usage' => '',
                    'meta' => '',
                    'created_at' => $now->copy()->addSeconds($i),
                    'updated_at' => $now->copy()->addSeconds($i),
                ]);
            }
        }

        if (Schema::hasTable('conversation_timeline_messages')) {
            $timelineExists = DB::table('conversation_timeline_messages')
                ->where('lead_id', $lead->id)
                ->where('conversation_id', $conversationId)
                ->exists();

            if (! $timelineExists) {
                foreach ($messages as $i => $m) {
                    DB::table('conversation_timeline_messages')->insert([
                        'tenant_id' => (string) $user->tenantId,
                        'lead_id' => $lead->id,
                        'conversation_id' => $conversationId,
                        'direction' => $m['role'] === 'user' ? 'inbound' : 'outbound',
                        'sender_type' => $m['role'] === 'user' ? 'lead' : 'ai',
                        'channel' => 'whatsapp',
                        'body' => $m['content'],
                        'media' => null,
                        'status' => $m['role'] === 'user' ? 'received' : 'sent',
                        'source' => 'seed',
                        'created_at' => $now->copy()->addSeconds($i),
                        'updated_at' => $now->copy()->addSeconds($i),
                    ]);
                }
            }
        }
    }
}
