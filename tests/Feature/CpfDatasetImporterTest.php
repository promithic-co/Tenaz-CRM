<?php

use App\Models\CpfDataset;
use App\Models\CpfDatasetEntry;
use App\Models\StressTestCycle;
use App\Models\StressTestRun;
use App\Models\User;
use App\Services\CpfDatasetImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('imports cpf dataset from csv file', function () {
    $user = User::factory()->create();
    $csv = tempnam(sys_get_temp_dir(), 'cpf_test_').'.csv';

    file_put_contents($csv, implode(PHP_EOL, [
        'cpf,nome,status_expected',
        '01113404116,LUIZA QUEVEDO,QUALIFICADO',
        '03082303889,REINALDO JORGE,QUALIFICADO',
    ]));

    $importer = app(CpfDatasetImporter::class);
    $dataset = $importer->importFromCsv($csv, 'Test Dataset', 'Test description', $user->id);

    unlink($csv);

    expect($dataset)->toBeInstanceOf(CpfDataset::class)
        ->and($dataset->name)->toBe('Test Dataset')
        ->and($dataset->total_entries)->toBe(2);

    expect(CpfDatasetEntry::where('cpf_dataset_id', $dataset->id)->count())->toBe(2);
});

it('imports cpf dataset from json file', function () {
    $user = User::factory()->create();
    $json = tempnam(sys_get_temp_dir(), 'cpf_test_').'.json';

    file_put_contents($json, json_encode([
        ['cpf' => '01113404116', 'nome' => 'LUIZA QUEVEDO', 'status_expected' => 'QUALIFICADO'],
        ['cpf' => '03082303889', 'nome' => 'REINALDO JORGE', 'status_expected' => 'QUALIFICADO'],
    ]));

    $importer = app(CpfDatasetImporter::class);
    $dataset = $importer->importFromJson($json, 'Test JSON Dataset', null, $user->id);

    unlink($json);

    expect($dataset)->toBeInstanceOf(CpfDataset::class)
        ->and($dataset->total_entries)->toBe(2);

    expect(CpfDatasetEntry::where('cpf_dataset_id', $dataset->id)->count())->toBe(2);
});

it('updates total_entries count after import', function () {
    $user = User::factory()->create();
    $csv = tempnam(sys_get_temp_dir(), 'cpf_test_').'.csv';

    file_put_contents($csv, implode(PHP_EOL, [
        'cpf,nome,status_expected',
        '01113404116,LUIZA QUEVEDO,QUALIFICADO',
        '03082303889,REINALDO JORGE,QUALIFICADO',
    ]));

    $importer = app(CpfDatasetImporter::class);
    $dataset = $importer->importFromCsv($csv, 'Count Test', null, $user->id);

    unlink($csv);

    $entryCount = CpfDatasetEntry::where('cpf_dataset_id', $dataset->id)->count();

    expect($dataset->total_entries)->toBe($entryCount);
});

it('validates cpf format during import', function () {
    $user = User::factory()->create();
    $csv = tempnam(sys_get_temp_dir(), 'cpf_test_').'.csv';

    file_put_contents($csv, implode(PHP_EOL, [
        'cpf,nome,status_expected',
        '01113404116,LUIZA QUEVEDO,QUALIFICADO',
        '12345678900,INVALID CPF,QUALIFICADO',
    ]));

    $importer = app(CpfDatasetImporter::class);
    $dataset = $importer->importFromCsv($csv, 'Validation Test', null, $user->id);

    unlink($csv);

    expect($dataset->total_entries)->toBe(1);
    expect(CpfDatasetEntry::where('cpf_dataset_id', $dataset->id)->count())->toBe(1);
    expect(CpfDatasetEntry::where('cpf_dataset_id', $dataset->id)->where('cpf', '01113404116')->exists())->toBeTrue();
});

it('creates stress test run with dataset relationship', function () {
    $dataset = CpfDataset::factory()->create();
    $run = StressTestRun::factory()->create(['cpf_dataset_id' => $dataset->id]);

    expect($run->cpfDataset)->toBeInstanceOf(CpfDataset::class)
        ->and($run->cpfDataset->id)->toBe($dataset->id);

    expect($dataset->stressTestRuns()->count())->toBe(1);
});

it('creates stress test cycle with hallucination tracking', function () {
    $cycle = StressTestCycle::factory()->create();

    $hallucinations = [
        ['type' => 'invented_value', 'field' => 'taxa_juros', 'value' => '1.5%'],
        ['type' => 'wrong_name', 'field' => 'banco', 'value' => 'Banco Inexistente'],
    ];

    $cycle->update(['hallucinations' => $hallucinations]);

    $fresh = $cycle->fresh();

    expect($fresh->hallucinations)->toBeArray()
        ->and($fresh->hallucinations)->toHaveCount(2)
        ->and($fresh->hallucinations[0]['type'])->toBe('invented_value');
});

it('scopes failed cycles by fidelity score', function () {
    $run = StressTestRun::factory()->create();

    StressTestCycle::factory()->create(['stress_test_run_id' => $run->id, 'fidelity_score' => 95.00, 'status' => 'completed']);
    StressTestCycle::factory()->create(['stress_test_run_id' => $run->id, 'fidelity_score' => 75.00, 'status' => 'completed']);
    StressTestCycle::factory()->create(['stress_test_run_id' => $run->id, 'fidelity_score' => 60.00, 'status' => 'completed']);

    $failedCycles = StressTestCycle::where('stress_test_run_id', $run->id)->failed()->get();

    expect($failedCycles)->toHaveCount(2);
    expect($failedCycles->pluck('fidelity_score')->map(fn ($s) => (float) $s)->toArray())->toContain(75.0)
        ->toContain(60.0);
});
