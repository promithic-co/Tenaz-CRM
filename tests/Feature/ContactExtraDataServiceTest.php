<?php

use App\Models\Contact;
use App\Services\ContactExtraDataService;

test('extra data mutations refresh stale contacts and preserve concurrent namespaces', function (): void {
    $contact = Contact::factory()->create([
        'extra_data' => ['campaign_code' => 'initial'],
    ]);
    $staleContact = Contact::withoutGlobalScopes()->findOrFail($contact->id);

    $contact->update([
        'extra_data' => [
            'campaign_code' => 'initial',
            'collected_information' => [
                'objetivo' => [
                    'label' => 'Objetivo',
                    'value' => 'Refinanciamento',
                    'source' => 'manual',
                ],
            ],
        ],
    ]);

    app(ContactExtraDataService::class)->merge($staleContact, [
        'whatsapp_app_sync_action' => 'add',
    ]);

    expect($contact->fresh()->extra_data)
        ->campaign_code->toBe('initial')
        ->whatsapp_app_sync_action->toBe('add')
        ->collected_information->toHaveKey('objetivo');
});
