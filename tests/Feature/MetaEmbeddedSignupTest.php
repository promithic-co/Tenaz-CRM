<?php

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    config()->set('services.meta.app_id', 'meta-app-id');
    config()->set('services.meta.app_secret', 'meta-app-secret');
});

it('coordinates the Meta assets and caches a standard Cloud API signup', function (): void {
    Http::fake([
        'graph.facebook.com/v23.0/oauth/access_token*' => Http::response(['access_token' => 'user-token']),
        'graph.facebook.com/v23.0/waba-1/phone_numbers*' => Http::response([
            'data' => [['id' => 'phone-1', 'display_phone_number' => '5511999999999']],
        ]),
        'graph.facebook.com/v23.0/waba-1*' => Http::response([
            'owner_business_info' => ['id' => 'business-1'],
        ]),
    ]);

    $user = userWithTenant();

    $response = $this->actingAs($user)->postJson(route('whatsapp.meta.embedded-signup'), [
        'code' => 'oauth-code',
        'waba_id' => 'waba-1',
        'phone_number_id' => 'phone-1',
        'business_id' => 'business-1',
        'finish_type' => 'FINISH',
        'onboarding_mode' => 'new_cloud_api',
    ]);

    $response->assertOk()
        ->assertJsonPath('business_id', 'business-1')
        ->assertJsonPath('waba_id', 'waba-1')
        ->assertJsonPath('phone_number_id', 'phone-1')
        ->assertJsonPath('coexistence', false)
        ->assertJsonPath('onboarding_mode', 'new_cloud_api');

    $cached = Cache::get('meta_signup:'.$response->json('signup_token'));

    expect($cached['business_id'])->toBe('business-1');
    expect($cached['coexistence'])->toBeFalse();
    expect($cached['onboarding_mode'])->toBe('new_cloud_api');
    expect($cached['permanent'])->toBeTrue();
    expect($cached['system_user_id'])->toBeNull();
    expect($cached)->not->toHaveKey('meta_pin');
    Http::assertNotSent(fn ($request): bool => str_contains($request->url(), '/system_users')
        || str_contains($request->url(), '/access_tokens'));
});

it('derives and verifies coexistence from the Meta finish event', function (): void {
    Http::fake([
        'graph.facebook.com/v23.0/oauth/access_token*' => Http::response(['access_token' => 'user-token']),
        'graph.facebook.com/v23.0/waba-coex/phone_numbers*' => Http::response([
            'data' => [['id' => 'phone-coex']],
        ]),
        'graph.facebook.com/v23.0/waba-coex*' => Http::response([
            'owner_business_info' => ['id' => 'business-coex'],
        ]),
        'graph.facebook.com/v23.0/phone-coex*' => Http::response([
            'is_on_biz_app' => true,
            'platform_type' => 'CLOUD_API',
        ]),
    ]);

    $user = userWithTenant();

    $response = $this->actingAs($user)->postJson(route('whatsapp.meta.embedded-signup'), [
        'code' => 'oauth-code',
        'waba_id' => 'waba-coex',
        'finish_type' => 'FINISH_WHATSAPP_BUSINESS_APP_ONBOARDING',
        'onboarding_mode' => 'coexistence',
    ]);

    $response->assertOk()
        ->assertJsonPath('phone_number_id', 'phone-coex')
        ->assertJsonPath('business_id', 'business-coex')
        ->assertJsonPath('coexistence', true)
        ->assertJsonPath('onboarding_mode', 'coexistence');

    $cached = Cache::get('meta_signup:'.$response->json('signup_token'));
    expect($cached['coexistence'])->toBeTrue();
    expect($cached['onboarding_mode'])->toBe('coexistence');
});

it('caches an already registered Cloud API WABA as send only', function (): void {
    Http::fake([
        'graph.facebook.com/v23.0/oauth/access_token*' => Http::response(['access_token' => 'user-token']),
        'graph.facebook.com/v23.0/waba-existing/phone_numbers*' => Http::response([
            'data' => [['id' => 'phone-existing']],
        ]),
        'graph.facebook.com/v23.0/waba-existing*' => Http::response([
            'owner_business_info' => ['id' => 'business-existing'],
        ]),
    ]);

    $user = userWithTenant();

    $response = $this->actingAs($user)->postJson(route('whatsapp.meta.embedded-signup'), [
        'code' => 'oauth-code',
        'waba_id' => 'waba-existing',
        'phone_number_id' => 'phone-existing',
        'business_id' => 'business-existing',
        'finish_type' => 'FINISH',
        'onboarding_mode' => 'existing_cloud_api',
    ]);

    $response->assertOk()
        ->assertJsonPath('coexistence', false)
        ->assertJsonPath('onboarding_mode', 'existing_cloud_api');

    $cached = Cache::get('meta_signup:'.$response->json('signup_token'));
    expect($cached['onboarding_mode'])->toBe('existing_cloud_api');
});

it('rejects a coexistence finish event for a standard Cloud API mode', function (): void {
    $user = userWithTenant();

    $this->actingAs($user)->postJson(route('whatsapp.meta.embedded-signup'), [
        'code' => 'oauth-code',
        'waba_id' => 'waba-mismatched-mode',
        'finish_type' => 'FINISH_WHATSAPP_BUSINESS_APP_ONBOARDING',
        'onboarding_mode' => 'existing_cloud_api',
    ])->assertUnprocessable()
        ->assertJsonPath('message', 'O modo concluído pela Meta não corresponde ao modo de conexão selecionado.');

    Http::assertNothingSent();
});

it('rejects an asset combination from different Meta business portfolios', function (): void {
    Http::fake([
        'graph.facebook.com/v23.0/oauth/access_token*' => Http::response(['access_token' => 'user-token']),
        'graph.facebook.com/v23.0/waba-mismatch/phone_numbers*' => Http::response([
            'data' => [['id' => 'phone-mismatch']],
        ]),
        'graph.facebook.com/v23.0/waba-mismatch*' => Http::response([
            'owner_business_info' => ['id' => 'actual-business'],
        ]),
    ]);

    $user = userWithTenant();

    $this->actingAs($user)->postJson(route('whatsapp.meta.embedded-signup'), [
        'code' => 'oauth-code',
        'waba_id' => 'waba-mismatch',
        'phone_number_id' => 'phone-mismatch',
        'business_id' => 'different-business',
        'finish_type' => 'FINISH',
    ])->assertUnprocessable()
        ->assertJsonPath('message', 'A conta do WhatsApp não pertence ao portfólio empresarial retornado pela Meta.');
});
