<?php

namespace App\Http\Controllers;

use App\Exceptions\MetaApiException;
use App\Http\Requests\MetaEmbeddedSignupRequest;
use App\Services\WhatsApp\MetaTokenExchangeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class MetaEmbeddedSignupController extends Controller
{
    public function __construct(private readonly MetaTokenExchangeService $tokenService) {}

    /**
     * Receives the Embedded Signup callback from the Vue layer (code + WABA/phone IDs
     * coming from the JS SDK's FINISH event), exchanges the code for a (permanent)
     * access token and stashes everything under a short-lived signup_token in Redis/cache.
     * The frontend then POSTs that token when creating the WhatsApp instance.
     */
    public function callback(MetaEmbeddedSignupRequest $request): JsonResponse
    {
        try {
            $result = $this->tokenService->exchangeCodeForPermanentToken(
                code: $request->string('code')->toString(),
                wabaId: $request->string('waba_id')->toString(),
                businessId: $request->string('business_id', '')->toString(),
                phoneNumberId: $request->string('phone_number_id', '')->toString(),
            );

            $coexistence = $request->string('finish_type')->toString()
                === 'FINISH_WHATSAPP_BUSINESS_APP_ONBOARDING';

            if ($coexistence && ! $this->tokenService->isCoexistencePhone(
                $result['phone_number_id'],
                $result['access_token'],
            )) {
                throw new MetaApiException('A Meta não confirmou a coexistência deste número com o WhatsApp Business.');
            }
        } catch (MetaApiException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $token = Str::random(64);

        Cache::put("meta_signup:{$token}", [
            'access_token' => $result['access_token'],
            'system_user_id' => $result['system_user_id'],
            'permanent' => $result['permanent'],
            'business_id' => $result['business_id'],
            'waba_id' => $result['waba_id'],
            'phone_number_id' => $result['phone_number_id'],
            'coexistence' => $coexistence,
        ], now()->addMinutes(30));

        return response()->json([
            'signup_token' => $token,
            'business_id' => $result['business_id'],
            'phone_number_id' => $result['phone_number_id'],
            'waba_id' => $result['waba_id'],
            'coexistence' => $coexistence,
        ]);
    }
}
