<?php

namespace Database\Seeders;

use App\Models\ConversationTimelineMessage;
use App\Models\Lead;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Database\Seeder;

class UatSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::with('tenants')->whereHas('tenants', fn ($q) => $q->where('role', 'owner'))->first();
        if (! $user) {
            $this->command?->error('No owner user found');

            return;
        }
        $tenant = $user->tenants->first();
        $tenantId = (string) $tenant->id;

        $tag = Tag::withTrashed()->firstOrCreate(
            ['tenant_id' => $tenantId, 'slug' => 'sem-documentos'],
            [
                'name' => 'Sem documentos',
                'color' => 'orange',
                'ai_detectable' => true,
                'ai_description' => 'Cliente alegou nao ter documentos disponiveis (CNH, RG, comprovante).',
                'ai_min_confidence' => 0.70,
                'created_by' => $user->id,
            ]
        );

        $lead = Lead::create([
            'tenant_id' => $tenantId,
            'whatsapp' => '5511999'.random_int(100000, 999999),
            'nome' => 'Joao da Silva (UAT)',
            'cpf' => '12345678900',
            'idade' => 42,
            'status' => 'novo',
            'modo' => 'receptivo',
            'operational_stage' => 'new_inbound',
            'is_sandbox' => false,
        ]);

        $messages = [
            ['inbound', 'user', 'oi quero simular um emprestimo'],
            ['outbound', 'assistant', 'Claro! Pode me enviar foto da sua CNH?'],
            ['inbound', 'user', 'nao tenho CNH nem RG aqui agora'],
            ['outbound', 'assistant', 'Sem problemas, depois envia. Posso prosseguir.'],
            ['inbound', 'user', 'ok, qual o proximo passo?'],
        ];

        foreach ($messages as [$dir, $sender, $body]) {
            ConversationTimelineMessage::create([
                'tenant_id' => $tenantId,
                'lead_id' => $lead->id,
                'direction' => $dir,
                'sender_type' => $sender,
                'channel' => 'whatsapp',
                'body' => $body,
                'status' => 'delivered',
                'source' => 'uat',
            ]);
        }

        $this->command?->info('UAT seed complete:');
        $this->command?->info("  tenant_id={$tenantId}");
        $this->command?->info("  lead_id={$lead->id}");
        $this->command?->info("  tag_id={$tag->id}");
        $this->command?->info("  lead_url=/conversas/{$lead->id}");
    }
}
