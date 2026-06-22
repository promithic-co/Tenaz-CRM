<?php

use Illuminate\Support\Facades\Queue;

function dockerSupervisordContent(): string
{
    return file_get_contents(base_path('docker/supervisord.conf'));
}

function dockerStackContent(): string
{
    return file_get_contents(base_path('docker-stack.yml'));
}

function workerTimeouts(string $supervisord): array
{
    preg_match_all('/--timeout=(\d+)/', $supervisord, $matches);

    return array_map('intval', $matches[1]);
}

it('runs each production queue through an explicit docker worker', function () {
    $supervisord = dockerSupervisordContent();

    foreach (config('queue.health_queues') as $queue) {
        expect($supervisord)->toContain("--queue={$queue}");
    }

    expect($supervisord)
        ->not->toContain('--queue=campaigns,messages,followups,outbox,media,default')
        ->toContain('[program:queue-auto-tags]');
});

it('keeps redis retry_after above every docker worker timeout', function () {
    $supervisord = dockerSupervisordContent();
    $stack = dockerStackContent();
    $timeouts = workerTimeouts($supervisord);

    expect($timeouts)->not->toBeEmpty();
    expect($stack)->toContain('REDIS_QUEUE_RETRY_AFTER: ${REDIS_QUEUE_RETRY_AFTER:-3900}');
    expect((int) env('REDIS_QUEUE_RETRY_AFTER', 3900))->toBeGreaterThan(max($timeouts));
});

it('reports health depth for every runtime queue', function () {
    foreach (config('queue.health_queues') as $index => $queue) {
        Queue::shouldReceive('size')->once()->with($queue)->andReturn($index + 1);
    }

    $response = $this->getJson('/api/health');

    $response->assertOk()
        ->assertJsonPath('checks.queue.status', 'ok');

    foreach (config('queue.health_queues') as $index => $queue) {
        $response->assertJsonPath("checks.queue.queues.{$queue}", $index + 1);
    }
});
