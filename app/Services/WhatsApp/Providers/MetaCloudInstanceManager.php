<?php

namespace App\Services\WhatsApp\Providers;

use App\Contracts\WhatsApp\InstanceManagerInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Meta Cloud API — managed entirely in the Meta Business Suite / Embedded Signup.
 * No QR code, no session lifecycle. Status is derived from token presence.
 */
class MetaCloudInstanceManager implements InstanceManagerInterface
{
    public function __construct(
        private readonly string $phoneNumberId,
        private readonly ?string $accessToken,
        private readonly ?string $ownerPhoneNumber = null,
        private readonly bool $tokenExpired = false,
        private readonly string $graphApiVersion = 'v23.0',
    ) {}

    /** @return array{state: string} */
    public function status(): array
    {
        if ($this->tokenExpired) {
            return ['state' => 'close', 'reason' => 'token_expired'];
        }

        return ['state' => filled($this->accessToken) ? 'open' : 'close'];
    }

    /** @return array{base64?: string, pairingCode?: string, count?: int, error?: string} */
    public function connect(): array
    {
        return ['error' => 'Meta Cloud API é gerenciado via Embedded Signup. Use o wizard de criação de instância para conectar uma conta Meta.'];
    }

    public function create(): bool
    {
        return true;
    }

    /**
     * Fetch the WhatsApp number tied to this Cloud instance.
     *
     * Falls back to the Graph API when the cached `ownerPhoneNumber` is empty
     * (e.g. just after Embedded Signup, before the controller persists it).
     *
     * @return array{phone_number?: string}|null
     */
    public function fetchInstanceInfo(): ?array
    {
        if (filled($this->ownerPhoneNumber)) {
            return ['phone_number' => $this->ownerPhoneNumber];
        }

        if (! $this->phoneNumberId || ! $this->accessToken) {
            return null;
        }

        try {
            $response = Http::withToken($this->accessToken)
                ->timeout(10)
                ->get("https://graph.facebook.com/{$this->graphApiVersion}/{$this->phoneNumberId}", [
                    'fields' => 'display_phone_number,verified_name',
                ]);

            if (! $response->successful()) {
                return null;
            }

            $phone = $response->json('display_phone_number');

            return $phone ? ['phone_number' => (string) $phone] : null;
        } catch (\Throwable $e) {
            Log::warning('MetaCloudInstanceManager: failed to resolve display_phone_number', [
                'phone_number_id' => $this->phoneNumberId,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    public function disconnect(): bool
    {
        return true;
    }
}
