<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

it('renders the health dashboard with every probe section', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('laboratory.health'))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('laboratory/Health')
            ->where('checks.database.status', 'ok')
            ->where('checks.cache.status', 'ok')
            ->where('checks.queue.status', 'ok')
            ->has('checks.disk.status')
            ->has('horizon.status')
            ->has('failedJobs')
            ->has('checkedAt')
        );
});

it('degrades a failing probe to an error status without throwing a 500', function () {
    $user = User::factory()->create();

    Queue::shouldReceive('size')
        ->andThrow(new RuntimeException('queue connection refused'));

    $this->actingAs($user)
        ->get(route('laboratory.health'))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('laboratory/Health')
            ->where('checks.queue.status', 'error')
            ->where('checks.queue.message', 'queue connection refused')
            ->where('checks.database.status', 'ok')
        );
});
