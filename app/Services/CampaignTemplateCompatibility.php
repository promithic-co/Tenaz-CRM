<?php

namespace App\Services;

use App\Enums\TemplateKind;
use App\Enums\WhatsAppProvider;
use App\Models\Campaign;
use App\Models\WhatsappInstance;
use App\Models\WhatsappTemplate;

final class CampaignTemplateCompatibility
{
    public const string TENANT_MISMATCH = 'CAMPAIGN_TEMPLATE_TENANT_MISMATCH';

    public const string CAMPAIGN_INSTANCE_MISMATCH = 'CAMPAIGN_INSTANCE_MISMATCH';

    public const string TEMPLATE_INSTANCE_MISMATCH = 'TEMPLATE_INSTANCE_MISMATCH';

    public const string CAMPAIGN_TEMPLATE_MISMATCH = 'CAMPAIGN_TEMPLATE_MISMATCH';

    public const string TEMPLATE_KIND_INVALID = 'TEMPLATE_KIND_INVALID';

    public const string TEMPLATE_NOT_APPROVED = 'TEMPLATE_NOT_APPROVED';

    public const string TEMPLATE_NAME_MISSING = 'TEMPLATE_NAME_MISSING';

    public const string TEMPLATE_LANGUAGE_MISSING = 'TEMPLATE_LANGUAGE_MISSING';

    public const string INSTANCE_WABA_MISSING = 'INSTANCE_WABA_MISSING';

    public const string TEMPLATE_WABA_MISSING = 'TEMPLATE_WABA_MISSING';

    public const string TEMPLATE_WABA_MISMATCH = 'TEMPLATE_WABA_MISMATCH';

    /**
     * @return list<string>
     */
    public function violations(
        Campaign $campaign,
        WhatsappInstance $instance,
        WhatsappTemplate $template,
    ): array {
        return $this->violationsForConfig(CampaignSendConfig::fromModels(
            $campaign,
            $instance,
            $template,
        ));
    }

    /** @return list<string> */
    public function violationsForConfig(CampaignSendConfig $config): array
    {
        $violations = [];

        if (
            ! $this->sameIdentifier($config->campaignTenantId, $config->instanceTenantId)
            || ! $this->sameIdentifier($config->campaignTenantId, $config->templateTenantId)
        ) {
            $violations[] = self::TENANT_MISMATCH;
        }

        if (! $this->sameIdentifier($config->campaignInstanceId, $config->instanceId)) {
            $violations[] = self::CAMPAIGN_INSTANCE_MISMATCH;
        }

        if (! $this->sameIdentifier($config->templateInstanceId, $config->instanceId)) {
            $violations[] = self::TEMPLATE_INSTANCE_MISMATCH;
        }

        if (! $this->sameIdentifier($config->campaignTemplateId, $config->templateId)) {
            $violations[] = self::CAMPAIGN_TEMPLATE_MISMATCH;
        }

        if ($config->templateKind !== TemplateKind::MetaHsm->value) {
            $violations[] = self::TEMPLATE_KIND_INVALID;
        }

        if ($config->templateStatus !== 'APPROVED') {
            $violations[] = self::TEMPLATE_NOT_APPROVED;
        }

        if (! $this->hasValue($config->templateName)) {
            $violations[] = self::TEMPLATE_NAME_MISSING;
        }

        if (! $this->hasValue($config->templateLanguage)) {
            $violations[] = self::TEMPLATE_LANGUAGE_MISSING;
        }

        if ($config->provider === WhatsAppProvider::MetaCloud) {
            $instanceHasWaba = $this->hasValue($config->instanceWabaId);
            $templateHasWaba = $this->hasValue($config->templateWabaId);

            if (! $instanceHasWaba) {
                $violations[] = self::INSTANCE_WABA_MISSING;
            }

            if (! $templateHasWaba) {
                $violations[] = self::TEMPLATE_WABA_MISSING;
            }

            if (
                $instanceHasWaba
                && $templateHasWaba
                && ! $this->sameIdentifier($config->instanceWabaId, $config->templateWabaId)
            ) {
                $violations[] = self::TEMPLATE_WABA_MISMATCH;
            }
        }

        return $violations;
    }

    private function sameIdentifier(mixed $left, mixed $right): bool
    {
        return $this->hasValue($left)
            && $this->hasValue($right)
            && (string) $left === (string) $right;
    }

    private function hasValue(mixed $value): bool
    {
        return is_int($value)
            || (is_string($value) && trim($value) !== '');
    }
}
