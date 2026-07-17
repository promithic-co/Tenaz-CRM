<?php

namespace App\Observers;

use App\Models\NicheTemplate;
use Illuminate\Support\Facades\Cache;

class NicheTemplateObserver
{
    public function saved(NicheTemplate $template): void
    {
        $this->bustCache();
    }

    public function deleted(NicheTemplate $template): void
    {
        $this->bustCache();
    }

    /**
     * Driver-agnostic single-key forget.
     * Cache::tags() intentionally NOT used — production CACHE_STORE=database
     * does not support tag-based invalidation (Pitfall C2).
     */
    private function bustCache(): void
    {
        Cache::forget(NicheTemplate::REGISTRY_CACHE_KEY);
    }
}
