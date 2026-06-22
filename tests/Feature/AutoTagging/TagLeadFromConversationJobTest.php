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

    test('has a retry window for overlap and rate-limit releases', function () {
        $job = new TagLeadFromConversationJob(1, 'status_change');

        expect($job->retryUntil())->toBeInstanceOf(DateTimeInterface::class);
        expect($job->retryUntil()->getTimestamp())->toBeGreaterThan(now()->getTimestamp());
        expect($job->maxExceptions)->toBe(2);
    });

    test('retry window can be disabled for attempt-count behaviour', function () {
        config(['credflow.jobs.auto_tag_retry_window_seconds' => 0]);

        $job = new TagLeadFromConversationJob(1, 'status_change');

        expect($job->retryUntil())->toBeNull();
        expect($job->tries)->toBe(2);
    });
});
