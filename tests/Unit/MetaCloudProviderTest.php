<?php

use App\Exceptions\MetaAmbiguousSendException;
use App\Exceptions\MetaApiException;
use App\Exceptions\MetaInvalidNumberException;
use App\Exceptions\MetaNoWhatsAppException;
use App\Exceptions\MetaRateLimitException;
use App\Services\WhatsApp\Providers\MetaCloudProvider;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

function makeProvider(string $appSecret = 'test-secret'): MetaCloudProvider
{
    return new MetaCloudProvider(
        phoneNumberId: '123456789',
        accessToken: 'test-token',
        appSecret: $appSecret,
        graphApiVersion: 'v23.0',
    );
}

// ─── sendText ─────────────────────────────────────────────────────────────────

it('sendText posts correct payload to Meta messages endpoint', function (): void {
    Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.test']]], 200)]);

    $wamid = makeProvider()->sendText('5511999999999', 'Hello World');

    expect($wamid)->toBe('wamid.test');

    Http::assertSent(function ($request) {
        return str_contains($request->url(), 'graph.facebook.com/v23.0/123456789/messages')
            && $request->hasHeader('Authorization', 'Bearer test-token')
            && $request['messaging_product'] === 'whatsapp'
            && $request['type'] === 'text'
            && $request['text']['body'] === 'Hello World'
            && $request['to'] === '5511999999999';
    });
});

// ─── sendTemplate ─────────────────────────────────────────────────────────────

it('sendTemplate posts type=template with name and language', function (): void {
    Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.test']]], 200)]);

    $wamid = makeProvider()->sendTemplate('5511999999999', 'hello_world', 'pt_BR', []);

    expect($wamid)->toBe('wamid.test');

    Http::assertSent(function ($request) {
        return $request['type'] === 'template'
            && $request['template']['name'] === 'hello_world'
            && $request['template']['language']['code'] === 'pt_BR';
    });
});

// ─── sendMedia ────────────────────────────────────────────────────────────────

it('sendMedia with URL uses link field and correct type', function (): void {
    Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.test']]], 200)]);

    makeProvider()->sendMedia('5511999999999', 'https://example.com/image.jpg', 'image/jpeg', 'image', null, 'caption test');

    Http::assertSent(function ($request) {
        return $request['type'] === 'image'
            && $request['image']['link'] === 'https://example.com/image.jpg'
            && $request['image']['caption'] === 'caption test';
    });
});

// ─── error codes ──────────────────────────────────────────────────────────────

it('error code 130429 throws MetaRateLimitException', function (): void {
    Http::fake(['graph.facebook.com/*' => Http::response(['error' => ['code' => 130429, 'message' => 'Rate limit']], 429)]);

    expect(fn () => makeProvider()->sendText('5511999999999', 'test'))
        ->toThrow(MetaRateLimitException::class);
});

it('re-engagement code 131047 maps to permanent-skip, not invalid number', function (): void {
    // Meta 131047 = re-engagement message (24h window expired), NOT an invalid number.
    Http::fake(['graph.facebook.com/*' => Http::response(['error' => ['code' => 131047, 'message' => 'Re-engagement message']], 400)]);

    expect(fn () => makeProvider()->sendText('5511999999999', 'test'))
        ->toThrow(MetaNoWhatsAppException::class)
        ->and(fn () => makeProvider()->sendText('5511999999999', 'test'))
        ->not->toThrow(MetaInvalidNumberException::class);
});

it('error code 131026 throws MetaNoWhatsAppException', function (): void {
    Http::fake(['graph.facebook.com/*' => Http::response(['error' => ['code' => 131026, 'message' => 'No WhatsApp']], 400)]);

    expect(fn () => makeProvider()->sendText('5511999999999', 'test'))
        ->toThrow(MetaNoWhatsAppException::class);
});

it('per-user marketing-limit codes are permanent-skip', function (int $code): void {
    Http::fake(['graph.facebook.com/*' => Http::response(['error' => ['code' => $code, 'message' => 'Not delivered']], 400)]);

    expect(fn () => makeProvider()->sendText('5511999999999', 'test'))
        ->toThrow(MetaNoWhatsAppException::class);
})->with([131049, 130472]);

it('spam/throttle codes are retriable rate-limit errors', function (int $code): void {
    Http::fake(['graph.facebook.com/*' => Http::response(['error' => ['code' => $code, 'message' => 'Throttled']], 400)]);

    expect(fn () => makeProvider()->sendText('5511999999999', 'test'))
        ->toThrow(MetaRateLimitException::class);
})->with([131048, 80007]);

it('unknown error code throws base MetaApiException', function (): void {
    Http::fake(['graph.facebook.com/*' => Http::response(['error' => ['code' => 500, 'message' => 'Server error']], 500)]);

    expect(fn () => makeProvider()->sendText('5511999999999', 'test'))
        ->toThrow(MetaApiException::class);
});

// ─── ambiguous-send classification (IDEM-A) ────────────────────────────────────

it('injects biz_opaque_callback_data when an opaque id is provided', function (): void {
    Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.test']]], 200)]);

    makeProvider()->sendText('5511999999999', 'Hello', 'opaque-key-123');

    Http::assertSent(fn ($request) => $request['biz_opaque_callback_data'] === 'opaque-key-123');
});

it('omits biz_opaque_callback_data when no opaque id is provided', function (): void {
    Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.test']]], 200)]);

    makeProvider()->sendText('5511999999999', 'Hello');

    Http::assertSent(fn ($request) => ! isset($request['biz_opaque_callback_data']));
});

it('maps a 5xx response to an ambiguous-send exception (not blind-retryable)', function (): void {
    Http::fake(['graph.facebook.com/*' => Http::response('upstream error', 503)]);

    expect(fn () => makeProvider()->sendText('5511999999999', 'test'))
        ->toThrow(MetaAmbiguousSendException::class);
});

it('maps a transport timeout to an ambiguous-send exception', function (): void {
    Http::fake(fn () => throw new ConnectionException('cURL error 28: Operation timed out after 15000 ms'));

    expect(fn () => makeProvider()->sendText('5511999999999', 'test'))
        ->toThrow(MetaAmbiguousSendException::class);
});

it('rethrows a connection-refused failure as retryable (definitely not sent)', function (): void {
    Http::fake(fn () => throw new ConnectionException('cURL error 7: Failed to connect to graph.facebook.com'));

    expect(fn () => makeProvider()->sendText('5511999999999', 'test'))
        ->toThrow(ConnectionException::class)
        ->and(fn () => makeProvider()->sendText('5511999999999', 'test'))
        ->not->toThrow(MetaAmbiguousSendException::class);
});

// ─── verifyWebhook ────────────────────────────────────────────────────────────

it('logs Meta synchronous error audit fields', function (): void {
    Log::shouldReceive('error')
        ->once()
        ->with('meta.api_error', Mockery::on(fn (array $context): bool => $context['status'] === 400
            && $context['code'] === 131000
            && $context['type'] === 'OAuthException'
            && $context['error_subcode'] === 2494010
            && $context['fbtrace_id'] === 'FBTRACE123'
            && $context['message'] === 'Template rejected'));

    Http::fake(['graph.facebook.com/*' => Http::response([
        'error' => [
            'message' => 'Template rejected',
            'type' => 'OAuthException',
            'code' => 131000,
            'error_subcode' => 2494010,
            'fbtrace_id' => 'FBTRACE123',
        ],
    ], 400)]);

    expect(fn () => makeProvider()->sendText('5511999999999', 'test'))
        ->toThrow(MetaApiException::class);
});

it('verifyWebhook accepts valid HMAC signature', function (): void {
    $body = '{"test":true}';
    $sig = 'sha256='.hash_hmac('sha256', $body, 'test-secret');
    $request = Request::create('/webhooks/meta', 'POST', [], [], [], ['HTTP_X_HUB_SIGNATURE_256' => $sig], $body);

    expect(makeProvider()->verifyWebhook($request))->toBeTrue();
});

it('verifyWebhook rejects tampered body', function (): void {
    $sig = 'sha256='.hash_hmac('sha256', '{"test":true}', 'test-secret');
    $request = Request::create('/webhooks/meta', 'POST', [], [], [], ['HTTP_X_HUB_SIGNATURE_256' => $sig], '{"test":false}');

    expect(makeProvider()->verifyWebhook($request))->toBeFalse();
});

it('verifyWebhook rejects missing header', function (): void {
    $request = Request::create('/webhooks/meta', 'POST', [], [], [], [], '{"test":true}');

    expect(makeProvider()->verifyWebhook($request))->toBeFalse();
});

it('verifyWebhook rejects header without sha256= prefix', function (): void {
    $body = '{"test":true}';
    $sig = hash_hmac('sha256', $body, 'test-secret'); // no prefix
    $request = Request::create('/webhooks/meta', 'POST', [], [], [], ['HTTP_X_HUB_SIGNATURE_256' => $sig], $body);

    expect(makeProvider()->verifyWebhook($request))->toBeFalse();
});

it('verifyWebhook fails closed when appSecret is empty', function (): void {
    $body = '{"test":true}';
    $sig = 'sha256='.hash_hmac('sha256', $body, '');
    $request = Request::create('/webhooks/meta', 'POST', [], [], [], ['HTTP_X_HUB_SIGNATURE_256' => $sig], $body);

    expect(makeProvider(appSecret: '')->verifyWebhook($request))->toBeFalse();
});

// ─── parseWebhook ─────────────────────────────────────────────────────────────

it('parseWebhook extracts text message correctly', function (): void {
    $payload = [
        'object' => 'whatsapp_business_account',
        'entry' => [[
            'changes' => [[
                'field' => 'messages',
                'value' => [
                    'messaging_product' => 'whatsapp',
                    'metadata' => ['phone_number_id' => '123456789'],
                    'contacts' => [['profile' => ['name' => 'João Silva'], 'wa_id' => '5511999999999']],
                    'messages' => [[
                        'from' => '5511999999999',
                        'id' => 'wamid.ABC123',
                        'timestamp' => '1713000000',
                        'type' => 'text',
                        'text' => ['body' => 'Olá'],
                    ]],
                ],
            ]],
        ]],
    ];
    $request = Request::create('/webhooks/meta', 'POST', $payload);

    $dto = makeProvider()->parseWebhook($request);

    expect($dto)->not->toBeNull()
        ->and($dto->phone)->toBe('5511999999999')
        ->and($dto->text)->toBe('Olá')
        ->and($dto->pushName)->toBe('João Silva')
        ->and($dto->messageId)->toBe('wamid.ABC123')
        ->and($dto->hasMedia)->toBeFalse()
        ->and($dto->fromMe)->toBeFalse()
        ->and($dto->instanceName)->toBe('123456789');
});

it('parseWebhook returns null when no messages key', function (): void {
    $payload = [
        'object' => 'whatsapp_business_account',
        'entry' => [[
            'changes' => [[
                'field' => 'messages',
                'value' => [
                    'statuses' => [['id' => 'wamid.ABC', 'status' => 'delivered']],
                ],
            ]],
        ]],
    ];
    $request = Request::create('/webhooks/meta', 'POST', $payload);

    expect(makeProvider()->parseWebhook($request))->toBeNull();
});

it('parseWebhook detects media messages correctly', function (): void {
    $payload = [
        'entry' => [[
            'changes' => [[
                'value' => [
                    'contacts' => [['profile' => ['name' => 'Test']]],
                    'messages' => [[
                        'from' => '5511999999999',
                        'id' => 'wamid.IMG',
                        'type' => 'image',
                        'image' => ['id' => 'media123', 'mime_type' => 'image/jpeg', 'sha256' => 'abc'],
                    ]],
                ],
            ]],
        ]],
    ];
    $request = Request::create('/webhooks/meta', 'POST', $payload);

    $dto = makeProvider()->parseWebhook($request);

    expect($dto)->not->toBeNull()
        ->and($dto->hasMedia)->toBeTrue();
});

it('parseWebhook ignores status-only payloads', function (): void {
    $payload = [
        'entry' => [[
            'changes' => [[
                'value' => [
                    'statuses' => [['status' => 'read', 'id' => 'wamid.ABC']],
                ],
            ]],
        ]],
    ];
    $request = Request::create('/webhooks/meta', 'POST', $payload);

    expect(makeProvider()->parseWebhook($request))->toBeNull();
});
