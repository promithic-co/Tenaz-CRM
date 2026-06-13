<?php

use App\Models\Lead;
use App\Models\User;

it('sets AI pause metadata after manual move', function () {
    $user = User::factory()->create();
    $lead = Lead::factory()->create([
        'tenant_id' => $user->tenantId,
        'status' => 'novo',
        'ai_paused_until' => null,
        'ai_paused_reason' => null,
        'ai_paused_by' => null,
    ]);

    $this->actingAs($user)
        ->post('/pipeline/move', [
            'lead_id' => $lead->id,
            'from_status' => 'novo',
            'to_status' => 'qualificado',
        ]);

    $fresh = $lead->fresh();

    expect($fresh->ai_paused_until)->not->toBeNull()
        ->and(abs($fresh->ai_paused_until->diffInHours(now())))->toBeBetween(23, 25)
        ->and($fresh->ai_paused_reason)->toBe('manual_status_override')
        ->and($fresh->ai_paused_by)->toBe($user->id);
});
