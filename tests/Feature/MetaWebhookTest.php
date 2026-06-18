<?php

use App\Jobs\AggregateDebouncedMessageJob;
use App\Jobs\DownloadIncomingMediaJob;
use App\Jobs\ProcessCampaignDeliveryEventJob;
use App\Jobs\ProcessIncomingWhatsAppMessageJob;
use App\Models\AgentInteractionEvent;
use App\Models\Campaign;
use App\Models\WhatsappInstance;
use App\Models\WhatsappTemplate;
use App\Services\DebounceService;
use Illuminate\Support\Facades\Queue;

function metaSignedHeaders(string $body, string $secret): array
{
    return ['X-Hub-Signature-256' => 'sha256='.hash_hmac('sha256', $body, $secret)];
}

function metaTextPayload(string $phoneNumberId, string $wabaId, string $from = '5511999999999', string $text = 'Olá'): array
{
    return [
        'object' => 'whatsapp_business_account',
        'entry' => [[
            'id' => $wabaId,
            'changes' => [[
                'field' => 'messages',
                'value' => [
                    'messaging_product' => 'whatsapp',
                    'metadata' => ['display_phone_number' => '551100000000', 'phone_number_id' => $phoneNumberId],
                    'contacts' => [['profile' => ['name' => 'Test User'], 'wa_id' => $from]],
                    'messages' => [[
                        'from' => $from,
                        'id' => 'wamid.TEST',
                        'timestamp' => (string) time(),
                        'type' => 'text',
                        'text' => ['body' => $text],
                    ]],
                ],
            ]],
        ]],
    ];
}

// ─── GET verify ──────────────────────────────────────────────────────────────

it('GET verify returns 200 with challenge when verify_token matches', function (): void {
    config()->set('services.meta.verify_token', 'verify-secret-123');

    $response = $this->get('/api/webhooks/meta?hub_mode=subscribe&hub_verify_token=verify-secret-123&hub_challenge=randomchallenge');

    $response->assertStatus(200)->assertSee('randomchallenge');
});

it('GET verify returns 403 when verify_token is wrong', function (): void {
    config()->set('services.meta.verify_token', 'correct-token');

    $response = $this->get('/api/webhooks/meta?hub_mode=subscribe&hub_verify_token=wrong-token&hub_challenge=xyz');

    $response->assertStatus(403);
});

it('GET verify returns 403 when hub_mode is missing', function (): void {
    config()->set('services.meta.verify_token', 'correct-token');

    $response = $this->get('/api/webhooks/meta?hub_verify_token=correct-token&hub_challenge=xyz');

    $response->assertStatus(403);
});

// ─── POST handle ─────────────────────────────────────────────────────────────

it('POST returns 401 when HMAC signature is invalid', function (): void {
    config()->set('services.meta.app_secret', 'test-secret');

    $instance = WhatsappInstance::factory()->metaCloud()->create([
        'meta_phone_number_id' => '999111',
    ]);

    $payload = metaTextPayload('999111', $instance->meta_waba_id);
    $body = json_encode($payload);

    $response = $this->call(
        'POST',
        '/api/webhooks/meta',
        [],
        [],
        [],
        ['HTTP_X_HUB_SIGNATURE_256' => 'sha256=invalidsignature', 'CONTENT_TYPE' => 'application/json'],
        $body
    );

    $response->assertStatus(401);
});

it('POST returns 204 silently when phone_number_id is unknown', function (): void {
    config()->set('services.meta.app_secret', 'test-secret');

    $payload = metaTextPayload('UNKNOWN_ID', 'UNKNOWN_WABA');
    $body = json_encode($payload);
    $headers = metaSignedHeaders($body, 'test-secret');

    $response = $this->call(
        'POST',
        '/api/webhooks/meta',
        [],
        [],
        [],
        ['HTTP_X_HUB_SIGNATURE_256' => $headers['X-Hub-Signature-256'], 'CONTENT_TYPE' => 'application/json'],
        $body
    );

    $response->assertNoContent();
});

it('POST dispatches DownloadIncomingMediaJob and records interaction for a media message', function (): void {
    Queue::fake();
    config()->set('services.meta.app_secret', 'test-secret');

    $instance = WhatsappInstance::factory()->metaCloud()->create([
        'meta_phone_number_id' => '888780',
    ]);

    $payload = [
        'object' => 'whatsapp_business_account',
        'entry' => [[
            'id' => $instance->meta_waba_id,
            'changes' => [[
                'field' => 'messages',
                'value' => [
                    'messaging_product' => 'whatsapp',
                    'metadata' => ['phone_number_id' => '888780'],
                    'contacts' => [['profile' => ['name' => 'Media User'], 'wa_id' => '5511988887777']],
                    'messages' => [[
                        'from' => '5511988887777',
                        'id' => 'wamid.MEDIA',
                        'timestamp' => (string) time(),
                        'type' => 'image',
                        'image' => ['id' => 'media-123', 'mime_type' => 'image/jpeg'],
                    ]],
                ],
            ]],
        ]],
    ];
    $body = json_encode($payload);
    $headers = metaSignedHeaders($body, 'test-secret');

    $this->call(
        'POST',
        '/api/webhooks/meta',
        [],
        [],
        [],
        ['HTTP_X_HUB_SIGNATURE_256' => $headers['X-Hub-Signature-256'], 'CONTENT_TYPE' => 'application/json'],
        $body
    )->assertNoContent();

    Queue::assertNotPushed(ProcessIncomingWhatsAppMessageJob::class);
    Queue::assertNotPushed(AggregateDebouncedMessageJob::class);
    Queue::assertPushed(DownloadIncomingMediaJob::class, fn (DownloadIncomingMediaJob $job): bool => $job->instanceId === $instance->id
        && $job->phone === '5511988887777'
        && $job->providerMessageId === 'wamid.MEDIA');

    expect(AgentInteractionEvent::withoutGlobalScope('tenant')
        ->where('event_type', 'webhook_received')
        ->where('event_source', 'meta_webhook_controller')
        ->exists())->toBeTrue();
});

it('POST dispatches ProcessIncomingWhatsAppMessageJob for known instance with text message', function (): void {
    Queue::fake();
    config()->set('services.meta.app_secret', 'test-secret');

    $instance = WhatsappInstance::factory()->metaCloud()->create([
        'meta_phone_number_id' => '888777',
    ]);

    $payload = metaTextPayload('888777', $instance->meta_waba_id, text: 'oi');
    $body = json_encode($payload);
    $headers = metaSignedHeaders($body, 'test-secret');

    $this->call(
        'POST',
        '/api/webhooks/meta',
        [],
        [],
        [],
        ['HTTP_X_HUB_SIGNATURE_256' => $headers['X-Hub-Signature-256'], 'CONTENT_TYPE' => 'application/json'],
        $body
    );

    Queue::assertPushed(ProcessIncomingWhatsAppMessageJob::class, fn (ProcessIncomingWhatsAppMessageJob $job): bool => $job->agentId === null
        && $job->tenantId === (string) $instance->tenant_id
        && $job->instanceName === $instance->name
        && $job->providerMessageId === 'wamid.TEST');
});

it('POST schedules AggregateDebouncedMessageJob for a non-quick text message', function (): void {
    Queue::fake();
    config()->set('services.meta.app_secret', 'test-secret');
    config()->set('credflow.debounce_seconds', 3);

    // Avoid Redis in tests: only assert that a non-quick message is buffered
    // and the delayed drain job is scheduled (no immediate processing).
    $this->mock(DebounceService::class, function ($mock): void {
        $mock->shouldReceive('isQuickCommand')->andReturn(false);
        $mock->shouldReceive('push')->andReturn(true);
    });

    $instance = WhatsappInstance::factory()->metaCloud()->create([
        'meta_phone_number_id' => '888778',
    ]);

    $payload = metaTextPayload('888778', $instance->meta_waba_id, text: 'Quero saber sobre crédito consignado');
    $body = json_encode($payload);
    $headers = metaSignedHeaders($body, 'test-secret');

    $this->call(
        'POST',
        '/api/webhooks/meta',
        [],
        [],
        [],
        ['HTTP_X_HUB_SIGNATURE_256' => $headers['X-Hub-Signature-256'], 'CONTENT_TYPE' => 'application/json'],
        $body
    )->assertNoContent();

    Queue::assertNotPushed(ProcessIncomingWhatsAppMessageJob::class);
    Queue::assertPushed(AggregateDebouncedMessageJob::class, fn (AggregateDebouncedMessageJob $job): bool => $job->phone === '5511999999999'
        && $job->tenantId === (string) $instance->tenant_id
        && $job->instanceName === $instance->name);
});

it('POST does not schedule the drain job when the message is not the first in the window', function (): void {
    Queue::fake();
    config()->set('services.meta.app_secret', 'test-secret');

    $this->mock(DebounceService::class, function ($mock): void {
        $mock->shouldReceive('isQuickCommand')->andReturn(false);
        $mock->shouldReceive('push')->andReturn(false);
    });

    $instance = WhatsappInstance::factory()->metaCloud()->create([
        'meta_phone_number_id' => '888779',
    ]);

    $payload = metaTextPayload('888779', $instance->meta_waba_id, text: 'Segunda mensagem rápida em sequência');
    $body = json_encode($payload);
    $headers = metaSignedHeaders($body, 'test-secret');

    $this->call(
        'POST',
        '/api/webhooks/meta',
        [],
        [],
        [],
        ['HTTP_X_HUB_SIGNATURE_256' => $headers['X-Hub-Signature-256'], 'CONTENT_TYPE' => 'application/json'],
        $body
    )->assertNoContent();

    Queue::assertNotPushed(AggregateDebouncedMessageJob::class);
    Queue::assertNotPushed(ProcessIncomingWhatsAppMessageJob::class);
});

it('POST dispatches delivery tracking job when payload has statuses', function (): void {
    Queue::fake();
    config()->set('services.meta.app_secret', 'test-secret');

    $instance = WhatsappInstance::factory()->metaCloud()->create([
        'meta_phone_number_id' => '777666',
    ]);

    $payload = [
        'entry' => [[
            'id' => $instance->meta_waba_id,
            'changes' => [[
                'field' => 'messages',
                'value' => [
                    'messaging_product' => 'whatsapp',
                    'metadata' => ['phone_number_id' => '777666'],
                    'statuses' => [['id' => 'wamid.ABC', 'status' => 'delivered', 'timestamp' => '123', 'recipient_id' => '5511']],
                ],
            ]],
        ]],
    ];
    $body = json_encode($payload);
    $headers = metaSignedHeaders($body, 'test-secret');

    $response = $this->call(
        'POST',
        '/api/webhooks/meta',
        [],
        [],
        [],
        ['HTTP_X_HUB_SIGNATURE_256' => $headers['X-Hub-Signature-256'], 'CONTENT_TYPE' => 'application/json'],
        $body
    );

    $response->assertNoContent();
    Queue::assertNotPushed(ProcessIncomingWhatsAppMessageJob::class);
    Queue::assertPushed(ProcessCampaignDeliveryEventJob::class, fn ($job) => $job->providerMessageId === 'wamid.ABC'
        && $job->eventType === 'delivered');
});

it('POST dispatches an inbound wamid only once across duplicate deliveries (replay guard)', function (): void {
    Queue::fake();
    config()->set('services.meta.app_secret', 'test-secret');

    $instance = WhatsappInstance::factory()->metaCloud()->create([
        'meta_phone_number_id' => '888781',
    ]);

    // 'oi' is a quick command → immediate ProcessIncomingWhatsAppMessageJob dispatch (no Redis debounce).
    $payload = metaTextPayload('888781', $instance->meta_waba_id, text: 'oi');
    $body = json_encode($payload);
    $headers = metaSignedHeaders($body, 'test-secret');

    $deliver = fn () => $this->call(
        'POST',
        '/api/webhooks/meta',
        [],
        [],
        [],
        ['HTTP_X_HUB_SIGNATURE_256' => $headers['X-Hub-Signature-256'], 'CONTENT_TYPE' => 'application/json'],
        $body
    );

    $deliver()->assertSuccessful();
    $deliver()->assertSuccessful();

    Queue::assertPushed(ProcessIncomingWhatsAppMessageJob::class, 1);
});

it('POST updates template status and pauses campaigns on rejected template webhook', function (): void {
    config()->set('services.meta.app_secret', 'test-secret');

    $instance = WhatsappInstance::factory()->metaCloud()->create([
        'meta_phone_number_id' => '777665',
    ]);
    $template = WhatsappTemplate::factory()->create([
        'tenant_id' => $instance->tenant_id,
        'whatsapp_instance_id' => $instance->id,
        'meta_template_name' => 'promo',
        'status' => 'APPROVED',
    ]);
    $campaign = Campaign::factory()->sending()->create([
        'tenant_id' => $instance->tenant_id,
        'whatsapp_instance_id' => $instance->id,
        'whatsapp_template_id' => $template->id,
    ]);

    $payload = [
        'entry' => [[
            'id' => $instance->meta_waba_id,
            'changes' => [[
                'field' => 'message_template_status_update',
                'value' => [
                    'event' => 'REJECTED',
                    'message_template_name' => 'promo',
                    'message_template_language' => 'pt_BR',
                    'reason' => 'INVALID_FORMAT',
                ],
            ]],
        ]],
    ];
    $body = json_encode($payload);
    $headers = metaSignedHeaders($body, 'test-secret');

    $this->call(
        'POST',
        '/api/webhooks/meta',
        [],
        [],
        [],
        ['HTTP_X_HUB_SIGNATURE_256' => $headers['X-Hub-Signature-256'], 'CONTENT_TYPE' => 'application/json'],
        $body
    )->assertNoContent();

    expect($template->fresh()->status)->toBe('REJECTED');
    expect($template->fresh()->rejected_reason)->toBe('INVALID_FORMAT');
    expect($campaign->fresh()->status)->toBe('paused');
});
