<?php

use App\Enums\TemplateKind;
use App\Enums\WhatsAppProvider;
use App\Models\Campaign;
use App\Models\WhatsappInstance;
use App\Models\WhatsappTemplate;
use App\Services\CampaignTemplateCompatibility;
use Illuminate\Support\Facades\DB;

$compatibleModels = static function (): array {
    $tenantId = 'tenant-01';

    $instance = new WhatsappInstance([
        'tenant_id' => $tenantId,
        'provider' => WhatsAppProvider::MetaCloud,
        'meta_waba_id' => 'waba-01',
    ]);
    $instance->id = 10;

    $template = new WhatsappTemplate([
        'tenant_id' => $tenantId,
        'whatsapp_instance_id' => $instance->id,
        'kind' => TemplateKind::MetaHsm,
        'status' => 'APPROVED',
        'meta_template_name' => 'campaign_notice',
        'language' => 'pt_BR',
        'meta_waba_id' => 'waba-01',
    ]);
    $template->id = 20;

    $campaign = new Campaign([
        'tenant_id' => $tenantId,
        'whatsapp_instance_id' => $instance->id,
        'whatsapp_template_id' => $template->id,
    ]);
    $campaign->id = 30;

    return [$campaign, $instance, $template];
};

it('accepts a complete exact-instance Meta template configuration', function () use ($compatibleModels) {
    [$campaign, $instance, $template] = $compatibleModels();

    expect((new CampaignTemplateCompatibility)->violations($campaign, $instance, $template))
        ->toBe([]);
});

it('returns one stable violation for each incompatible configuration', function (
    Closure $mutate,
    string $expectedViolation,
) use ($compatibleModels) {
    [$campaign, $instance, $template] = $compatibleModels();
    $mutate($campaign, $instance, $template);

    expect((new CampaignTemplateCompatibility)->violations($campaign, $instance, $template))
        ->toContain($expectedViolation);
})->with([
    'campaign tenant differs' => [
        static fn (Campaign $campaign) => $campaign->tenant_id = 'tenant-02',
        'CAMPAIGN_TEMPLATE_TENANT_MISMATCH',
    ],
    'instance tenant is missing' => [
        static fn (Campaign $campaign, WhatsappInstance $instance) => $instance->tenant_id = null,
        'CAMPAIGN_TEMPLATE_TENANT_MISMATCH',
    ],
    'campaign points to another instance' => [
        static fn (Campaign $campaign) => $campaign->whatsapp_instance_id = 11,
        'CAMPAIGN_INSTANCE_MISMATCH',
    ],
    'template belongs to another instance' => [
        static fn (Campaign $campaign, WhatsappInstance $instance, WhatsappTemplate $template) => $template->whatsapp_instance_id = 11,
        'TEMPLATE_INSTANCE_MISMATCH',
    ],
    'campaign points to another template' => [
        static fn (Campaign $campaign) => $campaign->whatsapp_template_id = 21,
        'CAMPAIGN_TEMPLATE_MISMATCH',
    ],
    'template kind is not Meta HSM' => [
        static fn (Campaign $campaign, WhatsappInstance $instance, WhatsappTemplate $template) => $template->setRawAttributes(array_merge($template->getAttributes(), ['kind' => 'legacy'])),
        'TEMPLATE_KIND_INVALID',
    ],
    'template is not approved' => [
        static fn (Campaign $campaign, WhatsappInstance $instance, WhatsappTemplate $template) => $template->status = 'PAUSED',
        'TEMPLATE_NOT_APPROVED',
    ],
    'template Meta name is missing' => [
        static fn (Campaign $campaign, WhatsappInstance $instance, WhatsappTemplate $template) => $template->meta_template_name = '  ',
        'TEMPLATE_NAME_MISSING',
    ],
    'template language is missing' => [
        static fn (Campaign $campaign, WhatsappInstance $instance, WhatsappTemplate $template) => $template->language = null,
        'TEMPLATE_LANGUAGE_MISSING',
    ],
    'instance WABA is missing' => [
        static fn (Campaign $campaign, WhatsappInstance $instance) => $instance->meta_waba_id = '',
        'INSTANCE_WABA_MISSING',
    ],
    'template WABA is missing' => [
        static fn (Campaign $campaign, WhatsappInstance $instance, WhatsappTemplate $template) => $template->meta_waba_id = null,
        'TEMPLATE_WABA_MISSING',
    ],
    'Meta WABAs differ' => [
        static fn (Campaign $campaign, WhatsappInstance $instance, WhatsappTemplate $template) => $template->meta_waba_id = 'waba-02',
        'TEMPLATE_WABA_MISMATCH',
    ],
]);

it('returns violations in deterministic precedence without duplicate codes', function () use ($compatibleModels) {
    [$campaign, $instance, $template] = $compatibleModels();

    $campaign->tenant_id = 'tenant-02';
    $campaign->whatsapp_instance_id = 11;
    $campaign->whatsapp_template_id = 21;
    $template->whatsapp_instance_id = 12;
    $template->status = 'REJECTED';
    $template->meta_template_name = '';
    $template->language = '';
    $instance->meta_waba_id = null;
    $template->meta_waba_id = null;

    expect((new CampaignTemplateCompatibility)->violations($campaign, $instance, $template))
        ->toBe([
            'CAMPAIGN_TEMPLATE_TENANT_MISMATCH',
            'CAMPAIGN_INSTANCE_MISMATCH',
            'TEMPLATE_INSTANCE_MISMATCH',
            'CAMPAIGN_TEMPLATE_MISMATCH',
            'TEMPLATE_NOT_APPROVED',
            'TEMPLATE_NAME_MISSING',
            'TEMPLATE_LANGUAGE_MISSING',
            'INSTANCE_WABA_MISSING',
            'TEMPLATE_WABA_MISSING',
        ]);
});

it('performs only in-memory constant-time comparisons', function () use ($compatibleModels) {
    [$campaign, $instance, $template] = $compatibleModels();

    DB::flushQueryLog();
    DB::enableQueryLog();

    $violations = (new CampaignTemplateCompatibility)->violations($campaign, $instance, $template);
    $queries = DB::getQueryLog();

    DB::disableQueryLog();

    expect($violations)->toBe([])
        ->and($queries)->toBe([])
        ->and($campaign->getRelations())->toBe([])
        ->and($instance->getRelations())->toBe([])
        ->and($template->getRelations())->toBe([]);
});
