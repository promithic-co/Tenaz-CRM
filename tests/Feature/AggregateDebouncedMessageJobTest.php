<?php

use App\Jobs\AggregateDebouncedMessageJob;
use App\Jobs\ProcessIncomingWhatsAppMessageJob;
use App\Services\AgentInteractionEventService;
use App\Services\DebounceService;
use Illuminate\Support\Facades\Queue;

function makeDebounceJob(): AggregateDebouncedMessageJob
{
    return new AggregateDebouncedMessageJob(
        phone: '5511999999999',
        name: 'Test User',
        tenantId: '1',
        agentId: null,
        instanceName: 'inst-1',
        provider: 'meta_cloud',
        interactionId: 'int-123',
        providerMessageId: 'wamid.TEST',
    );
}

it('drains the buffer and dispatches ProcessIncomingWhatsAppMessageJob with the aggregated text', function (): void {
    Queue::fake();

    $debounce = Mockery::mock(DebounceService::class);
    $debounce->shouldReceive('drain')->once()->with('5511999999999')->andReturn("primeira\nsegunda");

    $events = Mockery::mock(AgentInteractionEventService::class);
    $events->shouldReceive('record')->once();

    makeDebounceJob()->handle($debounce, $events);

    Queue::assertPushed(ProcessIncomingWhatsAppMessageJob::class, fn (ProcessIncomingWhatsAppMessageJob $job): bool => $job->aggregatedMessage === "primeira\nsegunda"
        && $job->phone === '5511999999999'
        && $job->providerMessageId === 'wamid.TEST');
});

it('does nothing when the buffer drains empty', function (): void {
    Queue::fake();

    $debounce = Mockery::mock(DebounceService::class);
    $debounce->shouldReceive('drain')->once()->andReturn(null);

    $events = Mockery::mock(AgentInteractionEventService::class);
    $events->shouldNotReceive('record');

    makeDebounceJob()->handle($debounce, $events);

    Queue::assertNotPushed(ProcessIncomingWhatsAppMessageJob::class);
});
