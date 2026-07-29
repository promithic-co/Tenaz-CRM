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

        // Act as a freshly hydrated instance: the setup above already read
        // tenantId, and reusing that instance would hide the one-time tenant and
        // role lookups behind a warm memo, undercounting what production pays.
        $this->actingAs(User::findOrFail($user->id))
            ->get('/conversas')
            ->assertOk();

        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        // 25 leads attached to 1–2 tags each; without eager loading this would
        // exceed 50 queries (one per lead). With eager loading we expect a
        // bounded number — session/auth + paginated leads select + a single
        // eager-loaded tags select + ancillary lookups.
        //
        // The bound also guards the User tenancy memos: this route used to issue
        // 39 queries, 20 of them the first-tenant pivot select re-run on every
        // visibility-scope evaluation and 7 more the per-check role lookup.
        expect(count($queries))->toBeLessThan(16);
    });
});
