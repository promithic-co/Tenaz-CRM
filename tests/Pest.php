<?php

use App\Enums\TemplateKind;
use App\Models\Campaign;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WhatsappInstance;
use App\Models\WhatsappTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

pest()->extend(TestCase::class)
    ->in('Unit');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}

/**
 * Create a User with a real Tenant attached and return the User.
 *
 * Feature tests that hit HTTP endpoints which write to tables with NOT NULL
 * tenant_id (e.g. agent_configs) need a user whose `tenantId` accessor
 * returns a non-null value. `User::factory()->create()` alone does NOT
 * create a tenant, so use this helper for store/HTTP tests.
 */
function userWithTenant(): User
{
    $user = User::factory()->create();
    $tenant = Tenant::create(['name' => $user->name]);
    $user->tenants()->attach($tenant->id, ['role' => 'owner']);

    return $user;
}

/**
 * Align a campaign's test template with its Meta instance.
 *
 * Production rejects templates from another instance or WABA. Legacy tests that
 * exercise successful dispatches must therefore opt into a complete, compatible
 * provider configuration without requiring live Meta credentials.
 */
function makeCampaignMetaConfigurationCompatible(
    Campaign $campaign,
): WhatsappTemplate {
    $instance = WhatsappInstance::query()
        ->withoutGlobalScopes()
        ->findOrFail($campaign->whatsapp_instance_id);
    $template = WhatsappTemplate::query()
        ->withoutGlobalScopes()
        ->findOrFail($campaign->whatsapp_template_id);

    $instance->forceFill([
        'tenant_id' => $campaign->tenant_id,
    ])->save();

    $template->forceFill([
        'tenant_id' => $campaign->tenant_id,
        'whatsapp_instance_id' => $instance->getKey(),
        'kind' => TemplateKind::MetaHsm->value,
        'status' => 'APPROVED',
        'meta_template_id' => $template->meta_template_id ?: fake()->uuid(),
        'meta_template_name' => $template->meta_template_name ?: 'campaign-test-'.$campaign->getKey(),
        'meta_waba_id' => $instance->meta_waba_id,
        'language' => $template->language ?: 'pt_BR',
    ])->save();

    return $template->fresh();
}
