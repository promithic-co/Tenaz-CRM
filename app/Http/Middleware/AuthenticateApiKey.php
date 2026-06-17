<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateApiKey
{
    /** Request attribute holding the tenant bound to the authenticated API key. */
    public const TENANT_ATTRIBUTE = 'api_tenant_id';

    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();
        $tenantId = $token ? $this->resolveTenantForToken($token) : null;

        if ($tenantId === null) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $request->attributes->set(self::TENANT_ATTRIBUTE, $tenantId);

        return $next($request);
    }

    /**
     * Resolve the tenant bound to the presented API key using a timing-safe
     * comparison against every configured key. Returns null when no key matches.
     */
    private function resolveTenantForToken(string $token): ?string
    {
        $match = null;

        foreach ($this->keyTenantMap() as $key => $tenant) {
            if ($key !== '' && hash_equals((string) $key, $token)) {
                $match = (string) $tenant;
            }
        }

        return $match;
    }

    /**
     * Map of API key => tenant_id. The legacy single key is bound to the
     * configured default tenant so existing integrations keep working.
     *
     * @return array<string, string>
     */
    private function keyTenantMap(): array
    {
        $map = config('services.credflow.api_keys', []);

        $legacyKey = config('services.credflow.api_key');
        if ($legacyKey) {
            $map[$legacyKey] = (string) config('services.credflow.default_tenant_id', 'default');
        }

        return $map;
    }
}
