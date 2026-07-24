<?php

use App\Jobs\SyncMetaTemplatesJob;
use App\Models\Contact;
use App\Models\ConversationTimelineMessage;
use App\Models\Lead;
use App\Models\User;
use App\Models\WhatsappInstance;
use App\Models\WhatsappTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

/**
 * A Meta Cloud conversation whose lead carries a contact the resolver can draw from.
 *
 * @return array{user: User, lead: Lead, instance: WhatsappInstance}
 */
function pickerScenario(string $contactName = 'Marcos Vinicius'): array
{
    $user = User::factory()->create();
    $tenantId = (string) $user->tenantId;

    $instance = WhatsappInstance::factory()->create([
        'user_id' => $user->id,
        'tenant_id' => $tenantId,
        'provider' => 'meta_cloud',
        'meta_waba_id' => 'WABA-PICKER',
    ]);

    $contact = Contact::factory()->create(['tenant_id' => $tenantId, 'name' => $contactName]);

    $lead = Lead::factory()->forTenant($tenantId)->create([
        'whatsapp_instance_id' => $instance->id,
        'contact_id' => $contact->id,
    ]);

    return ['user' => $user, 'lead' => $lead, 'instance' => $instance];
}

/**
 * @param  array<string, mixed>  $overrides
 */
function pickerTemplate(Lead $lead, WhatsappInstance $instance, array $overrides = []): WhatsappTemplate
{
    return WhatsappTemplate::factory()->create(array_merge([
        'tenant_id' => $lead->tenant_id,
        'whatsapp_instance_id' => $instance->id,
        'meta_waba_id' => $instance->meta_waba_id,
        'status' => 'APPROVED',
        'name' => 'oferta_margem',
        'meta_template_name' => 'oferta_margem',
        'language' => 'pt_BR',
        'category' => 'MARKETING',
        'components_json' => [[
            'type' => 'BODY',
            'text' => 'Olá {{1}}, você tem margem disponível.',
            'example' => ['body_text' => [['Maria']]],
        ]],
    ], $overrides));
}

/**
 * @return list<array<string, mixed>>
 */
function panelTemplates(User $user, Lead $lead): array
{
    $response = test()->actingAs($user)->get("/conversas/{$lead->id}");
    $response->assertOk();

    return $response->viewData('page')['props']['activeConversation']['whatsappTemplates'];
}

it('keeps the seeded hello_world template out of the picker', function () {
    ['user' => $user, 'lead' => $lead, 'instance' => $instance] = pickerScenario();

    pickerTemplate($lead, $instance);
    pickerTemplate($lead, $instance, ['name' => 'hello_world', 'meta_template_name' => 'hello_world']);

    expect(array_column(panelTemplates($user, $lead), 'name'))->toBe(['oferta_margem']);
});

it('still lists a locally created template that has no meta name', function () {
    ['user' => $user, 'lead' => $lead, 'instance' => $instance] = pickerScenario();

    pickerTemplate($lead, $instance, ['name' => 'proposta_local', 'meta_template_name' => null]);

    expect(array_column(panelTemplates($user, $lead), 'name'))->toBe(['proposta_local']);
});

it('previews the template with the lead data already substituted and marks the field resolved', function () {
    ['user' => $user, 'lead' => $lead, 'instance' => $instance] = pickerScenario('Marcos Vinicius');

    pickerTemplate($lead, $instance);
    $template = panelTemplates($user, $lead)[0];

    expect($template['preview'])->toBe('Olá Marcos Vinicius, você tem margem disponível.')
        ->and($template['category'])->toBe('MARKETING')
        ->and($template['fields'][0]['resolved'])->toBe('Marcos Vinicius');
});

it('leaves a field the CRM cannot answer unresolved for the operator', function () {
    ['user' => $user, 'lead' => $lead, 'instance' => $instance] = pickerScenario();

    pickerTemplate($lead, $instance, [
        'components_json' => [[
            'type' => 'BODY',
            'text' => 'Olá {{1}}, sua proposta {{2}} saiu.',
            'example' => ['body_text' => [['Maria', '#0042']]],
        ]],
    ]);

    $fields = panelTemplates($user, $lead)[0]['fields'];

    expect($fields[0]['resolved'])->toBe('Marcos Vinicius')
        ->and($fields[1]['resolved'])->toBeNull();
});

it('resolves the template parameters server-side when the operator sends none', function () {
    ['user' => $user, 'lead' => $lead, 'instance' => $instance] = pickerScenario('Marcos Vinicius');
    $template = pickerTemplate($lead, $instance);

    test()->actingAs($user)
        ->postJson("/conversas/{$lead->id}/send", ['template_id' => $template->id])
        ->assertOk()
        ->assertJsonPath('status', 'queued');

    $row = ConversationTimelineMessage::where('lead_id', $lead->id)->firstOrFail();

    expect($row->body)->toBe('Olá Marcos Vinicius, você tem margem disponível.')
        ->and($row->metadata['whatsapp_template']['parameters'])->toBe(['body' => ['1' => 'Marcos Vinicius']]);
});

it('lets the operator override a resolved parameter', function () {
    ['user' => $user, 'lead' => $lead, 'instance' => $instance] = pickerScenario('Marcos Vinicius');
    $template = pickerTemplate($lead, $instance);

    test()->actingAs($user)
        ->postJson("/conversas/{$lead->id}/send", [
            'template_id' => $template->id,
            'template_parameters' => ['body' => ['1' => 'Dr. Marcos']],
        ])
        ->assertOk();

    expect(ConversationTimelineMessage::where('lead_id', $lead->id)->value('body'))
        ->toBe('Olá Dr. Marcos, você tem margem disponível.');
});

it('queues a Meta sync for the instance behind the conversation', function () {
    Queue::fake();
    ['user' => $user, 'lead' => $lead, 'instance' => $instance] = pickerScenario();

    test()->actingAs($user)
        ->postJson("/conversas/{$lead->id}/templates/sync")
        ->assertOk()
        ->assertJsonPath('status', 'queued');

    Queue::assertPushed(
        SyncMetaTemplatesJob::class,
        fn (SyncMetaTemplatesJob $job): bool => $job->instanceId === $instance->id,
    );
});

it('refuses to sync a conversation that is not on a Meta Cloud instance', function () {
    Queue::fake();
    ['user' => $user, 'lead' => $lead, 'instance' => $instance] = pickerScenario();
    $instance->update(['meta_waba_id' => null]);

    test()->actingAs($user)
        ->postJson("/conversas/{$lead->id}/templates/sync")
        ->assertUnprocessable()
        ->assertJsonPath('status', 'error');

    Queue::assertNotPushed(SyncMetaTemplatesJob::class);
});

it('does not let another tenant sync this conversation', function () {
    Queue::fake();
    ['lead' => $lead] = pickerScenario();
    $outsider = User::factory()->create();

    test()->actingAs($outsider)
        ->postJson("/conversas/{$lead->id}/templates/sync")
        ->assertNotFound();

    Queue::assertNotPushed(SyncMetaTemplatesJob::class);
});
