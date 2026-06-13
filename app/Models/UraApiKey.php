<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class UraApiKey extends Model
{
    /** @use HasFactory<\Database\Factories\UraApiKeyFactory> */
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'agent_id',
        'whatsapp_template_id',
        'name',
        'key_hash',
        'key_preview',
        'active',
        'last_used_at',
    ];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'last_used_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'tenant_id');
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    public function whatsappTemplate(): BelongsTo
    {
        return $this->belongsTo(WhatsappTemplate::class);
    }

    /**
     * Generate a new API key, returning [plaintext, model_attributes].
     *
     * @return array{key: string, key_hash: string, key_preview: string}
     */
    public static function generate(): array
    {
        $plain = 'ura_'.Str::random(40);

        return [
            'key' => $plain,
            'key_hash' => hash('sha256', $plain),
            'key_preview' => substr($plain, -8),
        ];
    }

    public static function findByPlainKey(string $plain): ?self
    {
        return self::where('key_hash', hash('sha256', $plain))
            ->where('active', true)
            ->first();
    }
}
