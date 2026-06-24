<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

test('agent_interaction_events has a (lead_id, created_at) index for lead-scoped reads (GROW-1)', function () {
    $hasLeadTimeIndex = collect(Schema::getIndexes('agent_interaction_events'))
        ->contains(fn (array $index): bool => $index['columns'] === ['lead_id', 'created_at']);

    expect($hasLeadTimeIndex)->toBeTrue();
});
