<?php

use App\Models\Lead;
use App\Models\WhatsappInstance;
use App\Services\WhatsApp\WhatsAppConversationWindowResolver;

test('it provides an actionable notice for coexistence instances', function (): void {
    $instance = WhatsappInstance::factory()->create([
        'tenant_id' => 'default',
        'meta_coexistence' => true,
    ]);
    $lead = Lead::factory()->create([
        'tenant_id' => $instance->tenant_id,
        'whatsapp_instance_id' => $instance->id,
    ]);

    $window = app(WhatsAppConversationWindowResolver::class)->resolve($lead);

    expect($window['coexistence'])
        ->enabled->toBeTrue()
        ->note->toBe('Algumas mensagens podem aparecer apenas no WhatsApp. Confira o aplicativo para acompanhar toda a conversa.');
});

test('it omits the notice for instances without coexistence', function (): void {
    $instance = WhatsappInstance::factory()->create([
        'tenant_id' => 'default',
        'meta_coexistence' => false,
    ]);
    $lead = Lead::factory()->create([
        'tenant_id' => $instance->tenant_id,
        'whatsapp_instance_id' => $instance->id,
    ]);

    $window = app(WhatsAppConversationWindowResolver::class)->resolve($lead);

    expect($window['coexistence'])
        ->enabled->toBeFalse()
        ->note->toBeNull();
});
