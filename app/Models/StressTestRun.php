<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StressTestRun extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'cpf_dataset_id',
        'label',
        'objective',
        'config',
        'status',
        'total_cycles',
        'completed_cycles',
        'results_summary',
        'started_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'config' => 'array',
            'results_summary' => 'array',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function cpfDataset(): BelongsTo
    {
        return $this->belongsTo(CpfDataset::class);
    }

    public function cycles(): HasMany
    {
        return $this->hasMany(StressTestCycle::class);
    }

    public function scopeRunning(Builder $query): Builder
    {
        return $query->where('status', 'running');
    }

    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('status', 'completed');
    }
}
