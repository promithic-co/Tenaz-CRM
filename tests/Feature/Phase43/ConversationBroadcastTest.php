<?php

use App\Events\ConversationUpdated;
use App\Events\LeadStatusChanged;
use App\Events\NewConversationMessage;
use App\Jobs\ProcessIncomingWhatsAppMessageJob;
use App\Models\Lead;
use App\Models\User;
use App\Services\AgentService;
use App\Services\CampaignReplyDetector;
use App\Services\WhatsAppService;
use Illuminate\Support\Facades\Event;

it('test_lead_status_change_dispatches_lead_status_changed', function () {
    Event::fake([LeadStatusChanged::class]);

    $lead = Lead::factory()->create([
        'status' => 'novo',
        'tenant_id' => 'test-tenant-42',
    ]);

    $lead->update(['status' => 'qualificado']);

    Event::assertDispatched(LeadStatusChanged::class, function (LeadStatusChanged $e) use ($lead) {
        return $e->leadId === $lead->id
            && $e->tenantId === 'test-tenant-42'
            && $e->oldStatus === 'novo'
            && $e->newStatus === 'qualificado';
    });
});

it('test_processIncomingWhatsAppMessageJob_dispatches_both_events', function () {
    Event::fake([NewConversationMessage::class, ConversationUpdated::class]);

    $user = User::factory()->create();
    $lead = Lead::factory()->create([
        'tenant_id' => $user->tenantId,
        'whatsapp' => '5511987654321',
        'agent_id' => null,
    ]);

    $this->mock(AgentService::class, fn ($m) => $m->shouldReceive('process')->andReturn(null));
    $this->mock(WhatsAppService::class);
    $this->mock(CampaignReplyDetector::class, fn ($m) => $m->shouldReceive('detect')->andReturn(null));

    $job = new ProcessIncomingWhatsAppMessageJob(
        phone: $lead->whatsapp,
        name: 'Test',
        tenantId: $user->tenantId,
        agentId: null,
        instanceName: 'test-instance',
        aggregatedMessage: 'Hello',
    );

    app()->call([$job, 'handle']);

    Event::assertDispatched(NewConversationMessage::class);
    Event::assertDispatched(ConversationUpdated::class, fn ($e) => $e->leadId === $lead->id);
});

it('test_conversation_updated_dispatched_on_inbound_message', function () {
    Event::fake([ConversationUpdated::class]);

    $user = User::factory()->create();
    $lead = Lead::factory()->create([
        'tenant_id' => $user->tenantId,
        'whatsapp' => '5511912345678',
        'agent_id' => null,
    ]);

    $this->mock(AgentService::class, fn ($m) => $m->shouldReceive('process')->andReturn(null));
    $this->mock(WhatsAppService::class);
    $this->mock(CampaignReplyDetector::class, fn ($m) => $m->shouldReceive('detect')->andReturn(null));

    $job = new ProcessIncomingWhatsAppMessageJob(
        phone: $lead->whatsapp,
        name: 'Test',
        tenantId: $user->tenantId,
        agentId: null,
        instanceName: 'test-instance',
        aggregatedMessage: 'Hi there',
    );

    app()->call([$job, 'handle']);

    Event::assertDispatched(ConversationUpdated::class, function (ConversationUpdated $e) use ($user) {
        return $e->tenantId === $user->tenantId;
    });
});
