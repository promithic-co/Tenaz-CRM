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
        // The bound also guards the User tenancy memos. Adding the "envios" tab had
        // pushed this to 46, because the sidebar runs one count per tab and each
        // rebuilt the visibility scope, re-running tenants()->first() and the role
        // lookup on every access — roughly 30 of the 43 queries were those two.
        // Both are memoized per instance now, so a tab costs its own count query and
        // nothing else, and the bound is back to guarding what it was meant to:
        // that the cost does not scale with lead count.
        expect(count($queries))->toBeLessThan(18);
    });
});
