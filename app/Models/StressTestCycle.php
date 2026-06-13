<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StressTestCycle extends Model
{
    use HasFactory;

    protected $fillable = [
        'stress_test_run_id',
        'cycle_number',
        'cpf_used',
        'scenario',
        'lead_id',
        'status',
        'fidelity_score',
        'hallucinations',
        'token_metrics',
        'evaluation_report',
        'console_errors',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'fidelity_score' => 'decimal:2',
            'hallucinations' => 'array',
            'token_metrics' => 'array',
            'console_errors' => 'array',
            'completed_at' => 'datetime',
        ];
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(StressTestRun::class, 'stress_test_run_id');
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function scopeFailed(Builder $query): Builder
    {
        return $query->where('fidelity_score', '<', 80);
    }
}
