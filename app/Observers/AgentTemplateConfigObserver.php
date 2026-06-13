<?php

namespace App\Observers;

use App\Models\AgentTemplateConfig;
use Illuminate\Support\Facades\Cache;

class AgentTemplateConfigObserver
{
    public function saved(AgentTemplateConfig $config): void
    {
        $this->bustCache($config);
    }

    public function deleted(AgentTemplateConfig $config): void
    {
        $this->bustCache($config);
    }

    /**
     * Driver-agnostic single-key forget.
     * Cache::tags() intentionally NOT used — production CACHE_STORE=database
     * does not support tag-based invalidation (Pitfall C2).
     */
    private function bustCache(AgentTemplateConfig $config): void
    {
        Cache::forget(AgentTemplateConfig::cacheKey($config->template_slug));
    }
}
