<?php

use App\Jobs\TagLeadFromConversationJob;
use App\Models\Lead;
use App\Models\User;
use App\Services\LeadAutoTaggingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

describe('TagLeadFromConversationJob', function () {
    test('job is dispatched to auto-tags queue', function () {
        Queue::fake();

        $user = User::factory()->create();
        $tenantId = (string) $user->tenantId;
        $lead = Lead::factory()->forTenant($tenantId)->create();

        TagLeadFromConversationJob::dispatch($lead->id, 'status_change');

        Queue::assertPushedOn('auto-tags', TagLeadFromConversationJob::class);
    });

    test('missing lead exits quietly without exception', function () {
        $mockService = Mockery::mock(LeadAutoTaggingService::class);
        $mockService->shouldNotReceive('evaluate');
        app()->instance(LeadAutoTaggingService::class, $mockService);

        $job = new TagLeadFromConversationJob(99999, 'test');
        $job->handle(app(LeadAutoTaggingService::class));

        expect(true)->toBeTrue(); // no exception thrown
    });

    test('calls LeadAutoTaggingService::evaluate when lead exists', function () {
        $user = User::factory()->create();
        $tenantId = (string) $user->tenantId;
        $lead = Lead::factory()->forTenant($tenantId)->create();

        $mockService = Mockery::mock(LeadAutoTaggingService::class);
        $mockService->shouldReceive('evaluate')
            ->once()
            ->with(Mockery::type(Lead::class), 'status_change', null)
            ->andReturn(['skipped' => true]);
        app()->instance(LeadAutoTaggingService::class, $mockService);

        $job = new TagLeadFromConversationJob($lead->id, 'status_change');
        $job->handle(app(LeadAutoTaggingService::class));
    });
});
