<?php

namespace App\Models;

use App\Enums\TenantRole;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class TenantInvitation extends Model
{
    /** @use HasFactory<\Database\Factories\TenantInvitationFactory> */
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'invited_by_user_id',
        'email',
        'role',
        'token',
        'expires_at',
        'accepted_at',
    ];

    protected function casts(): array
    {
        return [
            'role' => TenantRole::class,
            'expires_at' => 'datetime',
            'accepted_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function invitedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by_user_id');
    }

    /**
     * Generate a signing pair: the plain token (returned to caller for the URL)
     * and the hashed form that is persisted on the record. We use a deterministic
     * sha256 so we can look invitations up by the hashed token.
     *
     * @return array{plain: string, hashed: string}
     */
    public static function generateToken(): array
    {
        $plain = Str::random(48);

        return [
            'plain' => $plain,
            'hashed' => self::hashToken($plain),
        ];
    }

    public static function hashToken(string $plain): string
    {
        return hash('sha256', $plain);
    }

    public function matchesToken(string $plain): bool
    {
        return hash_equals($this->token, self::hashToken($plain));
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function isPending(): bool
    {
        return $this->accepted_at === null && ! $this->isExpired();
    }

    public function markAccepted(): void
    {
        $this->forceFill(['accepted_at' => now()])->save();
    }
}
