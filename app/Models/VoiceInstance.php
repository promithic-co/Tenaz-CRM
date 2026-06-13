<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VoiceInstance extends Model
{
    /** @use HasFactory<\Database\Factories\VoiceInstanceFactory> */
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'user_id',
        'name',
        'display_name',
        'whatsapp_instance_id',
        'post_call_meta_template_id',
        'greeting_template',
        'post_call_message',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function whatsappInstance(): BelongsTo
    {
        return $this->belongsTo(WhatsappInstance::class, 'whatsapp_instance_id');
    }

    public function postCallMetaTemplate(): BelongsTo
    {
        return $this->belongsTo(WhatsappTemplate::class, 'post_call_meta_template_id');
    }

    public function label(): string
    {
        return $this->display_name ?: $this->name;
    }
}
