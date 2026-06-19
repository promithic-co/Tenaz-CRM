<?php

namespace App\Services\WhatsApp;

use App\Contracts\WhatsApp\InstanceManagerInterface;
use App\Contracts\WhatsApp\WhatsAppProviderInterface;
use App\Enums\WhatsAppProvider;
use App\Exceptions\MetaApiException;
use App\Models\WhatsappInstance;
use App\Services\WhatsApp\Providers\MetaCloudInstanceManager;
use App\Services\WhatsApp\Providers\MetaCloudProvider;

class WhatsAppProviderFactory
{
    public function makeProvider(WhatsappInstance $instance, bool $allowExpiredToken = false): WhatsAppProviderInterface
    {
        if (! $allowExpiredToken && $instance->provider === WhatsAppProvider::MetaCloud && $instance->hasExpiredMetaToken()) {
            throw new MetaApiException('Meta access token expired. Reconnect the WhatsApp instance before sending messages.');
        }

        return match ($instance->provider) {
            WhatsAppProvider::MetaCloud => new MetaCloudProvider(
                phoneNumberId: (string) $instance->meta_phone_number_id,
                accessToken: (string) ($instance->meta_access_token ?? ''),
                appSecret: (string) config('services.meta.app_secret', ''),
                graphApiVersion: (string) config('services.meta.graph_api_version', 'v23.0'),
            ),
        };
    }

    public function makeInstanceManager(WhatsappInstance $instance): InstanceManagerInterface
    {
        return match ($instance->provider) {
            WhatsAppProvider::MetaCloud => new MetaCloudInstanceManager(
                phoneNumberId: (string) $instance->meta_phone_number_id,
                accessToken: $instance->meta_access_token,
                ownerPhoneNumber: $instance->phone_number,
                tokenExpired: $instance->hasExpiredMetaToken(),
                graphApiVersion: (string) config('services.meta.graph_api_version', 'v23.0'),
            ),
        };
    }

    /**
     * Resolve provider by instance name (DB lookup).
     * Returns null if instance not found (caller handles fallback).
     */
    public function makeProviderFromInstanceName(string $instanceName): ?WhatsAppProviderInterface
    {
        $instance = WhatsappInstance::where('name', $instanceName)->first();

        if (! $instance) {
            return null;
        }

        return $this->makeProvider($instance);
    }

    public function makeInstanceManagerFromInstanceName(string $instanceName): ?InstanceManagerInterface
    {
        $instance = WhatsappInstance::where('name', $instanceName)->first();

        if (! $instance) {
            return null;
        }

        return $this->makeInstanceManager($instance);
    }
}
