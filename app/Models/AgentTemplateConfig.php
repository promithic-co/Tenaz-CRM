<?php

namespace App\Models;

use App\Observers\AgentTemplateConfigObserver;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AgentTemplateConfig extends Model
{
    /** @use HasFactory<\Database\Factories\AgentTemplateConfigFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::observe(AgentTemplateConfigObserver::class);
    }

    protected $fillable = [
        'template_slug',
        'agent_provider',
        'agent_model',
        'transcription_provider',
        'transcription_model',
        'vision_provider',
        'vision_model',
        'temperature',
        'max_tokens',
        'max_conversation_messages',
    ];

    protected function casts(): array
    {
        return [
            'temperature' => 'float',
            'max_tokens' => 'integer',
            'max_conversation_messages' => 'integer',
        ];
    }

    /**
     * Shared cache key for this template slug.
     * Used by AgentTemplateConfigObserver (cache bust) and the resolver waterfall (cache read).
     */
    public static function cacheKey(string $slug): string
    {
        return "agent_template_config_{$slug}";
    }
}
