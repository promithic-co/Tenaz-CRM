<?php

use App\Models\Lead;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

describe('Tag eager loading — N+1 prevention', function () {
    test('GET /conversas with many tagged leads executes a bounded number of queries', function () {
        $user = User::factory()->create();
        $tenantId = (string) $user->tenantId;

        $tags = collect(['vip', 'idoso', 'urgente'])->map(
            fn (string $name): Tag => Tag::createForTenant($tenantId, ['name' => $name])
        );

        $leads = Lead::factory()->forTenant($tenantId)->count(25)->create();
        foreach ($leads as $i => $lead) {
            $lead->attachTag($tags[$i % 3]);
            if ($i % 5 === 0) {
                $lead->attachTag($tags[($i + 1) % 3]);
            }
        }

        DB::enableQueryLog();
        DB::flushQueryLog();

        $this->actingAs($user)
            ->get('/conversas')
            ->assertOk();

        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        // 25 leads attached to 1–2 tags each; without eager loading this would
        // exceed 50 queries (one per lead). With eager loading we expect a
        // bounded number — session/auth + paginated leads select + a single
        // eager-loaded tags select + ancillary lookups.
        //
        // Raised from 40 when the "disparos" tab was added: the sidebar runs one count
        // per tab, and each rebuilds the visibility scope. The count itself is one query;
        // the other three are User::getTenantIdAttribute re-running tenants()->first()
        // on every access, which already accounts for roughly 30 of the queries below
        // and grows with anything that touches the scope. That is the real cost here and
        // it is not what this test guards — it guards against scaling with lead count.
        expect(count($queries))->toBeLessThan(46);
    });
});
