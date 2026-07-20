<?php

use App\Jobs\ProcessLeadFollowUpJob;
use App\Models\ConversationSession;
use App\Models\Lead;
use App\Services\FollowUpSettingsResolver;
use App\Services\FollowUpWindowService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

/**
 * Builds a lead that is otherwise fully eligible for a follow-up (active, inside the
 * customer-service window, within business hours). The session state is left to the caller.
 */
function eligibleFollowUpLead(array $overrides = []): Lead
{
    return Lead::factory()->create(array_merge([
        'is_sandbox' => false,
        'status' => 'novo',
        'followup_status' => 'active',
        'followup_count' => 0,
        'last_inbound_at' => now()->subMinutes(15),
        'last_interaction_at' => now()->subMinutes(15),
    ], $overrides));
}

function evaluateFollowUp(Lead $lead): array
{
    $settings = app(FollowUpSettingsResolver::class)->forLead($lead);

    return app(FollowUpWindowService::class)->evaluate($lead->fresh(), $settings, now());
}

beforeEach(function () {
    // Within business hours (12:00 UTC ≈ 09:00 São Paulo) so the window gate never masks the result.
    $this->travelTo(Carbon::create(2026, 3, 20, 12, 0, 0, 'UTC'));
});

test('evaluate is eligible when the lead has an open session', function () {
    $lead = eligibleFollowUpLead();
    ConversationSession::factory()->forLead($lead)->open()->create();

    expect(evaluateFollowUp($lead)['eligible'])->toBeTrue();
});

test('evaluate returns no_open_session when the only session is closed', function () {
    $lead = eligibleFollowUpLead();
    ConversationSession::factory()->forLead($lead)->closed(ConversationSession::OUTCOME_NO_RESPONSE)->create();

    $evaluation = evaluateFollowUp($lead);

    expect($evaluation['eligible'])->toBeFalse()
        ->and($evaluation['reason'])->toBe('no_open_session');
});

test('evaluate stays eligible for a legacy lead with no sessions at all (pre-backfill)', function () {
    $lead = eligibleFollowUpLead();

    expect($lead->sessions()->exists())->toBeFalse()
        ->and(evaluateFollowUp($lead)['eligible'])->toBeTrue();
});

test('the command deactivates a lead whose only session is closed', function () {
    Queue::fake();
    $lead = eligibleFollowUpLead();
    ConversationSession::factory()->forLead($lead)->closed(ConversationSession::OUTCOME_LOST)->create();

    $this->artisan('credflow:check-followups')->assertSuccessful();

    expect($lead->fresh()->followup_status)->toBe('inactive');
    Queue::assertNotPushed(ProcessLeadFollowUpJob::class);
});

test('the command dispatches a lead with an open session', function () {
    Queue::fake();
    $lead = eligibleFollowUpLead();
    ConversationSession::factory()->forLead($lead)->open()->create();

    $this->artisan('credflow:check-followups')->assertSuccessful();

    Queue::assertPushed(ProcessLeadFollowUpJob::class, fn ($job): bool => $job->lead->is($lead));
});
