<?php

namespace App\Http\Controllers;

use App\Exceptions\MetaApiException;
use App\Http\Requests\MetaEmbeddedSignupRequest;
use App\Services\WhatsApp\MetaTokenExchangeService;
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
    public function callback(MetaEmbeddedSignupRequest $request): \Illuminate\Http\JsonResponse
    {
        try {
            $result = $this->tokenService->exchangeCodeForPermanentToken(
                code: $request->string('code')->toString(),
                wabaId: $request->string('waba_id')->toString(),
                phoneNumberId: $request->string('phone_number_id', '')->toString(),
            );
        } catch (MetaApiException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $token = Str::random(64);

        Cache::put("meta_signup:{$token}", [
            'access_token'    => $result['access_token'],
            'system_user_id'  => $result['system_user_id'],
            'permanent'       => $result['permanent'],
            'waba_id'         => $result['waba_id'],
            'phone_number_id' => $result['phone_number_id'],
            'mode'            => $request->input('mode', 'new'),
            'meta_pin'        => $request->input('meta_pin'),
        ], now()->addMinutes(30));

        return response()->json([
            'signup_token'    => $token,
            'phone_number_id' => $result['phone_number_id'],
            'waba_id'         => $result['waba_id'],
        ]);
    }
}
