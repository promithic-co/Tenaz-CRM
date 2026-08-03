<?php

use App\Models\User;
use App\Models\WhatsappInstance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

/**
 * @return array{0: User, 1: WhatsappInstance}
 */
function connectionFixture(array $attributes = []): array
{
    $user = User::factory()->create();

    $instance = WhatsappInstance::factory()->metaCloud()->create([
        'user_id' => $user->id,
        'tenant_id' => $user->tenantId,
        'display_name' => 'Nome antigo',
        ...$attributes,
    ]);

    return [$user, $instance];
}

it('activates the number with the two-step PIN', function (): void {
    // The whole point of this endpoint: registering a Meta Cloud number used to
    // live only inside the signup flow, so a number that arrived unregistered
    // could only be fixed by hand with curl.
    Http::fake([
        'graph.facebook.com/*/register' => Http::response(['success' => true]),
        'graph.facebook.com/*' => Http::response(['id' => '1']),
    ]);

    [$user, $instance] = connectionFixture();

    test()->actingAs($user)
        ->postJson("/whatsapp/{$instance->id}/connection", [
            'display_name' => 'Amec Consignado',
            'pin' => '123456',
        ])
        ->assertOk()
        ->assertJsonPath('display_name', 'Amec Consignado')
        ->assertJsonPath('has_registration_pin', true);

    Http::assertSent(fn ($request) => str_contains($request->url(), '/register')
        && $request['messaging_product'] === 'whatsapp'
        && $request['pin'] === '123456');

    expect($instance->refresh()->meta_registration_pin)->toBe('123456');
});

it('stores the PIN encrypted and never serializes it', function (): void {
    Http::fake([
        'graph.facebook.com/*/register' => Http::response(['success' => true]),
        'graph.facebook.com/*' => Http::response(['id' => '1']),
    ]);

    [$user, $instance] = connectionFixture();

    $response = test()->actingAs($user)
        ->postJson("/whatsapp/{$instance->id}/connection", [
            'display_name' => 'Amec Consignado',
            'pin' => '654321',
        ])
        ->assertOk();

    // The response confirms a PIN is on file without ever echoing it back.
    expect($response->getContent())->not->toContain('654321');

    // Straight off the connection, bypassing the model: going through Eloquent
    // would decrypt the value and prove nothing about what is on disk.
    $stored = (string) DB::table('whatsapp_instances')
        ->where('id', $instance->getKey())
        ->value('meta_registration_pin');

    expect($stored)->not->toBe('654321')
        ->and($stored)->not->toBeEmpty()
        ->and($instance->refresh()->toArray())->not->toHaveKey('meta_registration_pin');
});

it('keeps the PIN out of every log line', function (): void {
    // Meta's failure path logs the phone number id and the error code. A PIN
    // that leaked into that context would sit in plain text in the container
    // logs of a multi-tenant production host.
    Http::fake([
        'graph.facebook.com/*/register' => Http::response([
            'error' => ['code' => 133005, 'message' => 'PIN mismatch'],
        ], 400),
    ]);

    $logged = [];
    Event::listen(MessageLogged::class, function (MessageLogged $event) use (&$logged): void {
        $logged[] = $event->message.' '.json_encode($event->context);
    });

    [$user, $instance] = connectionFixture();

    test()->actingAs($user)
        ->postJson("/whatsapp/{$instance->id}/connection", [
            'display_name' => 'Amec Consignado',
            'pin' => '999888',
        ])
        ->assertStatus(422);

    expect(implode("\n", $logged))->not->toContain('999888');
});

it('explains a wrong PIN in plain Portuguese', function (): void {
    Http::fake([
        'graph.facebook.com/*/register' => Http::response([
            'error' => ['code' => 133005, 'message' => 'Two-step verification PIN mismatch'],
        ], 400),
    ]);

    [$user, $instance] = connectionFixture();

    test()->actingAs($user)
        ->postJson("/whatsapp/{$instance->id}/connection", [
            'display_name' => 'Amec Consignado',
            'pin' => '123456',
        ])
        ->assertStatus(422)
        ->assertJsonPath('errors.pin.0', 'PIN incorreto. Use os 6 dígitos da verificação em duas etapas deste número no WhatsApp.');

    // A refused registration must not leave a wrong PIN on file.
    expect($instance->refresh()->meta_registration_pin)->toBeNull();
});

it('tells the user to wait when Meta locked the number out', function (): void {
    Http::fake([
        'graph.facebook.com/*/register' => Http::response([
            'error' => ['code' => 133008, 'message' => 'Too many attempts'],
        ], 400),
    ]);

    [$user, $instance] = connectionFixture();

    test()->actingAs($user)
        ->postJson("/whatsapp/{$instance->id}/connection", [
            'display_name' => 'Amec Consignado',
            'pin' => '123456',
        ])
        ->assertStatus(422)
        ->assertJsonPath(
            'errors.pin.0',
            'A Meta bloqueou novas tentativas porque o PIN errado foi enviado várias vezes. Espere algumas horas antes de tentar outra vez.'
        );
});

it('renames the connection without touching Meta', function (): void {
    // Renaming is not a privileged action and must not demand a secret.
    Http::fake();

    [$user, $instance] = connectionFixture();

    test()->actingAs($user)
        ->postJson("/whatsapp/{$instance->id}/connection", [
            'display_name' => 'Amec Consignado',
        ])
        ->assertOk()
        ->assertJsonPath('display_name', 'Amec Consignado')
        ->assertJsonPath('has_registration_pin', false);

    Http::assertNothingSent();
});

it('rejects a PIN that is not six digits', function (): void {
    Http::fake();

    [$user, $instance] = connectionFixture();

    test()->actingAs($user)
        ->postJson("/whatsapp/{$instance->id}/connection", [
            'display_name' => 'Amec Consignado',
            'pin' => '12ab56',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['pin']);

    Http::assertNothingSent();
});

it('refuses to touch another tenant\'s connection', function (): void {
    Http::fake();

    [, $instance] = connectionFixture();
    $outsider = User::factory()->create();

    // 404, not 403: the tenant scope hides the row from route model binding, so
    // an outsider cannot even confirm the instance exists.
    test()->actingAs($outsider)
        ->postJson("/whatsapp/{$instance->id}/connection", [
            'display_name' => 'Sequestrado',
        ])
        ->assertNotFound();

    expect($instance->refresh()->display_name)->toBe('Nome antigo');
});
