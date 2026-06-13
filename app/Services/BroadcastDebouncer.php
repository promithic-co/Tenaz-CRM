<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

class BroadcastDebouncer
{
    /**
     * Returns true the first time the key is seen within the TTL window.
     * Uses Cache::add (SET IF NOT EXISTS) — atomic, no race condition.
     */
    public function shouldFire(string $key, int $ttlSeconds): bool
    {
        return Cache::add("broadcast:debounce:{$key}", 1, $ttlSeconds);
    }
}
