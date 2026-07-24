<?php

use App\Models\ConversationTimelineMessage;
use App\Models\Lead;
use App\Models\WhatsappTemplate;
use App\Services\WhatsApp\TemplateTimelineRecorder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function recorder(): TemplateTimelineRecorder
{
    return app(TemplateTimelineRecorder::class);
}

function recorderTemplate(array $attributes = []): WhatsappTemplate
{
    return WhatsappTemplate::factory()->create([
        'components_json' => [
            [
                'type' => 'BODY',
                'text' => 'Olá {{1}}, seu benefício {{2}} foi liberado.',
                'example' => ['body_text' => [['Maria', 'INSS']]],
            ],
        ],
        ...$attributes,
    ]);
}

it('records a sent template with its rendered snapshot', function () {
    $template = recorderTemplate();
    $lead = Lead::factory()->forTenant((string) $template->tenant_id)->create();

    recorder()->record(
        lead: $lead,
        template: $template,
        variables: ['João', 'INSS'],
        source: 'ura',
        providerMessageId: 'wamid-ura-001',
    );

    $row = ConversationTimelineMessage::where('lead_id', $lead->id)->first();

    expect($row)->not->toBeNull()
        ->and($row->direction)->toBe('outbound')
        ->and($row->sender_type)->toBe('system')
        ->and($row->source)->toBe('ura')
        ->and($row->status)->toBe('sent')
        ->and($row->provider_message_id)->toBe('wamid-ura-001')
        ->and($row->body)->toBe('Olá João, seu benefício INSS foi liberado.')
        ->and($row->metadata['whatsapp_template']['rendered']['body'])
        ->toBe('Olá João, seu benefício INSS foi liberado.')
        ->and($row->metadata['whatsapp_template']['id'])->toBe($template->id);
});

it('does not stack a second row when a job retry re-sends', function () {
    $template = recorderTemplate();
    $lead = Lead::factory()->forTenant((string) $template->tenant_id)->create();

    foreach (range(1, 2) as $ignored) {
        recorder()->record(
            lead: $lead,
            template: $template,
            variables: ['João', 'INSS'],
            source: 'ura',
            providerMessageId: 'wamid-ura-002',
        );
    }

    expect(ConversationTimelineMessage::where('lead_id', $lead->id)->count())->toBe(1);
});

it('falls back to the Meta example when a variable is missing', function () {
    $template = recorderTemplate();
    $lead = Lead::factory()->forTenant((string) $template->tenant_id)->create();

    // A strict render would throw here; the row still has to exist for the operator.
    recorder()->record(lead: $lead, template: $template, variables: ['João'], source: 'post_call');

    $row = ConversationTimelineMessage::where('lead_id', $lead->id)->first();

    expect($row)->not->toBeNull()
        ->and($row->body)->toBe('Olá João, seu benefício INSS foi liberado.');
});

it('records a template that carries no variables at all', function () {
    $template = recorderTemplate([
        'components_json' => [['type' => 'BODY', 'text' => 'Recebemos seu contato. Retornamos em breve.']],
    ]);
    $lead = Lead::factory()->forTenant((string) $template->tenant_id)->create();

    recorder()->record(lead: $lead, template: $template, source: 'ura_inbound');

    $row = ConversationTimelineMessage::where('lead_id', $lead->id)->first();

    expect($row)->not->toBeNull()
        ->and($row->source)->toBe('ura_inbound')
        ->and($row->body)->toBe('Recebemos seu contato. Retornamos em breve.');
});

it('keeps the buttons in the snapshot so the bubble renders them', function () {
    $template = recorderTemplate([
        'components_json' => [
            ['type' => 'BODY', 'text' => 'Podemos continuar por aqui?'],
            [
                'type' => 'BUTTONS',
                'buttons' => [
                    ['type' => 'QUICK_REPLY', 'text' => 'Sim, pode enviar'],
                    ['type' => 'QUICK_REPLY', 'text' => 'Agora não'],
                ],
            ],
        ],
    ]);
    $lead = Lead::factory()->forTenant((string) $template->tenant_id)->create();

    recorder()->record(lead: $lead, template: $template, source: 'ura');

    $buttons = ConversationTimelineMessage::where('lead_id', $lead->id)
        ->first()
        ->metadata['whatsapp_template']['rendered']['buttons'];

    expect($buttons)->toHaveCount(2)
        ->and($buttons[0]['text'])->toBe('Sim, pode enviar');
});

it('never throws into the sender when the template cannot be rendered', function () {
    $template = recorderTemplate([
        'components_json' => [['type' => 'CAROUSEL', 'cards' => []]],
    ]);
    $lead = Lead::factory()->forTenant((string) $template->tenant_id)->create();

    $message = recorder()->record(lead: $lead, template: $template, variables: ['João'], source: 'ura');

    // The send already reached the customer, so an unsupported component still yields a row.
    expect($message)->not->toBeNull()
        ->and($message->body)->toBe('João');
});
