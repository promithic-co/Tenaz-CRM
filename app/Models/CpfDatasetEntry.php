<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CpfDatasetEntry extends Model
{
    use HasFactory;

    protected $fillable = [
        'cpf_dataset_id',
        'cpf',
        'nome',
        'status_expected',
        'qualified_json',
        'promosys_raw',
    ];

    protected function casts(): array
    {
        return [
            'qualified_json' => 'array',
            'promosys_raw' => 'array',
        ];
    }

    public function dataset(): BelongsTo
    {
        return $this->belongsTo(CpfDataset::class, 'cpf_dataset_id');
    }
}
