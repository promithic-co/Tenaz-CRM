<?php

use App\Models\Lead;
use App\Services\AgentService;

beforeEach(function () {
    $this->service = app(AgentService::class);
});

test('process returns null when lead status is optou_sair', function () {
    $lead = Lead::factory()->create(['status' => 'optou_sair']);

    $result = $this->service->process($lead, 'oi');

    expect($result)->toBeNull();
});

test('shouldNotSendReply returns true when response contains sentinel', function () {
    $method = (new \ReflectionClass(AgentService::class))->getMethod('shouldNotSendReply');
    $method->setAccessible(true);

    expect($method->invoke($this->service, AgentService::NO_REPLY_SENTINEL))->toBeTrue();
    expect($method->invoke($this->service, '  '.AgentService::NO_REPLY_SENTINEL.'  '))->toBeTrue();
    expect($method->invoke($this->service, 'Texto e '.AgentService::NO_REPLY_SENTINEL))->toBeTrue();
});

test('shouldNotSendReply returns true when response is empty or whitespace', function () {
    $method = (new \ReflectionClass(AgentService::class))->getMethod('shouldNotSendReply');
    $method->setAccessible(true);

    expect($method->invoke($this->service, ''))->toBeTrue();
    expect($method->invoke($this->service, '   '))->toBeTrue();
});

test('shouldNotSendReply returns false when response is normal text', function () {
    $method = (new \ReflectionClass(AgentService::class))->getMethod('shouldNotSendReply');
    $method->setAccessible(true);

    expect($method->invoke($this->service, 'Olá, como posso ajudar?'))->toBeFalse();
    expect($method->invoke($this->service, 'Tchau.'))->toBeFalse();
});
