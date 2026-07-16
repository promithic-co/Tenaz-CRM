<?php

use App\Models\ConversationTimelineMessage;
use App\Models\Lead;
use App\Models\User;
use App\Models\WhatsappInstance;
use App\Models\WhatsappOutboxMessage;
use App\Services\ConversationTimelineService;
use Illuminate\Support\Facades\Event;

/**
 * @return array{0: WhatsappOutboxMessage, 1: ConversationTimelineMessage}
 */
function strandedOutbox(string $status, int $ageSeconds, string $timelineStatus = 'sending'): array
{
    $user = User::factory()->create();
    $instance = WhatsappInstance::factory()->metaCloud()->for($user)->create();
    $lead = Lead::factory()->create([
        'tenant_id' => $user->tenantId,
        'whatsapp_instance_id' => $instance->id,
    ]);

    $timeline = app(ConversationTimelineService::class)->record(
        lead: $lead,
        direction: 'outbound',
        senderType: 'human',
        body: 'Mensagem',
        status: $timelineStatus,
        source: 'manual',
    );

    $outbox = WhatsappOutboxMessage::create([
        'tenant_id' => (string) $user->tenantId,
        'lead_id' => $lead->id,
        'channel' => 'whatsapp',
        'provider' => 'meta_cloud',
        'payload_json' => ['type' => 'text', 'phone' => $lead->whatsapp, 'text' => 'Mensagem'],
        'status' => $status,
        'idempotency_key' => 'key-'.uniqid(),
        'provider_attempted_at' => now(),
        'scheduled_at' => now(),
        'timeline_message_id' => $timeline->id,
    ]);

    // Backdate updated_at without touching the timestamp automatically (explicit value wins).
    WhatsappOutboxMessage::query()
        ->whereKey($outbox->id)
        ->update(['updated_at' => now()->subSeconds($ageSeconds)]);

    return [$outbox->fresh(), $timeline];
}

beforeEach(function () {
    Event::fake();
    config()->set('credflow.jobs.outbox_in_doubt_timeout_seconds', 86400);
});

it('reconciles a stranded in_doubt outbox to failed and fails its timeline', function () {
    [$outbox, $timeline] = strandedOutbox('in_doubt', 90000);

    $this->artisan('credflow:reconcile-outbox')->assertSuccessful();

    expect($outbox->fresh()->status)->toBe('failed')
        ->and($outbox->fresh()->last_error)->not->toBeNull()
        // provider_attempted_at is preserved so the in-doubt guard still blocks any re-send.
        ->and($outbox->fresh()->provider_attempted_at)->not->toBeNull()
        ->and($timeline->fresh()->status)->toBe('failed');
});

it('leaves a recent in_doubt outbox untouched', function () {
    [$outbox, $timeline] = strandedOutbox('in_doubt', 60);

    $this->artisan('credflow:reconcile-outbox')->assertSuccessful();

    expect($outbox->fresh()->status)->toBe('in_doubt')
        ->and($timeline->fresh()->status)->toBe('sending');
});

it('never touches non in_doubt outbox rows', function (string $status) {
    [$outbox] = strandedOutbox($status, 90000, timelineStatus: 'sent');

    $this->artisan('credflow:reconcile-outbox')->assertSuccessful();

    expect($outbox->fresh()->status)->toBe($status);
})->with(['sent', 'queued', 'delivered', 'failed']);

it('is a no-op when the timeout is disabled', function () {
    config()->set('credflow.jobs.outbox_in_doubt_timeout_seconds', 0);

    [$outbox, $timeline] = strandedOutbox('in_doubt', 90000);

    $this->artisan('credflow:reconcile-outbox')->assertSuccessful();

    expect($outbox->fresh()->status)->toBe('in_doubt')
        ->and($timeline->fresh()->status)->toBe('sending');
});
