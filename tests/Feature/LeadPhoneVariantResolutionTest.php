<?php

use App\Actions\CreateOrRestoreLeadAction;
use App\Models\Agent;
use App\Models\Lead;
use App\Models\User;
use App\Models\WhatsappInstance;
use App\Services\CampaignReplyDetector;
use App\Services\IncomingConversationPersister;
use App\Services\PauseService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * The same subscriber, spelled both ways. Brazilian mobiles gained a mandatory 9th digit
 * in 2012 and the provider echoes whichever form it feels like, so one person reached the
 * inbox as two conversations until leads resolved across the pair.
 */
const WITH_NINTH = '5511987654321';

const WITHOUT_NINTH = '551187654321';

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function variantPersistArgs(array $overrides = []): array
{
    return array_merge([
        'tenantId' => 'tenant-variant',
        'agentId' => null,
        'phone' => WITH_NINTH,
        'name' => 'Variante',
        'instanceName' => '',
        'aggregatedMessage' => 'oi',
        'mediaContext' => null,
        'interactionId' => 'int-variant-1',
        'providerMessageId' => null,
    ], $overrides);
}

test('an inbound missing the 9th digit lands in the conversation that already exists', function () {
    $this->mock(CampaignReplyDetector::class)->shouldReceive('detect')->twice();

    $persister = app(IncomingConversationPersister::class);

    $first = $persister->persist(...variantPersistArgs(['phone' => WITH_NINTH]));
    $second = $persister->persist(...variantPersistArgs([
        'phone' => WITHOUT_NINTH,
        'interactionId' => 'int-variant-2',
    ]));

    expect($second['lead']->id)->toBe($first['lead']->id);
    expect(Lead::withoutGlobalScopes()->where('tenant_id', 'tenant-variant')->count())->toBe(1);
});

test('the pair reconciles in the other direction too', function () {
    $this->mock(CampaignReplyDetector::class)->shouldReceive('detect')->twice();

    $persister = app(IncomingConversationPersister::class);

    $first = $persister->persist(...variantPersistArgs(['phone' => WITHOUT_NINTH]));
    $second = $persister->persist(...variantPersistArgs([
        'phone' => WITH_NINTH,
        'interactionId' => 'int-variant-2',
    ]));

    expect($second['lead']->id)->toBe($first['lead']->id);
    expect(Lead::withoutGlobalScopes()->where('tenant_id', 'tenant-variant')->count())->toBe(1);
});

test('a lead created from an inbound is stored under the canonical spelling', function () {
    $this->mock(CampaignReplyDetector::class)->shouldReceive('detect')->once();

    // Arrives without the 9; the row must still be written with it, so the table stops
    // accumulating two spellings for the dedupe command to clean up later.
    $result = app(IncomingConversationPersister::class)->persist(...variantPersistArgs([
        'phone' => WITHOUT_NINTH,
    ]));

    expect($result['lead']->whatsapp)->toBe(WITH_NINTH);
});

test('a landline is left alone — it never carried a 9th digit to reconcile', function () {
    $this->mock(CampaignReplyDetector::class)->shouldReceive('detect')->once();

    $result = app(IncomingConversationPersister::class)->persist(...variantPersistArgs([
        'phone' => '551133334444',
    ]));

    expect($result['lead']->whatsapp)->toBe('551133334444');
});

test('pausing one spelling pauses the subscriber, not just that row', function () {
    // Duplicates predating the fix still exist in production, and an AI that keeps
    // answering on the twin of a conversation an operator just took over is the worst
    // way for the split to surface.
    $withNinth = Lead::factory()->create([
        'tenant_id' => 'tenant-variant',
        'whatsapp' => WITH_NINTH,
    ]);
    $withoutNinth = Lead::factory()->create([
        'tenant_id' => 'tenant-variant',
        'whatsapp' => WITHOUT_NINTH,
    ]);

    app(PauseService::class)->pause(WITH_NINTH, 'tenant-variant');

    expect($withNinth->fresh()->ai_paused_until)->not->toBeNull()
        ->and($withoutNinth->fresh()->ai_paused_until)->not->toBeNull();

    // And the cache side answers for either spelling, since it is keyed canonically.
    expect(app(PauseService::class)->isPaused(WITHOUT_NINTH, 'tenant-variant'))->toBeTrue();
});

test('resuming clears the pause on every spelling', function () {
    $withNinth = Lead::factory()->create([
        'tenant_id' => 'tenant-variant',
        'whatsapp' => WITH_NINTH,
        'ai_paused_until' => now()->addHour(),
    ]);
    $withoutNinth = Lead::factory()->create([
        'tenant_id' => 'tenant-variant',
        'whatsapp' => WITHOUT_NINTH,
        'ai_paused_until' => now()->addHour(),
    ]);

    app(PauseService::class)->pause(WITHOUT_NINTH, 'tenant-variant');
    app(PauseService::class)->resume(WITHOUT_NINTH, 'tenant-variant');

    expect($withNinth->fresh()->ai_paused_until)->toBeNull()
        ->and($withoutNinth->fresh()->ai_paused_until)->toBeNull()
        ->and(app(PauseService::class)->isPaused(WITH_NINTH, 'tenant-variant'))->toBeFalse();
});

test('registering a lead by hand finds the one already there under the other spelling', function () {
    $user = User::factory()->create();
    $tenantId = (string) $user->tenantId;
    $agent = Agent::factory()->create(['user_id' => $user->id, 'is_default' => true]);
    $instance = WhatsappInstance::factory()->create([
        'user_id' => $user->id,
        'tenant_id' => $tenantId,
        'agent_id' => $agent->id,
        'name' => 'variant-instance',
    ]);

    $existing = Lead::factory()->create([
        'tenant_id' => $tenantId,
        'agent_id' => $agent->id,
        'whatsapp' => WITH_NINTH,
    ]);

    $result = app(CreateOrRestoreLeadAction::class)->execute($tenantId, $user->id, [
        'nome' => 'Digitado Sem O Nove',
        'whatsapp' => WITHOUT_NINTH,
        'evolution_instance' => $instance->name,
    ]);

    expect($result['existed'])->toBeTrue()
        ->and($result['lead']->id)->toBe($existing->id);
});

test('the variant scope never degrades into matching every lead', function () {
    Lead::factory()->create(['tenant_id' => 'tenant-variant', 'whatsapp' => WITH_NINTH]);

    // A blank phone must select nothing. Left implicit this rides on whereIn's empty-array
    // behaviour, and a scope that silently means "any lead" would let a pause or a rename
    // hit the whole tenant.
    expect(Lead::withoutGlobalScopes()->forPhoneVariants('')->count())->toBe(0);
    expect(Lead::withoutGlobalScopes()->forPhoneVariants(null)->count())->toBe(0);
});
