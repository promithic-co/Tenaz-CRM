<?php

use App\Events\ConversationUpdated;
use App\Events\NewConversationMessage;
use App\Jobs\ProcessIncomingWhatsAppMessageJob;
use App\Models\User;
use App\Models\WhatsappInstance;
use App\Services\AgentService;
use App\Services\CampaignReplyDetector;
use App\Services\WhatsAppService;

it('test_inbound_job_dispatches_both_conversation_events', function () {
    Event::fake([NewConversationMessage::class, ConversationUpdated::class]);

    $user = User::factory()->create();
    WhatsappInstance::factory()->for($user)->create(['name' => 'meta-regression-inbound']);

    $agentMock = Mockery::mock(AgentService::class);
    $agentMock->shouldReceive('process')->andReturn('Olá, posso te ajudar?');
    app()->instance(AgentService::class, $agentMock);

    $whatsappMock = Mockery::mock(WhatsAppService::class);
    $whatsappMock->shouldReceive('sendSplitMessages')->andReturnNull();
    app()->instance(WhatsAppService::class, $whatsappMock);

    $detectorMock = Mockery::mock(CampaignReplyDetector::class);
    $detectorMock->shouldReceive('detect')->andReturn(null);
    app()->instance(CampaignReplyDetector::class, $detectorMock);

    $job = new ProcessIncomingWhatsAppMessageJob(
        phone: '5511999990001',
        name: 'Cliente Teste',
        tenantId: (string) $user->id,
        agentId: null,
        instanceName: 'meta-regression-inbound',
        aggregatedMessage: 'Olá, quero saber mais sobre o produto',
    );

    app()->call([$job, 'handle']);

    Event::assertDispatched(NewConversationMessage::class);
    Event::assertDispatched(ConversationUpdated::class);
});

it('test_meta_inbound_webhook_dispatches_both_events', function () {
    Event::fake([NewConversationMessage::class, ConversationUpdated::class]);

    $user = User::factory()->create();
    WhatsappInstance::factory()->metaCloud()->for($user)->create(['name' => 'meta-regression']);

    $agentMock = Mockery::mock(AgentService::class);
    $agentMock->shouldReceive('process')->andReturn('Olá, posso te ajudar?');
    app()->instance(AgentService::class, $agentMock);

    $whatsappMock = Mockery::mock(WhatsAppService::class);
    $whatsappMock->shouldReceive('sendSplitMessages')->andReturnNull();
    app()->instance(WhatsAppService::class, $whatsappMock);

    $detectorMock = Mockery::mock(CampaignReplyDetector::class);
    $detectorMock->shouldReceive('detect')->andReturn(null);
    app()->instance(CampaignReplyDetector::class, $detectorMock);

    $job = new ProcessIncomingWhatsAppMessageJob(
        phone: '5511999990002',
        name: 'Cliente Meta',
        tenantId: (string) $user->id,
        agentId: null,
        instanceName: 'meta-regression',
        aggregatedMessage: 'Vim pelo anúncio!',
    );

    app()->call([$job, 'handle']);

    Event::assertDispatched(NewConversationMessage::class);
    Event::assertDispatched(ConversationUpdated::class);
});
