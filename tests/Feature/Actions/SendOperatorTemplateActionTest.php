<?php

use App\Actions\SendOperatorTemplateAction;
use App\Models\ConversationTimelineMessage;
use App\Models\Lead;
use App\Models\User;
use App\Models\WhatsappInstance;
use App\Models\WhatsappOutboxMessage;
use App\Models\WhatsappTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

function templateBody(): array
{
    return [
        [
            'type' => 'BODY',
            'text' => 'Olá {{1}}, sua proposta {{2}} está pronta.',
            'example' => ['body_text' => [['Maria', '#0']]],
        ],
    ];
}

/**
 * @return array{0: Lead, 1: WhatsappTemplate, 2: User}
 */
function templateScenario(array $templateOverrides = []): array
{
    $user = User::factory()->create();
    $tenant = (string) $user->tenantId;

    $instance = WhatsappInstance::factory()->create([
        'user_id' => $user->id,
        'tenant_id' => $tenant,
        'meta_waba_id' => 'WABA-1',
    ]);

    $template = WhatsappTemplate::factory()->create(array_merge([
        'tenant_id' => $tenant,
        'whatsapp_instance_id' => $instance->id,
        'meta_waba_id' => 'WABA-1',
        'status' => 'APPROVED',
        'meta_template_name' => 'promo_proposta',
        'language' => 'pt_BR',
        'components_json' => templateBody(),
    ], $templateOverrides));

    $lead = Lead::factory()->forTenant($tenant)->create([
        'whatsapp_instance_id' => $instance->id,
    ]);

    return [$lead, $template, $user];
}

function templateAction(): SendOperatorTemplateAction
{
    return app(SendOperatorTemplateAction::class);
}

it('queues an approved template and records a manual_template timeline row with a snapshot', function () {
    [$lead, $template, $user] = templateScenario();

    $result = templateAction()->send($lead, $template->id, ['body' => ['1' => 'João', '2' => '#42']], $user, false);

    expect($result)->not->toBeNull()
        ->and($result['outbox_id'])->not->toBeNull();

    $row = ConversationTimelineMessage::where('lead_id', $lead->id)->first();
    expect($row->sender_type)->toBe('human')
        ->and($row->source)->toBe('manual_template')
        ->and($row->body)->toBe('Olá João, sua proposta #42 está pronta.')
        ->and($row->metadata['whatsapp_template']['id'])->toBe($template->id)
        ->and($row->metadata['whatsapp_template']['parameters'])->toBe(['body' => ['1' => 'João', '2' => '#42']]);

    $outbox = WhatsappOutboxMessage::where('lead_id', $lead->id)->first();
    expect($outbox->payload_json['type'])->toBe('template')
        ->and($outbox->payload_json['template_name'])->toBe('promo_proposta')
        ->and($outbox->payload_json['components'])->not->toBeEmpty();
});

it('rejects a template that does not belong to the lead instance', function () {
    [$lead, , $user] = templateScenario();

    $foreign = WhatsappTemplate::factory()->create([
        'tenant_id' => $lead->tenant_id,
        'whatsapp_instance_id' => null,
        'meta_waba_id' => 'OTHER-WABA',
        'status' => 'APPROVED',
        'components_json' => templateBody(),
    ]);

    templateAction()->send($lead, $foreign->id, ['body' => ['1' => 'a', '2' => 'b']], $user, false);
})->throws(ValidationException::class);

it('rejects an unapproved template', function () {
    [$lead, $template, $user] = templateScenario(['status' => 'PENDING']);

    templateAction()->send($lead, $template->id, ['body' => ['1' => 'a', '2' => 'b']], $user, false);
})->throws(ValidationException::class);

it('rejects a missing required parameter before any side effect', function () {
    [$lead, $template, $user] = templateScenario();

    try {
        templateAction()->send($lead, $template->id, ['body' => ['1' => 'só um']], $user, false);
        expect()->fail('Expected ValidationException');
    } catch (ValidationException) {
        // No timeline row and no outbox row should have been created.
        expect(ConversationTimelineMessage::where('lead_id', $lead->id)->count())->toBe(0)
            ->and(WhatsappOutboxMessage::where('lead_id', $lead->id)->count())->toBe(0);
    }
});

it('sends a template even when the 24h window is closed', function () {
    [$lead, $template, $user] = templateScenario();
    $lead->update(['service_window_expires_at' => now()->subHour()]);

    $result = templateAction()->send($lead, $template->id, ['body' => ['1' => 'João', '2' => '#42']], $user, false);

    expect($result)->not->toBeNull()
        ->and(ConversationTimelineMessage::where('lead_id', $lead->id)->where('source', 'manual_template')->count())->toBe(1);
});
