<?php

use App\Http\Requests\UpdateConversationSessionValueRequest;
use App\Models\Agent;
use App\Models\ConversationSession;
use App\Models\Lead;
use App\Models\User;
use App\Services\Dashboard\DashboardMetricsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

/**
 * @return array{0: User, 1: Lead, 2: ConversationSession}
 */
function leadWithOpenSession(): array
{
    $user = User::factory()->create();
    $agent = Agent::factory()->create(['user_id' => $user->id, 'is_default' => true]);
    $lead = Lead::factory()->forAgent($agent)->create();
    $session = ConversationSession::factory()->forLead($lead)->open()->create(['number' => 1]);

    return [$user, $lead, $session];
}

test('update stores the amount and the forecast date', function () {
    [$user, $lead, $session] = leadWithOpenSession();

    $this->actingAs($user)
        ->patch(route('conversas.sessions.value.update', [$lead, $session]), [
            'value_cents' => 350_075,
            'expected_close_at' => '2026-08-15',
        ])
        ->assertRedirect();

    $session->refresh();

    expect($session->value_cents)->toBe(350_075)
        ->and($session->expected_close_at?->toDateString())->toBe('2026-08-15');
});

test('update clears both fields when they arrive null', function () {
    [$user, $lead, $session] = leadWithOpenSession();
    $session->update(['value_cents' => 1000, 'expected_close_at' => '2026-08-01']);

    $this->actingAs($user)
        ->patch(route('conversas.sessions.value.update', [$lead, $session]), [
            'value_cents' => null,
            'expected_close_at' => null,
        ])
        ->assertRedirect();

    $session->refresh();

    expect($session->value_cents)->toBeNull()
        ->and($session->expected_close_at)->toBeNull();
});

/**
 * What the browser actually sends when the operator empties the fields: `<input type="date">`
 * and the masked text field both clear to '', not to null. If empty strings did not survive
 * the round trip, clearing a value in the UI would fail validation instead of unpricing it.
 */
test('update clears both fields when they arrive as empty strings', function () {
    [$user, $lead, $session] = leadWithOpenSession();
    $session->update(['value_cents' => 1000, 'expected_close_at' => '2026-08-01']);

    $this->actingAs($user)
        ->patch(route('conversas.sessions.value.update', [$lead, $session]), [
            'value_cents' => '',
            'expected_close_at' => '',
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $session->refresh();

    expect($session->value_cents)->toBeNull()
        ->and($session->expected_close_at)->toBeNull();
});

test('update rejects a negative amount', function () {
    [$user, $lead, $session] = leadWithOpenSession();

    $this->actingAs($user)
        ->patch(route('conversas.sessions.value.update', [$lead, $session]), [
            'value_cents' => -1,
            'expected_close_at' => null,
        ])
        ->assertSessionHasErrors('value_cents');

    expect($session->fresh()->value_cents)->toBeNull();
});

/**
 * The ceiling is what catches a mask bug: a stray multiplication turns R$ 5.000 into a
 * nine-figure amount that would then be summed into the dashboard as if it were real.
 */
test('update rejects an amount above the ceiling', function () {
    [$user, $lead, $session] = leadWithOpenSession();

    $this->actingAs($user)
        ->patch(route('conversas.sessions.value.update', [$lead, $session]), [
            'value_cents' => UpdateConversationSessionValueRequest::MAX_VALUE_CENTS + 1,
            'expected_close_at' => null,
        ])
        ->assertSessionHasErrors('value_cents');
});

test('update rejects a decimal amount', function () {
    [$user, $lead, $session] = leadWithOpenSession();

    $this->actingAs($user)
        ->patch(route('conversas.sessions.value.update', [$lead, $session]), [
            'value_cents' => 35.75,
            'expected_close_at' => null,
        ])
        ->assertSessionHasErrors('value_cents');
});

test('update rejects a date that is not Y-m-d', function () {
    [$user, $lead, $session] = leadWithOpenSession();

    $this->actingAs($user)
        ->patch(route('conversas.sessions.value.update', [$lead, $session]), [
            'value_cents' => null,
            'expected_close_at' => '15/08/2026',
        ])
        ->assertSessionHasErrors('expected_close_at');
});

/**
 * A closed atendimento has already been counted into the dashboard's won total. Editing its
 * amount afterwards would restate a historical figure with no record of the change.
 */
test('update refuses a closed atendimento', function () {
    $user = User::factory()->create();
    $agent = Agent::factory()->create(['user_id' => $user->id, 'is_default' => true]);
    $lead = Lead::factory()->forAgent($agent)->create();
    $session = ConversationSession::factory()
        ->forLead($lead)
        ->closed(ConversationSession::OUTCOME_CONVERTED)
        ->create(['number' => 1, 'value_cents' => 50_000]);

    $this->actingAs($user)
        ->patch(route('conversas.sessions.value.update', [$lead, $session]), [
            'value_cents' => 999_999,
            'expected_close_at' => null,
        ])
        ->assertSessionHasErrors('value_cents');

    expect($session->fresh()->value_cents)->toBe(50_000);
});

test('update refuses a session that belongs to another lead', function () {
    $user = User::factory()->create();
    $agent = Agent::factory()->create(['user_id' => $user->id, 'is_default' => true]);
    $lead = Lead::factory()->forAgent($agent)->create();
    $otherLead = Lead::factory()->forAgent($agent)->create();
    $session = ConversationSession::factory()->forLead($otherLead)->open()->create();

    $this->actingAs($user)
        ->patch(route('conversas.sessions.value.update', [$lead, $session]), [
            'value_cents' => 1000,
            'expected_close_at' => null,
        ])
        ->assertNotFound();

    expect($session->fresh()->value_cents)->toBeNull();
});

test('update is forbidden for a lead in another tenant', function () {
    [, $lead, $session] = leadWithOpenSession();
    $intruder = User::factory()->create();

    $this->actingAs($intruder)
        ->patch(route('conversas.sessions.value.update', [$lead, $session]), [
            'value_cents' => 1000,
            'expected_close_at' => null,
        ])
        ->assertNotFound();

    expect($session->fresh()->value_cents)->toBeNull();
});

test('panel carries the amount and forecast on the session payload', function () {
    [$user, $lead, $session] = leadWithOpenSession();
    $session->update(['value_cents' => 275_050, 'expected_close_at' => '2026-09-01']);

    $this->actingAs($user)
        ->get(route('conversas.show', $lead))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('activeConversation.sessions.0.value_cents', 275_050)
            ->where('activeConversation.sessions.0.expected_close_at', '2026-09-01')
        );
});

test('panel reports a null amount for an unpriced atendimento', function () {
    [$user, $lead] = leadWithOpenSession();

    $this->actingAs($user)
        ->get(route('conversas.show', $lead))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('activeConversation.sessions.0.value_cents', null)
            ->where('activeConversation.sessions.0.expected_close_at', null)
        );
});

test('pipeline card carries the open atendimento amount', function () {
    $user = User::factory()->create();
    $lead = Lead::factory()->create(['tenant_id' => $user->tenantId, 'status' => 'novo']);
    ConversationSession::factory()->forLead($lead)->open()->create([
        'number' => 1,
        'value_cents' => 120_000,
    ]);

    $this->actingAs($user)
        ->get(route('pipeline.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('columns.novo.data.0.value_cents', 120_000)
        );
});

/**
 * A closed session must not price the card. The lead is still on the board, but its cycle is
 * over — showing last month's amount would double-count it against the open pipeline.
 */
test('pipeline card is unpriced when the lead has no open atendimento', function () {
    $user = User::factory()->create();
    $lead = Lead::factory()->create(['tenant_id' => $user->tenantId, 'status' => 'novo']);
    ConversationSession::factory()->forLead($lead)->closed()->create([
        'number' => 1,
        'value_cents' => 120_000,
    ]);

    $this->actingAs($user)
        ->get(route('pipeline.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('columns.novo.data.0.value_cents', null)
        );
});

/**
 * The header total must cover the whole column, not the ~30 cards the page loaded. With 32
 * priced leads a sum over the rendered page would be short by two.
 */
test('pipeline column total covers leads beyond the loaded page', function () {
    $user = User::factory()->create();

    Lead::factory()->count(32)->create([
        'tenant_id' => $user->tenantId,
        'status' => 'novo',
    ])->each(function (Lead $lead): void {
        ConversationSession::factory()->forLead($lead)->open()->create([
            'number' => 1,
            'value_cents' => 10_000,
        ]);
    });

    $this->actingAs($user)
        ->get(route('pipeline.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('columns.novo.count', 32)
            ->where('columns.novo.value_cents', 320_000)
            ->count('columns.novo.data', 30)
        );
});

test('pipeline column total ignores closed atendimentos', function () {
    $user = User::factory()->create();

    $priced = Lead::factory()->create(['tenant_id' => $user->tenantId, 'status' => 'novo']);
    ConversationSession::factory()->forLead($priced)->open()->create([
        'number' => 1,
        'value_cents' => 40_000,
    ]);

    $stale = Lead::factory()->create(['tenant_id' => $user->tenantId, 'status' => 'novo']);
    ConversationSession::factory()->forLead($stale)->closed()->create([
        'number' => 1,
        'value_cents' => 999_000,
    ]);

    $this->actingAs($user)
        ->get(route('pipeline.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('columns.novo.count', 2)
            ->where('columns.novo.value_cents', 40_000)
        );
});

test('dashboard sums open atendimentos into the forecast', function () {
    $tenantId = 'tenant-value-open';

    foreach ([15_000, 25_000] as $cents) {
        $lead = Lead::factory()->create(['tenant_id' => $tenantId]);
        ConversationSession::factory()->forLead($lead)->open()->create([
            'number' => 1,
            'value_cents' => $cents,
        ]);
    }

    $unpriced = Lead::factory()->create(['tenant_id' => $tenantId]);
    ConversationSession::factory()->forLead($unpriced)->open()->create(['number' => 1]);

    $otherTenant = Lead::factory()->create(['tenant_id' => 'tenant-value-other']);
    ConversationSession::factory()->forLead($otherTenant)->open()->create([
        'number' => 1,
        'value_cents' => 999_000,
    ]);

    Cache::flush();

    $atendimentos = app(DashboardMetricsService::class)->snapshot($tenantId)['atendimentos'];

    expect($atendimentos['open_value_cents'])->toBe(40_000);
});

/**
 * Won means `converted`. A lost cycle can carry an amount too — it was negotiated before it
 * fell through — and counting it would inflate the week's revenue.
 */
test('dashboard counts only converted atendimentos as won', function () {
    $tenantId = 'tenant-value-won';

    $won = Lead::factory()->create(['tenant_id' => $tenantId]);
    ConversationSession::factory()->forLead($won)->closed(ConversationSession::OUTCOME_CONVERTED)->create([
        'number' => 1,
        'value_cents' => 80_000,
        'closed_at' => now()->subDay(),
    ]);

    $lost = Lead::factory()->create(['tenant_id' => $tenantId]);
    ConversationSession::factory()->forLead($lost)->closed(ConversationSession::OUTCOME_LOST)->create([
        'number' => 1,
        'value_cents' => 500_000,
        'closed_at' => now()->subDay(),
    ]);

    $old = Lead::factory()->create(['tenant_id' => $tenantId]);
    ConversationSession::factory()->forLead($old)->closed(ConversationSession::OUTCOME_CONVERTED)->create([
        'number' => 1,
        'value_cents' => 700_000,
        'closed_at' => now()->subDays(10),
    ]);

    Cache::flush();

    $atendimentos = app(DashboardMetricsService::class)->snapshot($tenantId)['atendimentos'];

    expect($atendimentos['won_value_7d_cents'])->toBe(80_000);
});
