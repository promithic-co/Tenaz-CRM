<?php

namespace App\Services;

use App\Enums\WhatsAppProvider;
use App\Models\Campaign;
use App\Models\WhatsappInstance;
use App\Models\WhatsappTemplate;
use BackedEnum;
use Illuminate\Support\Carbon;

final readonly class CampaignSendConfig
{
    /** @param list<array<string, mixed>>|null $templateComponents */
    public function __construct(
        public int $campaignId,
        public string $campaignTenantId,
        public int $campaignInstanceId,
        public int $campaignTemplateId,
        public int $instanceId,
        public string $instanceTenantId,
        public WhatsAppProvider $provider,
        public ?string $phoneNumberId,
        public ?string $instanceWabaId,
        public ?string $accessToken,
        public bool $tokenPermanent,
        public ?int $tokenExpiresAt,
        public int $templateId,
        public string $templateTenantId,
        public ?int $templateInstanceId,
        public string $templateKind,
        public string $templateStatus,
        public ?string $templateName,
        public ?string $templateLanguage,
        public ?string $templateWabaId,
        public ?array $templateComponents,
    ) {}

    public static function fromModels(
        Campaign $campaign,
        WhatsappInstance $instance,
        WhatsappTemplate $template,
    ): self {
        $provider = $instance->provider;

        if (! $provider instanceof WhatsAppProvider) {
            $provider = WhatsAppProvider::from((string) $provider);
        }

        $kind = $template->getAttributes()['kind'] ?? null;
        if ($kind instanceof BackedEnum) {
            $kind = $kind->value;
        }

        return new self(
            campaignId: (int) $campaign->getKey(),
            campaignTenantId: (string) $campaign->tenant_id,
            campaignInstanceId: (int) $campaign->whatsapp_instance_id,
            campaignTemplateId: (int) $campaign->whatsapp_template_id,
            instanceId: (int) $instance->getKey(),
            instanceTenantId: (string) $instance->tenant_id,
            provider: $provider,
            phoneNumberId: self::nullableString($instance->meta_phone_number_id),
            instanceWabaId: self::nullableString($instance->meta_waba_id),
            accessToken: self::nullableString($instance->meta_access_token),
            tokenPermanent: (bool) $instance->meta_token_permanent,
            tokenExpiresAt: $instance->meta_token_expires_at?->getTimestamp(),
            templateId: (int) $template->getKey(),
            templateTenantId: (string) $template->tenant_id,
            templateInstanceId: $template->whatsapp_instance_id === null
                ? null
                : (int) $template->whatsapp_instance_id,
            templateKind: (string) $kind,
            templateStatus: (string) $template->status,
            templateName: self::nullableString($template->meta_template_name),
            templateLanguage: self::nullableString($template->language),
            templateWabaId: self::nullableString($template->meta_waba_id),
            templateComponents: is_array($template->components_json)
                ? $template->components_json
                : null,
        );
    }

    public function hasExpiredToken(): bool
    {
        return ! $this->tokenPermanent
            && $this->tokenExpiresAt !== null
            && $this->tokenExpiresAt < now()->timestamp;
    }

    public function providerInstance(): WhatsappInstance
    {
        $instance = new WhatsappInstance([
            'tenant_id' => $this->instanceTenantId,
            'provider' => $this->provider,
            'meta_phone_number_id' => $this->phoneNumberId,
            'meta_waba_id' => $this->instanceWabaId,
            'meta_access_token' => $this->accessToken,
            'meta_token_permanent' => $this->tokenPermanent,
            'meta_token_expires_at' => $this->tokenExpiresAt === null
                ? null
                : Carbon::createFromTimestamp($this->tokenExpiresAt),
        ]);
        $instance->setAttribute($instance->getKeyName(), $this->instanceId);

        return $instance;
    }

    private static function nullableString(mixed $value): ?string
    {
        return $value === null ? null : (string) $value;
    }
}
