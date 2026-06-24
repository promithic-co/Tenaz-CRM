<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

test('agent_conversation_messages has a (role, created_at) index for daily usage aggregation (GROW-3)', function () {
    $hasRoleCreatedIndex = collect(Schema::getIndexes('agent_conversation_messages'))
        ->contains(fn (array $index): bool => $index['columns'] === ['role', 'created_at']);

    expect($hasRoleCreatedIndex)->toBeTrue();
});
