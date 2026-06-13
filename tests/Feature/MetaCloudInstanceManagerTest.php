<?php

use App\Services\WhatsApp\Providers\MetaCloudInstanceManager;

it('reports connected when access token is present', function (): void {
    $manager = new MetaCloudInstanceManager(
        phoneNumberId: '123456789',
        accessToken: 'valid-token-abc',
    );

    expect($manager->status())->toBe(['state' => 'open']);
});

it('reports disconnected when access token is null', function (): void {
    $manager = new MetaCloudInstanceManager(
        phoneNumberId: '123456789',
        accessToken: null,
    );

    expect($manager->status())->toBe(['state' => 'close']);
});

it('connect returns error because embedded signup is frontend', function (): void {
    $manager = new MetaCloudInstanceManager(
        phoneNumberId: '123456789',
        accessToken: null,
    );

    $result = $manager->connect();

    expect($result)->toHaveKey('error')
        ->and($result['error'])->toContain('Meta');
});

it('fetchInstanceInfo returns phone number from owner phone', function (): void {
    $manager = new MetaCloudInstanceManager(
        phoneNumberId: '123456789',
        accessToken: 'valid-token',
        ownerPhoneNumber: '5511999999999',
    );

    expect($manager->fetchInstanceInfo())->toBe(['phone_number' => '5511999999999']);
});
