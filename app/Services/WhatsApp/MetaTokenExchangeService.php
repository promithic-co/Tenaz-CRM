<?php

namespace App\Services\WhatsApp;

use App\Exceptions\MetaApiException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class MetaTokenExchangeService
{
    public function __construct(
        private readonly ?string $appId = null,
        private readonly ?string $appSecret = null,
        private readonly string $graphApiVersion = 'v23.0',
    ) {}

    /**
     * Full 4-step exchange. Falls back to 60-day User Token if System User creation fails.
     *
     * @return array{access_token: string, system_user_id: ?string, permanent: bool, waba_id: string, phone_number_id: string}
     *
     * @throws MetaApiException if the initial code exchange fails
     */
    public function exchangeCodeForPermanentToken(string $code, string $wabaId, string $phoneNumberId = ''): array
    {
        $appId = $this->appId ?? (string) config('services.meta.app_id');
        $appSecret = $this->appSecret ?? (string) config('services.meta.app_secret');

        if (! $appId || ! $appSecret) {
            throw new MetaApiException('Meta app credentials not configured (check META_APP_ID / META_APP_SECRET).');
        }

        // Step 1: exchange code for 60-day User Token.
        // redirect_uri MUST be empty string for Embedded Signup flows.
        $step1 = Http::timeout(15)->get("https://graph.facebook.com/{$this->graphApiVersion}/oauth/access_token", [
            'client_id' => $appId,
            'client_secret' => $appSecret,
            'code' => $code,
            'redirect_uri' => '',
        ]);

        if (! $step1->successful()) {
            Log::error('meta.token_exchange.code_exchange_failed', [
                'status' => $step1->status(),
                'error_type' => $step1->json('error.type'),
            ]);
            throw new MetaApiException('Embedded Signup code exchange failed.');
        }

        $userToken = (string) $step1->json('access_token', '');
        if (! $userToken) {
            throw new MetaApiException('Meta did not return an access_token.');
        }

        // Resolve phone_number_id from WABA if not provided by the FINISH event (L-04)
        if (! $phoneNumberId) {
            $phoneNumberId = $this->resolvePhoneNumberId($wabaId, $userToken);
        }

        // Step 2: find tenant's Business Portfolio ID
        $businessId = $this->resolveBusinessId($userToken);

        if (! $businessId) {
            Log::warning('meta.token_exchange.fallback_user_token', [
                'reason' => 'no_business_id',
                'waba_id' => $wabaId,
            ]);

            return [
                'access_token' => $userToken,
                'system_user_id' => null,
                'permanent' => false,
                'waba_id' => $wabaId,
                'phone_number_id' => $phoneNumberId,
            ];
        }

        // Step 3: create System User
        $step3 = Http::withToken($userToken)->timeout(15)->post(
            "https://graph.facebook.com/{$this->graphApiVersion}/{$businessId}/system_users",
            ['name' => 'Tenaz CRM Integration', 'role' => 'ADMIN']
        );

        if (! $step3->successful()) {
            Log::warning('meta.token_exchange.fallback_user_token', [
                'reason' => 'system_user_creation_failed',
                'status' => $step3->status(),
                'waba_id' => $wabaId,
            ]);

            return [
                'access_token' => $userToken,
                'system_user_id' => null,
                'permanent' => false,
                'waba_id' => $wabaId,
                'phone_number_id' => $phoneNumberId,
            ];
        }

        $systemUserId = (string) $step3->json('id', '');

        // Step 4: generate permanent token for System User
        $step4 = Http::withToken($userToken)->timeout(15)->post(
            "https://graph.facebook.com/{$this->graphApiVersion}/{$systemUserId}/access_tokens",
            [
                'app_id' => $appId,
                'scopes' => 'whatsapp_business_messaging,whatsapp_business_management',
            ]
        );

        if (! $step4->successful()) {
            Log::warning('meta.token_exchange.fallback_user_token', [
                'reason' => 'permanent_token_generation_failed',
                'system_user_id' => $systemUserId,
            ]);

            return [
                'access_token' => $userToken,
                'system_user_id' => $systemUserId,
                'permanent' => false,
                'waba_id' => $wabaId,
                'phone_number_id' => $phoneNumberId,
            ];
        }

        return [
            'access_token' => (string) $step4->json('access_token'),
            'system_user_id' => $systemUserId,
            'permanent' => true,
            'waba_id' => $wabaId,
            'phone_number_id' => $phoneNumberId,
        ];
    }

    /**
     * Subscribe the WABA to this app so Meta starts delivering webhook events.
     * Must be called once after a new Meta instance is created.
     */
    public function subscribeWaba(string $wabaId, string $accessToken): bool
    {
        try {
            $response = Http::withToken($accessToken)
                ->timeout(15)
                ->post("https://graph.facebook.com/{$this->graphApiVersion}/{$wabaId}/subscribed_apps");
        } catch (Throwable $e) {
            Log::warning('meta.token_exchange.subscribe_waba_exception', [
                'waba_id' => $wabaId,
                'error' => $e->getMessage(),
            ]);

            return false;
        }

        if (! $response->successful()) {
            Log::warning('meta.token_exchange.subscribe_waba_failed', [
                'waba_id' => $wabaId,
                'status' => $response->status(),
                'error' => $response->json('error.message'),
            ]);

            return false;
        }

        Log::info('meta.token_exchange.waba_subscribed', ['waba_id' => $wabaId]);

        return true;
    }

    /**
     * Register phone number on the Cloud API (required for modes A/B, skip for mode C coexistence).
     */
    public function registerPhoneNumber(string $phoneNumberId, string $accessToken, string $pin): bool
    {
        try {
            $response = Http::withToken($accessToken)->timeout(15)->post(
                "https://graph.facebook.com/{$this->graphApiVersion}/{$phoneNumberId}/register",
                ['messaging_product' => 'whatsapp', 'pin' => $pin]
            );
        } catch (Throwable $e) {
            Log::warning('meta.token_exchange.register_phone_exception', [
                'phone_number_id' => $phoneNumberId,
                'error' => $e->getMessage(),
            ]);

            return false;
        }

        if (! $response->successful()) {
            Log::warning('meta.token_exchange.register_phone_failed', [
                'phone_number_id' => $phoneNumberId,
                'status' => $response->status(),
            ]);

            return false;
        }

        return true;
    }

    private function resolveBusinessId(string $userToken): ?string
    {
        $response = Http::withToken($userToken)
            ->timeout(15)
            ->get("https://graph.facebook.com/{$this->graphApiVersion}/me/businesses");

        if (! $response->successful()) {
            return null;
        }

        $businesses = (array) $response->json('data', []);

        return (string) ($businesses[0]['id'] ?? '') ?: null;
    }

    private function resolvePhoneNumberId(string $wabaId, string $userToken): string
    {
        $response = Http::withToken($userToken)->timeout(15)->get(
            "https://graph.facebook.com/{$this->graphApiVersion}/{$wabaId}/phone_numbers",
            ['fields' => 'id,display_phone_number,status']
        );

        if (! $response->successful()) {
            return '';
        }

        $phones = (array) $response->json('data', []);

        return (string) ($phones[0]['id'] ?? '');
    }
}
