<?php

namespace Database\Seeders;

use App\Enums\TenantRole;
use App\Models\Agent;
use App\Models\ConversationSession;
use App\Models\ConversationTimelineMessage;
use App\Models\Lead;
use App\Models\ServiceTicket;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WhatsappInstance;
use Carbon\CarbonInterface;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * A self-contained demo tenant for eyeballing /conversas: one owner, one seller,
 * two WhatsApp instances and enough leads to populate every inbox tab (fila,
 * minhas, ia) plus the rows that only "todas" reaches.
 *
 * Re-running wipes and rebuilds only this seeder's own tenant.
 *
 * Run: php artisan db:seed --class=InboxDemoSeeder
 */
class InboxDemoSeeder extends Seeder
{
    private const OWNER_EMAIL = 'demo@tenaz.local';

    private const SELLER_EMAIL = 'vendedor@tenaz.local';

    private const PASSWORD = 'demo1234';

    public function run(): void
    {
        /**
         * This seeder creates real login accounts with a hard-coded password.
         * Running it against a live database would hand anyone who reads this
         * file an owner account, so it is refused outside local/testing.
         */
        if (! app()->environment(['local', 'testing'])) {
            $this->command->error('InboxDemoSeeder cria contas com senha fixa e so roda em local/testing.');

            return;
        }

        $owner = User::query()->where('email', self::OWNER_EMAIL)->first();
        $tenant = $owner?->tenants()->first() ?? Tenant::create(['name' => 'Demo Vendas']);

        $owner = $this->upsertUser(self::OWNER_EMAIL, 'Ricardo Demo', $tenant, TenantRole::Owner);
        $seller = $this->upsertUser(self::SELLER_EMAIL, 'Juliana Santos', $tenant, TenantRole::User);

        $tenantId = (string) $tenant->id;

        $this->wipe($tenantId);

        $agent = Agent::query()->updateOrCreate(
            ['tenant_id' => $tenantId, 'slug' => 'agente-demo'],
            ['user_id' => $owner->id, 'name' => 'Agente Demo', 'is_active' => true, 'is_default' => true],
        );

        $principal = $this->instance($tenantId, $owner, $agent, 'demo-principal', 'Comercial Principal');
        $secondary = $this->instance($tenantId, $owner, $agent, 'demo-secundaria', 'Consignado SP');

        $this->seedQueue($tenantId, $agent, $principal);
        $this->seedMine($tenantId, $agent, $principal, $secondary, $owner);
        $this->seedAi($tenantId, $agent, $principal, $secondary);
        $this->seedOthers($tenantId, $agent, $secondary, $seller);

        $this->command->info('Inbox demo pronto.');
        $this->command->info('  Owner:    '.self::OWNER_EMAIL.' / '.self::PASSWORD);
        $this->command->info('  Vendedor: '.self::SELLER_EMAIL.' / '.self::PASSWORD);
    }

    private function upsertUser(string $email, string $name, Tenant $tenant, TenantRole $role): User
    {
        $user = User::query()->updateOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'password' => Hash::make(self::PASSWORD),
                'email_verified_at' => now(),
                'onboarded_at' => now(),
            ],
        );

        $user->tenants()->syncWithoutDetaching([$tenant->id => ['role' => $role->value]]);

        return $user;
    }

    /** Clears the previous run so the demo tenant always shows the exact set below. */
    private function wipe(string $tenantId): void
    {
        $leadIds = Lead::withTrashed()->where('tenant_id', $tenantId)->pluck('id');

        ConversationTimelineMessage::query()->whereIn('lead_id', $leadIds)->delete();
        ServiceTicket::withTrashed()->whereIn('lead_id', $leadIds)->forceDelete();
        ConversationSession::withTrashed()->whereIn('lead_id', $leadIds)->forceDelete();
        Lead::withTrashed()->whereIn('id', $leadIds)->forceDelete();
    }

    private function instance(string $tenantId, User $owner, Agent $agent, string $name, string $label): WhatsappInstance
    {
        return WhatsappInstance::query()->updateOrCreate(
            ['tenant_id' => $tenantId, 'name' => $name],
            [
                'user_id' => $owner->id,
                'agent_id' => $agent->id,
                'display_name' => $label,
                'api_url' => 'https://graph.facebook.com',
                'api_key' => '',
                'meta_phone_number_id' => 'demo-phone-'.$name,
                'meta_waba_id' => 'demo-waba-'.$name,
                'meta_access_token' => 'demo-token',
                'meta_token_permanent' => true,
                'default_ai_mode' => Lead::AI_MODE_AUTOMATIC,
            ],
        );
    }

    /**
     * Unclaimed escalations. The wait times differ so the queue's oldest-first
     * ordering is visible at a glance.
     */
    private function seedQueue(string $tenantId, Agent $agent, WhatsappInstance $instance): void
    {
        $queue = [
            ['Marcos Vinicius Alves', '5511990010001', 190, [
                ['outbound', 'Oi Marcos! Vi que voce tem margem disponivel para consignado. Quer simular?'],
                ['inbound', 'Quero sim. Quanto consigo?'],
                ['outbound', 'Pelo seu perfil, ate R$ 18.400 em 84x. Posso seguir com a proposta?'],
                ['inbound', 'Prefiro falar com um atendente antes de fechar.'],
            ]],
            ['Sandra Regina Lopes', '5511990010002', 75, [
                ['inbound', 'Boa tarde, recebi uma ligacao sobre emprestimo. E verdade?'],
                ['outbound', 'Boa tarde Sandra! Sim, temos uma condicao liberada para o seu CPF.'],
                ['inbound', 'Nao entendi as taxas, pode me passar pra alguem?'],
            ]],
            ['Eduardo Nakamura', '5511990010003', 20, [
                ['outbound', 'Eduardo, sua proposta de portabilidade saiu com taxa menor. Confere?'],
                ['inbound', 'Isso muda a parcela? Quero falar com o gerente.'],
            ]],
        ];

        foreach ($queue as [$name, $phone, $minutesAgo, $messages]) {
            $lead = $this->lead($tenantId, $agent, $instance, [
                'nome' => $name,
                'whatsapp' => $phone,
                'status' => 'escalado',
                'operational_stage' => Lead::STAGE_HUMAN_PENDING,
                'assigned_user_id' => null,
                'last_interaction_at' => now()->subMinutes($minutesAgo),
            ]);

            $this->messages($lead, $messages, now()->subMinutes($minutesAgo));

            ServiceTicket::create([
                'tenant_id' => $tenantId,
                'lead_id' => $lead->id,
                'type' => ServiceTicket::TYPE_ESCALATION,
                'status' => ServiceTicket::STATUS_OPEN,
                'priority' => ServiceTicket::PRIORITY_NORMAL,
                'reason' => 'solicitacao_cliente',
                'summary' => 'Cliente pediu para falar com um atendente humano.',
                'sla_due_at' => now()->subMinutes($minutesAgo)->addHours(4),
            ]);
        }
    }

    /** Conversations already owned by the logged-in owner. */
    private function seedMine(
        string $tenantId,
        Agent $agent,
        WhatsappInstance $principal,
        WhatsappInstance $secondary,
        User $owner,
    ): void {
        $mine = [
            ['Camila Duarte', '5511990020001', $principal, 6, 'qualificado', false, [
                ['outbound', 'Camila, anexei a simulacao das 3 opcoes. Qual faz mais sentido?'],
                ['inbound', 'Gostei da segunda. O que preciso enviar?'],
            ]],
            ['Fernando Rocha', '5511990020002', $principal, 42, 'qualificado', false, [
                ['inbound', 'Ja mandei os documentos ontem, tem novidade?'],
            ]],
            ['Patricia Amorim', '5511990020003', $secondary, 130, 'novo', false, [
                ['inbound', 'Bom dia! Vi o anuncio de consignado INSS.'],
                ['outbound', 'Bom dia Patricia! Me confirma seu CPF que eu ja consulto a margem.'],
            ]],
            ['Helio Barreto', '5511990020004', $secondary, 300, 'qualificado', true, [
                ['inbound', 'Pode me ligar amanha de manha?'],
                ['outbound', 'Combinado Helio, te ligo as 9h. Deixei a IA pausada aqui.'],
            ]],
        ];

        foreach ($mine as [$name, $phone, $instance, $minutesAgo, $status, $paused, $messages]) {
            $lead = $this->lead($tenantId, $agent, $instance, [
                'nome' => $name,
                'whatsapp' => $phone,
                'status' => $status,
                'operational_stage' => Lead::STAGE_HUMAN_ACTIVE,
                'assigned_user_id' => $owner->id,
                'last_interaction_at' => now()->subMinutes($minutesAgo),
                'ai_paused_until' => $paused ? now()->addHours(8) : null,
                'ai_paused_reason' => $paused ? 'manual' : null,
                'service_window_expires_at' => now()->subMinutes($minutesAgo)->addDay(),
            ]);

            $this->messages($lead, $messages, now()->subMinutes($minutesAgo));

            ServiceTicket::create([
                'tenant_id' => $tenantId,
                'lead_id' => $lead->id,
                'assigned_user_id' => $owner->id,
                'type' => ServiceTicket::TYPE_ESCALATION,
                'status' => ServiceTicket::STATUS_ASSIGNED,
                'priority' => ServiceTicket::PRIORITY_NORMAL,
                'reason' => 'proposta_aceita',
                'claimed_at' => now()->subMinutes($minutesAgo + 10),
                'sla_due_at' => now()->addHours(2),
            ]);
        }
    }

    /** Nobody human involved: the AI is still working these. */
    private function seedAi(
        string $tenantId,
        Agent $agent,
        WhatsappInstance $principal,
        WhatsappInstance $secondary,
    ): void {
        $ai = [
            ['Renata Belchior', '5511990030001', $principal, 3, 'novo', false, [
                ['outbound', 'Renata, tudo bem? Sou a assistente da Tenaz. Voce tem interesse em antecipar o FGTS?'],
                ['inbound', 'Tenho sim! Como funciona?'],
            ]],
            ['Joao Pedro Miranda', '5511990030002', $principal, 55, 'qualificado', false, [
                ['inbound', 'Meu CPF e 000.000.000-00'],
                ['outbound', 'Obrigada Joao! Consultei aqui: sua margem livre e de R$ 412,00 por mes.'],
            ]],
            ['Cleusa Martins', '5511990030003', $secondary, 240, 'novo', true, [
                ['outbound', 'Cleusa, retomando nosso contato de marco. A condicao voltou a ficar disponivel.'],
                ['inbound', 'Ah, eu ja tinha desistido. Me explica de novo?'],
            ]],
            ['Anderson Prates', '5511990030004', $secondary, 600, 'qualificado', false, [
                ['outbound', 'Anderson, precisamos do comprovante de residencia para seguir.'],
            ]],
            ['Beatriz Nogueira', '5511990030005', $principal, 1500, 'optou_sair', false, [
                ['outbound', 'Beatriz, temos uma nova condicao para voce.'],
                ['inbound', 'Nao quero mais receber mensagens, por favor me remova.'],
            ]],
        ];

        foreach ($ai as [$name, $phone, $instance, $minutesAgo, $status, $returning, $messages]) {
            $lead = $this->lead($tenantId, $agent, $instance, [
                'nome' => $name,
                'whatsapp' => $phone,
                'status' => $status,
                'operational_stage' => Lead::STAGE_AI_QUALIFYING,
                'assigned_user_id' => null,
                'last_interaction_at' => now()->subMinutes($minutesAgo),
            ]);

            $this->messages($lead, $messages, now()->subMinutes($minutesAgo));

            if ($returning) {
                ConversationSession::create([
                    'tenant_id' => $tenantId,
                    'lead_id' => $lead->id,
                    'number' => 2,
                    'status' => ConversationSession::STATUS_OPEN,
                    'open_reason' => ConversationSession::OPEN_REASON_REENGAGEMENT_AFTER_INACTIVITY,
                    'opened_at' => now()->subMinutes($minutesAgo),
                    'last_message_at' => now()->subMinutes($minutesAgo),
                ]);
            }
        }
    }

    /** Owned by a teammate — only "todas" surfaces these. */
    private function seedOthers(string $tenantId, Agent $agent, WhatsappInstance $instance, User $seller): void
    {
        $others = [
            ['Vanessa Coutinho', '5511990040001', 25, [
                ['inbound', 'A Juliana pediu pra eu mandar o print do extrato, segue.'],
            ]],
            ['Rogerio Lima', '5511990040002', 480, [
                ['outbound', 'Rogerio, contrato assinado! Obrigada pela confianca.'],
            ]],
        ];

        foreach ($others as [$name, $phone, $minutesAgo, $messages]) {
            $lead = $this->lead($tenantId, $agent, $instance, [
                'nome' => $name,
                'whatsapp' => $phone,
                'status' => 'qualificado',
                'operational_stage' => Lead::STAGE_HUMAN_ACTIVE,
                'assigned_user_id' => $seller->id,
                'last_interaction_at' => now()->subMinutes($minutesAgo),
            ]);

            $this->messages($lead, $messages, now()->subMinutes($minutesAgo));
        }
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function lead(string $tenantId, Agent $agent, WhatsappInstance $instance, array $attributes): Lead
    {
        return Lead::create(array_merge([
            'tenant_id' => $tenantId,
            'agent_id' => $agent->id,
            'whatsapp_instance_id' => $instance->id,
            'evolution_instance' => $instance->name,
            'modo' => 'receptivo',
            'is_sandbox' => false,
            'followup_status' => 'inactive',
            'followup_count' => 0,
        ], $attributes));
    }

    /**
     * Writes the exchange ending at $lastAt, one minute apart, so the sidebar
     * preview shows the final line and the awaiting-reply marker is honest.
     *
     * @param  list<array{0: string, 1: string}>  $messages
     */
    private function messages(Lead $lead, array $messages, CarbonInterface $lastAt): void
    {
        $count = count($messages);

        foreach ($messages as $index => [$direction, $body]) {
            $at = $lastAt->copy()->subMinutes($count - 1 - $index);

            $message = ConversationTimelineMessage::create([
                'tenant_id' => (string) $lead->tenant_id,
                'lead_id' => $lead->id,
                'direction' => $direction,
                'sender_type' => $direction === 'inbound' ? 'lead' : 'ai',
                'channel' => 'whatsapp',
                'body' => $body,
                'status' => $direction === 'inbound' ? 'received' : 'sent',
                'source' => 'seed',
            ]);

            $message->forceFill(['created_at' => $at, 'updated_at' => $at])->saveQuietly();
        }
    }
}
