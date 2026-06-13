<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

it('drops gupshup_app_name column', function () {
    expect(Schema::hasColumn('whatsapp_instances', 'gupshup_app_name'))->toBeFalse();
});

it('drops gupshup_element_name column', function () {
    expect(Schema::hasColumn('whatsapp_templates', 'gupshup_element_name'))->toBeFalse();
});

it('test_safety_precheck', function () {
    // Create a user to satisfy FK constraint
    $user = \App\Models\User::factory()->create();

    // Insert a whatsapp_instance with provider='gupshup' using a raw INSERT
    // to bypass the Eloquent enum cast (enum case no longer exists after Task 1.2).
    DB::table('whatsapp_instances')->insert([
        'user_id' => $user->id,
        'tenant_id' => $user->tenantId,
        'name' => 'test-gupshup-precheck',
        'provider' => 'gupshup',
        'api_url' => 'https://api.gupshup.io',
        'api_key' => 'test-key',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $migration = require __DIR__.'/../../../database/migrations/2026_04_23_000000_phase43_pre_check_no_gupshup_waha_rows.php';

    expect(fn () => $migration->up())->toThrow(
        \RuntimeException::class,
        '1 whatsapp_instance(s) still use gupshup/waha'
    );
});

it('removes gupshup and waha enum cases', function () {
    $cases = \App\Enums\WhatsAppProvider::cases();
    $values = array_map(fn ($c) => $c->value, $cases);

    expect($values)->not->toContain('gupshup')
        ->and($values)->not->toContain('waha')
        ->and(count($cases))->toBe(1);
});

it('test_adds_kind_column', function () {
    expect(Schema::hasColumn('whatsapp_templates', 'kind'))->toBeTrue();
});

it('test_renames_gupshup_message_id', function () {
    expect(Schema::hasColumn('campaign_messages', 'provider_message_id'))->toBeTrue()
        ->and(Schema::hasColumn('campaign_messages', 'gupshup_message_id'))->toBeFalse();
});

it('test_renames_preserve_data', function () {
    // Verify that the renamed column provider_message_id can store and retrieve data.
    $message = \App\Models\CampaignMessage::factory()->create([
        'provider_message_id' => 'test-preserved-id-abc',
        'status' => 'sent',
    ]);

    $row = DB::table('campaign_messages')->where('id', $message->id)->first();

    expect($row->provider_message_id)->toBe('test-preserved-id-abc');
});
