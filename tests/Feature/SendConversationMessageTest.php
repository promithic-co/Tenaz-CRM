<?php

use App\Models\Lead;
use App\Models\ServiceTicket;
use App\Models\User;
use App\Models\WhatsappInstance;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
    $instance = WhatsappInstance::factory()->for($this->user)->create([
        'name' => 'test-instance',
        'tenant_id' => $this->user->tenantId,
    ]);
    $this->lead = Lead::factory()->create([
        'tenant_id' => $this->user->tenantId,
        'whatsapp' => '5511999999999',
        'whatsapp_instance_id' => $instance->id,
    ]);
});

test('operator can send text message to their lead', function () {
    $response = $this->postJson("/conversas/{$this->lead->id}/send", [
        'content' => 'Hello from operator',
    ]);

    $response->assertOk()
        ->assertJsonPath('status', 'queued')
        ->assertJsonPath('message.role', 'operator');

    $this->assertDatabaseHas('whatsapp_outbox_messages', [
        'lead_id' => $this->lead->id,
        'status' => 'queued',
    ]);
});

test('operator text message marks active service ticket as waiting customer', function () {
    $this->lead->update(['followup_status' => 'active']);

    $ticket = ServiceTicket::create([
        'tenant_id' => $this->user->tenantId,
        'lead_id' => $this->lead->id,
        'type' => 'escalation',
        'status' => ServiceTicket::STATUS_OPEN,
    ]);

    $this->postJson("/conversas/{$this->lead->id}/send", [
        'content' => 'Hello from operator',
    ])->assertOk();

    $ticket->refresh();
    expect($ticket->status)->toBe(ServiceTicket::STATUS_WAITING_CUSTOMER)
        ->and($ticket->assigned_user_id)->toBe($this->user->id)
        ->and($ticket->claimed_at)->not->toBeNull()
        ->and($ticket->first_response_at)->not->toBeNull()
        ->and($ticket->last_operator_message_at)->not->toBeNull();

    $this->lead->refresh();
    expect($this->lead->operational_stage)->toBe(Lead::STAGE_WAITING_CUSTOMER)
        ->and($this->lead->assigned_user_id)->toBe($this->user->id)
        ->and($this->lead->ai_paused_reason)->toBe('manual_message')
        ->and($this->lead->ai_paused_until)->not->toBeNull()
        ->and($this->lead->followup_status)->toBe('paused');
});

test('operator can send image file to their lead', function () {
    Storage::fake('local');

    $response = $this->postJson("/conversas/{$this->lead->id}/send", [
        'file' => UploadedFile::fake()->create('test.jpg', 100, 'image/jpeg'),
    ]);

    $response->assertOk()
        ->assertJsonPath('message.media.type', 'image');

    $this->assertDatabaseHas('whatsapp_outbox_messages', [
        'lead_id' => $this->lead->id,
        'status' => 'queued',
    ]);
});

test('operator cannot send when lead has no WhatsApp instance id', function () {
    $this->lead->update(['whatsapp_instance_id' => null]);

    $this->postJson("/conversas/{$this->lead->id}/send", [
        'content' => 'Hello from operator',
    ])->assertUnprocessable()
        ->assertJsonPath('status', 'error');
});

test('cannot send to another tenants lead', function () {
    $otherUser = User::factory()->create();
    $otherLead = Lead::factory()->create([
        'tenant_id' => $otherUser->tenantId,
        'whatsapp' => '5511888888888',
    ]);

    $response = $this->postJson("/conversas/{$otherLead->id}/send", [
        'content' => 'Should fail',
    ]);

    $response->assertNotFound();
});

test('validation fails without content or file', function () {
    $response = $this->postJson("/conversas/{$this->lead->id}/send", []);

    $response->assertUnprocessable();
});

test('validation rejects unsupported file types', function () {
    $response = $this->postJson("/conversas/{$this->lead->id}/send", [
        'file' => UploadedFile::fake()->create('malware.exe', 100),
    ]);

    $response->assertUnprocessable();
});
