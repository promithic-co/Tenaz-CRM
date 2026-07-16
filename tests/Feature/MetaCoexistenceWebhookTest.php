<?php

use App\Jobs\AggregateDebouncedMessageJob;
use App\Jobs\ProcessIncomingWhatsAppMessageJob;
use App\Jobs\ProcessMetaCoexistenceWebhookJob;
use App\Jobs\SyncMetaCoexistenceDataJob;
use App\Models\Contact;
use App\Models\ConversationTimelineMessage;
use App\Models\Lead;
use App\Models\WhatsappInstance;
use App\Services\WhatsApp\MetaCoexistenceWebhookService;
use App\Services\WhatsApp\MetaTokenExchangeService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

function coexistenceInstance(): WhatsappInstance
{
    $user = userWithTenant();

    return WhatsappInstance::factory()->for($user)->metaCloud()->create([
        'tenant_id' => (string) $user->tenantId,
        'meta_phone_number_id' => 'phone-coexistence',
        'meta_waba_id' => 'waba-coexistence',
        'meta_access_token' => 'coexistence-token',
        'meta_coexistence' => true,
    ]);
}

it('queues coexistence webhook payloads after signature and instance validation', function (): void {
    Queue::fake();
    config()->set('services.meta.app_secret', 'test-secret');
    $instance = coexistenceInstance();

    $payload = [
        'object' => 'whatsapp_business_account',
        'entry' => [[
            'id' => $instance->meta_waba_id,
            'changes' => [[
                'field' => 'smb_message_echoes',
                'value' => [
                    'messaging_product' => 'whatsapp',
                    'metadata' => ['phone_number_id' => $instance->meta_phone_number_id],
                    'message_echoes' => [[
                        'from' => '5511000000000',
                        'to' => '5511999999999',
                        'id' => 'wamid.ECHO',
                        'timestamp' => '1739321024',
                        'type' => 'text',
                        'text' => ['body' => 'Mensagem pelo celular'],
                    ]],
                ],
            ]],
        ]],
    ];
    $body = json_encode($payload, JSON_THROW_ON_ERROR);
    $signature = 'sha256='.hash_hmac('sha256', $body, 'test-secret');

    $this->call(
        'POST',
        '/api/webhooks/meta',
        [],
        [],
        [],
        ['HTTP_X_HUB_SIGNATURE_256' => $signature, 'CONTENT_TYPE' => 'application/json'],
        $body,
    )->assertNoContent();

    Queue::assertPushed(ProcessMetaCoexistenceWebhookJob::class, fn (ProcessMetaCoexistenceWebhookJob $job): bool => $job->instanceId === $instance->id
        && $job->field === 'smb_message_echoes');
});

it('mirrors app message echoes into the conversation timeline idempotently', function (): void {
    Event::fake();
    $instance = coexistenceInstance();
    $service = app(MetaCoexistenceWebhookService::class);
    $value = [
        'message_echoes' => [[
            'from' => '5511000000000',
            'to' => '5511999999999',
            'id' => 'wamid.ECHO.PERSISTED',
            'timestamp' => '1739321024',
            'type' => 'text',
            'text' => ['body' => 'Atendimento pelo WhatsApp Business'],
        ]],
    ];

    $service->process($instance, 'smb_message_echoes', $value);
    $service->process($instance, 'smb_message_echoes', $value);

    $lead = Lead::withoutGlobalScopes()->where('whatsapp', '5511999999999')->firstOrFail();
    $message = ConversationTimelineMessage::where('lead_id', $lead->id)->firstOrFail();

    expect($message->direction)->toBe('outbound')
        ->and($message->sender_type)->toBe('human')
        ->and($message->source)->toBe('whatsapp_business_app')
        ->and($message->body)->toBe('Atendimento pelo WhatsApp Business')
        ->and(ConversationTimelineMessage::where('provider_message_id', 'wamid.ECHO.PERSISTED')->count())->toBe(1);
});

it('imports app contacts and records removals without deleting CRM data', function (): void {
    $instance = coexistenceInstance();
    $service = app(MetaCoexistenceWebhookService::class);
    $lead = Lead::withoutGlobalScopes()->create([
        'tenant_id' => (string) $instance->tenant_id,
        'whatsapp' => '5511888888888',
        'status' => 'novo',
        'modo' => 'receptivo',
        'evolution_instance' => $instance->name,
        'whatsapp_instance_id' => $instance->id,
    ]);

    $service->process($instance, 'smb_app_state_sync', [
        'state_sync' => [[
            'type' => 'contact',
            'action' => 'add',
            'contact' => [
                'full_name' => 'Maria Cliente',
                'phone_number' => '5511888888888',
            ],
            'metadata' => ['timestamp' => '1739321000'],
        ]],
    ]);

    $contact = Contact::withoutGlobalScopes()->where('phone', '5511888888888')->firstOrFail();
    expect($contact->name)->toBe('Maria Cliente')
        ->and($contact->source)->toBe(Contact::SOURCE_WHATSAPP_APP_SYNC);
    expect($lead->refresh()->nome)->toBe('Maria Cliente')
        ->and($lead->contact_id)->toBe($contact->id);

    $service->process($instance, 'smb_app_state_sync', [
        'state_sync' => [[
            'type' => 'contact',
            'action' => 'remove',
            'contact' => ['phone_number' => '5511888888888'],
            'metadata' => ['timestamp' => '1739322000'],
        ]],
    ]);

    $contact->refresh();
    expect($contact->trashed())->toBeFalse()
        ->and($contact->extra_data['whatsapp_app_sync_action'])->toBe('remove')
        ->and($contact->extra_data['whatsapp_app_removed_at'])->toBe('1739322000');
});

it('imports historical messages without routing them through inbound automation', function (): void {
    Queue::fake();
    $instance = coexistenceInstance();

    app(MetaCoexistenceWebhookService::class)->process($instance, 'history', [
        'metadata' => ['display_phone_number' => '5511000000000'],
        'history' => [[
            'metadata' => ['phase' => 0, 'chunk_order' => 1, 'progress' => 100],
            'threads' => [[
                'id' => '5511777777777',
                'messages' => [[
                    'from' => '5511777777777',
                    'id' => 'wamid.HISTORY.INBOUND',
                    'timestamp' => '1739000000',
                    'type' => 'text',
                    'text' => ['body' => 'Mensagem antiga'],
                    'history_context' => ['status' => 'READ'],
                ], [
                    'from' => '5511000000000',
                    'to' => '5511777777777',
                    'id' => 'wamid.HISTORY.OUTBOUND',
                    'timestamp' => '1739000100',
                    'type' => 'text',
                    'text' => ['body' => 'Resposta antiga'],
                    'history_context' => ['status' => 'DELIVERED'],
                ]],
            ]],
        ]],
    ]);

    expect(ConversationTimelineMessage::where('source', 'whatsapp_app_history')->count())->toBe(2);
    expect(ConversationTimelineMessage::where('provider_message_id', 'wamid.HISTORY.INBOUND')->value('direction'))->toBe('inbound');
    expect(ConversationTimelineMessage::where('provider_message_id', 'wamid.HISTORY.OUTBOUND')->value('direction'))->toBe('outbound');
    Queue::assertNotPushed(ProcessIncomingWhatsAppMessageJob::class);
    Queue::assertNotPushed(AggregateDebouncedMessageJob::class);
});

it('enriches a historical media placeholder when Meta sends the media detail', function (): void {
    $instance = coexistenceInstance();
    $service = app(MetaCoexistenceWebhookService::class);

    $historyValue = fn (array $message): array => [
        'metadata' => ['display_phone_number' => '5511000000000'],
        'history' => [[
            'threads' => [[
                'id' => '5511666666666',
                'messages' => [$message],
            ]],
        ]],
    ];

    $service->process($instance, 'history', $historyValue([
        'from' => '5511666666666',
        'id' => 'wamid.HISTORY.MEDIA',
        'timestamp' => '1739000000',
        'type' => 'media_placeholder',
    ]));
    $service->process($instance, 'history', $historyValue([
        'from' => '5511666666666',
        'id' => 'wamid.HISTORY.MEDIA',
        'timestamp' => '1739000000',
        'type' => 'image',
        'image' => ['id' => 'media-id', 'mime_type' => 'image/jpeg', 'caption' => 'Comprovante'],
    ]));

    $message = ConversationTimelineMessage::where('provider_message_id', 'wamid.HISTORY.MEDIA')->firstOrFail();
    expect($message->body)->toBe('Comprovante')
        ->and($message->media['type'])->toBe('image')
        ->and(ConversationTimelineMessage::where('provider_message_id', 'wamid.HISTORY.MEDIA')->count())->toBe(1);
});

it('requests the one-time contact and history sync for coexistence', function (): void {
    $instance = coexistenceInstance();
    Http::fake([
        'graph.facebook.com/v23.0/phone-coexistence/smb_app_data' => Http::response([
            'messaging_product' => 'whatsapp',
            'request_id' => 'sync-request',
        ]),
    ]);

    (new SyncMetaCoexistenceDataJob($instance->id))->handle(app(MetaTokenExchangeService::class));

    Http::assertSentCount(2);
    Http::assertSent(fn ($request): bool => $request['sync_type'] === 'smb_app_state_sync');
    Http::assertSent(fn ($request): bool => $request['sync_type'] === 'history');
    expect(Cache::has("meta_coexistence_sync:{$instance->id}:smb_app_state_sync"))->toBeTrue();
    expect(Cache::has("meta_coexistence_sync:{$instance->id}:history"))->toBeTrue();
});
