<?php

namespace Database\Seeders;

use App\Enums\TaggableSource;
use App\Models\Contact;
use App\Models\Lead;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Seeds UAT data for Phase 51 smart list verification.
 *
 * Creates leads and contacts that exercise every filter field so the
 * three browser UAT scenarios (tag_source, D-02 redirect, wizard live-count)
 * have meaningful data to work against.
 *
 * Run: php artisan db:seed --class=SmartListUatSeeder
 */
class SmartListUatSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::with('tenants')
            ->whereHas('tenants', fn ($q) => $q->where('role', 'owner'))
            ->first();

        if (! $user) {
            $this->command?->error('No owner user found. Run DatabaseSeeder first.');

            return;
        }

        $tenant = $user->tenants->first();
        $tenantId = (string) $tenant->id;

        $tags = $this->seedTags($tenantId, $user->id);

        $this->seedLeads($tenantId, $user->id, $tags);
        $this->seedContacts($tenantId);

        $this->command?->newLine();
        $this->command?->info('SmartList UAT seed complete.');
        $this->command?->info("  tenant_id={$tenantId}");
        $this->command?->info('  Leads created: 30 (25 production, 5 opt-out)');
        $this->command?->info('  Contacts created: 20 (15 opted-in, 5 opted-out)');
        $this->command?->newLine();
        $this->command?->info('UAT scenarios:');
        $this->command?->info('  1. source=leads, tag=vip, status=qualificado → expect ~8 leads');
        $this->command?->info('  2. source=leads, tag_source=import → expect ~6 leads');
        $this->command?->info('  3. source=contacts, opt_in_status=opted_in → expect 15 contacts');
        $this->command?->info('  Opt-out leads/contacts must never appear in any preview.');
    }

    /** @return array<string, Tag> */
    private function seedTags(string $tenantId, int $userId): array
    {
        $defs = [
            'vip' => ['name' => 'VIP',             'color' => 'yellow', 'is_hot' => true],
            'inativo' => ['name' => 'Inativo',         'color' => 'gray',   'is_hot' => false],
            'interessado' => ['name' => 'Interessado',    'color' => 'green',  'is_hot' => false],
            'sem-docs' => ['name' => 'Sem documentos',  'color' => 'orange', 'is_hot' => false],
        ];

        $tags = [];
        foreach ($defs as $slug => $attrs) {
            $tag = Tag::withTrashed()->firstOrCreate(
                ['tenant_id' => $tenantId, 'slug' => $slug],
                array_merge($attrs, ['created_by' => $userId])
            );

            if ($tag->trashed()) {
                $tag->restore();
            }

            $tags[$slug] = $tag;
        }

        return $tags;
    }

    /** @param array<string, Tag> $tags */
    private function seedLeads(string $tenantId, int $userId, array $tags): void
    {
        $statuses = ['novo', 'em_atendimento', 'qualificado', 'perdido', 'escalado'];

        // 8 qualified VIP leads (manual tag) — hit "tag=vip + status=qualificado" filter
        for ($i = 0; $i < 8; $i++) {
            $lead = Lead::create([
                'tenant_id' => $tenantId,
                'whatsapp' => '5511'.random_int(900000000, 999999999),
                'nome' => fake()->name().' (UAT-vip-qual)',
                'status' => 'qualificado',
                'modo' => 'receptivo',
                'operational_stage' => 'human_pending',
                'is_sandbox' => false,
                'last_interaction_at' => now()->subDays(rand(5, 30)),
            ]);
            $lead->attachTag($tags['vip'], TaggableSource::Manual, $userId);
        }

        // 6 leads tagged via import — hit "tag_source=import" filter (UAT scenario 2)
        for ($i = 0; $i < 6; $i++) {
            $lead = Lead::create([
                'tenant_id' => $tenantId,
                'whatsapp' => '5511'.random_int(900000000, 999999999),
                'nome' => fake()->name().' (UAT-import)',
                'status' => $statuses[array_rand($statuses)],
                'modo' => 'ativo',
                'operational_stage' => 'human_pending',
                'is_sandbox' => false,
                'last_interaction_at' => now()->subDays(rand(1, 15)),
            ]);
            $lead->attachTag($tags['interessado'], TaggableSource::Import, $userId);
        }

        // 5 leads tagged by AI
        for ($i = 0; $i < 5; $i++) {
            $lead = Lead::create([
                'tenant_id' => $tenantId,
                'whatsapp' => '5511'.random_int(900000000, 999999999),
                'nome' => fake()->name().' (UAT-ai)',
                'status' => 'novo',
                'modo' => 'receptivo',
                'operational_stage' => 'new_inbound',
                'is_sandbox' => false,
                'last_interaction_at' => now()->subDays(rand(2, 60)),
            ]);
            $lead->attachTag($tags['sem-docs'], TaggableSource::Ai, $userId);
        }

        // 6 inactive leads (last_interaction_at > 45 days) — hit "older_than_days=45" filter
        for ($i = 0; $i < 6; $i++) {
            $lead = Lead::create([
                'tenant_id' => $tenantId,
                'whatsapp' => '5511'.random_int(900000000, 999999999),
                'nome' => fake()->name().' (UAT-inativo)',
                'status' => $statuses[array_rand($statuses)],
                'modo' => 'receptivo',
                'operational_stage' => 'human_pending',
                'is_sandbox' => false,
                'last_interaction_at' => now()->subDays(rand(46, 120)),
            ]);
            $lead->attachTag($tags['inativo'], TaggableSource::System, $userId);
        }

        // 5 opted-out leads — must NEVER appear in any smart list preview
        for ($i = 0; $i < 5; $i++) {
            Lead::create([
                'tenant_id' => $tenantId,
                'whatsapp' => '5511'.random_int(900000000, 999999999),
                'nome' => fake()->name().' (UAT-optou-sair)',
                'status' => 'optou_sair',
                'modo' => 'receptivo',
                'operational_stage' => 'human_pending',
                'is_sandbox' => false,
                'last_interaction_at' => now()->subDays(rand(1, 10)),
            ]);
        }
    }

    private function seedContacts(string $tenantId): void
    {
        // 15 opted-in contacts — hit "opt_in_status=opted_in" filter (UAT scenario 3)
        for ($i = 0; $i < 15; $i++) {
            Contact::create([
                'tenant_id' => $tenantId,
                'name' => fake()->name().' (UAT-opted-in)',
                'phone' => '5511'.random_int(900000000, 999999999),
                'source' => Contact::SOURCE_MANUAL,
                'opt_in_status' => Contact::OPT_IN,
                'opt_in_at' => now()->subDays(rand(1, 90)),
                'last_seen_at' => now()->subDays(rand(1, 30)),
            ]);
        }

        // 5 opted-out contacts — must NEVER appear in smart list preview
        for ($i = 0; $i < 5; $i++) {
            Contact::create([
                'tenant_id' => $tenantId,
                'name' => fake()->name().' (UAT-opted-out)',
                'phone' => '5511'.random_int(900000000, 999999999),
                'source' => Contact::SOURCE_MANUAL,
                'opt_in_status' => Contact::OPT_OUT,
                'opt_out_at' => now()->subDays(rand(1, 30)),
            ]);
        }
    }
}
