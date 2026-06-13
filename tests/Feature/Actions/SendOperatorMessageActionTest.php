<?php

use App\Actions\SendOperatorMessageAction;
use App\Models\Agent;
use App\Models\Lead;
use App\Models\User;
use App\Models\WhatsappInstance;
use App\Models\WhatsappOutboxMessage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Storage;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

/**
 * Behaviour coverage for SendOperatorMessageAction (Plan B.4), the operator
 * send orchestration extracted out of ConversasController::sendMessage.
 *
 * @return array{User, Lead, WhatsappInstance}
 */
function sendActionFixture(): array
{
    $user = User::factory()->create();
    $agent = Agent::factory()->create(['user_id' => $user->id, 'is_default' => true]);

    $instance = WhatsappInstance::factory()->metaCloud()->create([
        'user_id' => $user->id,
        'tenant_id' => $user->tenantId,
        'name' => 'action-instance',
    ]);

    $lead = Lead::factory()->forAgent($agent)->create([
        'whatsapp' => '5511955554444',
        'whatsapp_instance_id' => $instance->id,
    ]);

    return [$user, $lead, $instance];
}

test('text branch queues an outbox message with the text idempotency key', function () {
    [$user, $lead] = sendActionFixture();
    $this->actingAs($user);

    $result = app(SendOperatorMessageAction::class)->send(
        lead: $lead,
        content: 'mensagem de texto',
        file: null,
        actor: $user,
        broadcastToOthers: false,
    );

    expect($result)->not->toBeNull()
        ->and($result['message']['content'])->toBe('mensagem de texto')
        ->and($result['message']['sender_type'])->toBe('human')
        ->and($result['message']['source'])->toBe('manual');

    $outbox = WhatsappOutboxMessage::findOrFail($result['outbox_id']);
    $interactionId = $outbox->interaction_id;

    expect($outbox->idempotency_key)->toBe("manual:{$lead->id}:{$interactionId}:text")
        ->and($outbox->payload_json['type'])->toBe('text')
        ->and($outbox->payload_json['phone'])->toBe('5511955554444');
});

test('media branch streams to disk and queues with the media idempotency key', function () {
    Storage::fake('local');
    [$user, $lead] = sendActionFixture();
    $this->actingAs($user);

    $file = UploadedFile::fake()->image('foto.jpg', 10, 10);

    $result = app(SendOperatorMessageAction::class)->send(
        lead: $lead,
        content: 'legenda',
        file: $file,
        actor: $user,
        broadcastToOthers: false,
    );

    expect($result)->not->toBeNull()
        ->and($result['message']['media'])->not->toBeNull();

    $outbox = WhatsappOutboxMessage::findOrFail($result['outbox_id']);
    $interactionId = $outbox->interaction_id;

    expect($outbox->idempotency_key)->toBe("manual:{$lead->id}:{$interactionId}:media")
        ->and($outbox->payload_json['type'])->toBe('media')
        ->and($outbox->payload_json['media_type'])->toBe('image')
        ->and($outbox->payload_json['mime_type'])->toBe('image/jpeg')
        ->and($outbox->payload_json['instance_name'])->toBe('action-instance')
        ->and($outbox->payload_json['caption'])->toBe('legenda')
        ->and($outbox->payload_json['disk'])->toBe('local');

    Storage::disk('local')->assertExists($outbox->payload_json['disk_path']);
});

test('returns null (422 contract) when the lead has no instance', function () {
    $user = User::factory()->create();
    $agent = Agent::factory()->create(['user_id' => $user->id, 'is_default' => true]);
    $lead = Lead::factory()->forAgent($agent)->create([
        'whatsapp' => '5511944443333',
        'whatsapp_instance_id' => null,
    ]);
    $this->actingAs($user);

    $result = app(SendOperatorMessageAction::class)->send(
        lead: $lead,
        content: 'sem instancia',
        file: null,
        actor: $user,
        broadcastToOthers: false,
    );

    expect($result)->toBeNull();
    expect(WhatsappOutboxMessage::count())->toBe(0);
});

test('resolves the per-lead instance and never a global default', function () {
    $user = User::factory()->create();
    $agent = Agent::factory()->create(['user_id' => $user->id, 'is_default' => true]);

    $leadInstance = WhatsappInstance::factory()->metaCloud()->create([
        'user_id' => $user->id,
        'tenant_id' => $user->tenantId,
        'name' => 'lead-own',
    ]);
    WhatsappInstance::factory()->metaCloud()->create([
        'user_id' => $user->id,
        'tenant_id' => $user->tenantId,
        'name' => 'other-one',
    ]);

    $lead = Lead::factory()->forAgent($agent)->create([
        'whatsapp' => '5511933332222',
        'whatsapp_instance_id' => $leadInstance->id,
    ]);
    $this->actingAs($user);

    $result = app(SendOperatorMessageAction::class)->send(
        lead: $lead,
        content: 'rota correta',
        file: null,
        actor: $user,
        broadcastToOthers: false,
    );

    $outbox = WhatsappOutboxMessage::findOrFail($result['outbox_id']);

    expect($outbox->payload_json['instance_id'])->toBe($leadInstance->id)
        ->and($outbox->payload_json['instance_name'])->toBe('lead-own');
});

test('a failing broadcast does not abort the send', function () {
    [$user, $lead] = sendActionFixture();
    $this->actingAs($user);

    // Force the realtime broadcast to throw; the timeline service swallows it
    // and the send must still succeed (persistence already happened).
    Broadcast::shouldReceive('event')->andThrow(new RuntimeException('socket down'));

    $result = app(SendOperatorMessageAction::class)->send(
        lead: $lead,
        content: 'apesar do broadcast',
        file: null,
        actor: $user,
        broadcastToOthers: true,
    );

    expect($result)->not->toBeNull()
        ->and($result['outbox_id'])->not->toBeNull();
});
