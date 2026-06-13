<?php

use App\Models\ServiceTicket;
use App\Services\HumanHandoffStateService;

/**
 * Unit coverage for HumanHandoffStateService::handoffActions — the operator
 * action derivation extracted out of ConversasController::conversationProps
 * (Plan B.2).
 */
function makeTicket(string $status): ServiceTicket
{
    $ticket = new ServiceTicket;
    $ticket->status = $status;

    return $ticket;
}

test('no ticket yields no actions', function () {
    expect((new HumanHandoffStateService)->handoffActions(null))->toBe([]);
});

test('open ticket includes claim plus the standard actions', function () {
    $actions = (new HumanHandoffStateService)->handoffActions(makeTicket(ServiceTicket::STATUS_OPEN));

    expect($actions)->toBe(['claim', 'resolve', 'return_to_ai', 'keep_manual']);
});

test('assigned ticket omits claim', function () {
    $actions = (new HumanHandoffStateService)->handoffActions(makeTicket(ServiceTicket::STATUS_ASSIGNED));

    expect($actions)->toBe(['resolve', 'return_to_ai', 'keep_manual']);
});

test('resolved/closed (non-active) ticket yields no actions', function () {
    $service = new HumanHandoffStateService;

    expect($service->handoffActions(makeTicket(ServiceTicket::STATUS_RESOLVED)))->toBe([])
        ->and($service->handoffActions(makeTicket(ServiceTicket::STATUS_CLOSED)))->toBe([]);
});
