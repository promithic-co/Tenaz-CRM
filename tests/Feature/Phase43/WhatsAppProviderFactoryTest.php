<?php

use App\Enums\WhatsAppProvider;
use App\Services\WhatsApp\Providers\MetaCloudProvider;
use App\Services\WhatsApp\WhatsAppProviderFactory;

it('WhatsAppProvider enum has only MetaCloud case', function () {
    $cases = WhatsAppProvider::cases();
    $values = array_map(fn ($c) => $c->value, $cases);

    expect($cases)->toHaveCount(1)
        ->and($values)->toContain('meta_cloud')
        ->and($values)->not->toContain('evolution')
        ->and($values)->not->toContain('gupshup')
        ->and($values)->not->toContain('waha');
});

it('test_factory_makeProvider_meta_cloud', function () {
    $instance = \App\Models\WhatsappInstance::factory()->metaCloud()->create();

    $factory = app(WhatsAppProviderFactory::class);
    $provider = $factory->makeProvider($instance);

    expect($provider)->toBeInstanceOf(MetaCloudProvider::class);
});
