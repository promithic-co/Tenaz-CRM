<?php

use App\Models\VoiceInstance;
use App\Models\WhatsappInstance;

function makeVoiceUser(): \App\Models\User
{
    return userWithTenant();
}

function makeTenantId(\App\Models\User $user): string
{
    return (string) $user->tenants()->first()->id;
}

function makeVoiceInstanceForUser(\App\Models\User $user): VoiceInstance
{
    return VoiceInstance::factory()->create([
        'tenant_id' => makeTenantId($user),
        'user_id' => $user->id,
    ]);
}

// ─── index ────────────────────────────────────────────────────────────────────

test('index renders voz/Index with instances and whatsapp instances', function () {
    $user = makeVoiceUser();
    makeVoiceInstanceForUser($user);

    $this->actingAs($user)
        ->get('/voz')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('voz/Index')
            ->has('instances', 1)
            ->has('whatsappInstances')
        );
});

test('index only returns instances belonging to the authenticated tenant', function () {
    $user = makeVoiceUser();
    $other = makeVoiceUser();

    makeVoiceInstanceForUser($user);
    makeVoiceInstanceForUser($other);

    $this->actingAs($user)
        ->get('/voz')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('voz/Index')
            ->has('instances', 1)
        );
});

test('index requires authentication', function () {
    $this->get('/voz')->assertRedirect('/login');
});

// ─── store ────────────────────────────────────────────────────────────────────

test('store creates a voice instance and redirects', function () {
    $user = makeVoiceUser();

    $this->actingAs($user)
        ->post('/voz', [
            'name' => 'minha-ura',
            'display_name' => 'Minha URA',
            'whatsapp_instance_id' => null,
            'greeting_template' => 'Olá {nome}, pressione 1 para continuar.',
            'post_call_message' => 'Obrigado pelo contato!',
            'active' => true,
        ])
        ->assertRedirect(route('voz.index'));

    $this->assertDatabaseHas('voice_instances', [
        'name' => 'minha-ura',
        'tenant_id' => makeTenantId($user),
        'user_id' => $user->id,
    ]);
});

test('store validates required name field', function () {
    $user = makeVoiceUser();

    $this->actingAs($user)
        ->post('/voz', ['name' => ''])
        ->assertSessionHasErrors('name');
});

test('store validates whatsapp_instance_id must exist', function () {
    $user = makeVoiceUser();

    $this->actingAs($user)
        ->post('/voz', [
            'name' => 'ura-test',
            'whatsapp_instance_id' => 9999,
        ])
        ->assertSessionHasErrors('whatsapp_instance_id');
});

test('store accepts valid whatsapp_instance_id', function () {
    $user = makeVoiceUser();
    $tenantId = makeTenantId($user);
    $wa = WhatsappInstance::factory()->create(['tenant_id' => $tenantId]);

    $this->actingAs($user)
        ->post('/voz', [
            'name' => 'ura-com-wa',
            'whatsapp_instance_id' => $wa->id,
            'active' => true,
        ])
        ->assertRedirect(route('voz.index'));

    $this->assertDatabaseHas('voice_instances', [
        'name' => 'ura-com-wa',
        'whatsapp_instance_id' => $wa->id,
    ]);
});

// ─── update ───────────────────────────────────────────────────────────────────

test('update modifies an existing voice instance', function () {
    $user = makeVoiceUser();
    $instance = makeVoiceInstanceForUser($user);

    $this->actingAs($user)
        ->put("/voz/{$instance->id}", [
            'name' => 'ura-atualizada',
            'display_name' => 'URA Atualizada',
            'active' => false,
        ])
        ->assertRedirect(route('voz.index'));

    $this->assertDatabaseHas('voice_instances', [
        'id' => $instance->id,
        'name' => 'ura-atualizada',
        'active' => false,
    ]);
});

test('update is forbidden for instances belonging to another tenant', function () {
    $user = makeVoiceUser();
    $other = makeVoiceUser();
    $instance = makeVoiceInstanceForUser($other);

    $this->actingAs($user)
        ->put("/voz/{$instance->id}", [
            'name' => 'hack-attempt',
            'active' => true,
        ])
        ->assertNotFound();
});

// ─── destroy ──────────────────────────────────────────────────────────────────

test('destroy deletes the voice instance and redirects', function () {
    $user = makeVoiceUser();
    $instance = makeVoiceInstanceForUser($user);

    $this->actingAs($user)
        ->delete("/voz/{$instance->id}")
        ->assertRedirect(route('voz.index'));

    $this->assertDatabaseMissing('voice_instances', ['id' => $instance->id]);
});

test('destroy is forbidden for instances belonging to another tenant', function () {
    $user = makeVoiceUser();
    $other = makeVoiceUser();
    $instance = makeVoiceInstanceForUser($other);

    $this->actingAs($user)
        ->delete("/voz/{$instance->id}")
        ->assertNotFound();
});
