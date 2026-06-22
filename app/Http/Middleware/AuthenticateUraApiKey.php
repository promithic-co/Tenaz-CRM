<?php

namespace App\Http\Middleware;

use App\Models\UraApiKey;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateUraApiKey
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->header('X-URA-API-Key') ?? $request->bearerToken();

        if (! $token) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        // DB-based per-tenant key (new /trigger endpoint)
        $apiKey = UraApiKey::findByPlainKey($token);

        if ($apiKey) {
            $this->touchApiKeyIfStale($apiKey);
            $request->attributes->set('ura_api_key', $apiKey);

            return $next($request);
        }

        // Legacy config-based key (backward compat for /inbound-lead)
        $legacyKey = config('services.ura.api_key');
        if ($legacyKey && hash_equals($legacyKey, $token)) {
            return $next($request);
        }

        return response()->json(['error' => 'Unauthorized'], 401);
    }

    private function touchApiKeyIfStale(UraApiKey $apiKey): void
    {
        $debounceSeconds = (int) config('credflow.api.ura_key_last_used_debounce_seconds', 300);

        if ($debounceSeconds > 0
            && $apiKey->last_used_at !== null
            && $apiKey->last_used_at->gt(now()->subSeconds($debounceSeconds))) {
            return;
        }

        $apiKey->forceFill(['last_used_at' => now()])->save();
    }
}
