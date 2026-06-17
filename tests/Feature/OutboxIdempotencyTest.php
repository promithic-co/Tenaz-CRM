<?php

use App\Models\Lead;
use App\Models\User;
use App\Models\WhatsappInstance;
use App\Models\WhatsappOutboxMessage;
use App\Services\WhatsappOutboxService;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->instance = WhatsappInstance::factory()->metaCloud()->for($this->user)->create([
        'name' => 'outbox-idem',
    ]);
    $this->lead = Lead::factory()->create([
        'tenant_id' => (string) $this->user->tenantId,
        'whatsapp_instance_id' => $this->instance->id,
    ]);
    $this->service = app(WhatsappOutboxService::class);
});

test('same interaction id dedupes even after the lead is mutated mid-turn', function () {
    $this->service->queueTextForLead(
        $this->lead, $this->instance, $this->lead->whatsapp, 'Olá', 'agent', 'agent', 'int-retry'
    );

    // Simulate the mid-turn mutation that previously changed the idempotency key.
    $this->lead->touch();

    $this->service->queueTextForLead(
        $this->lead, $this->instance, $this->lead->whatsapp, 'Olá', 'agent', 'agent', 'int-retry'
    );

    expect(WhatsappOutboxMessage::where('lead_id', $this->lead->id)->count())->toBe(1);
});

test('distinct interaction ids produce distinct sends', function () {
    $this->service->queueTextForLead(
        $this->lead, $this->instance, $this->lead->whatsapp, 'Olá', 'agent', 'agent', 'int-a'
    );
    $this->service->queueTextForLead(
        $this->lead, $this->instance, $this->lead->whatsapp, 'Olá', 'agent', 'agent', 'int-b'
    );

    expect(WhatsappOutboxMessage::where('lead_id', $this->lead->id)->count())->toBe(2);
});
