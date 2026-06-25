<?php

use App\Jobs\RunStressTestJob;
use App\Models\CpfDataset;
use App\Models\CpfDatasetEntry;
use App\Models\StressTestRun;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
});

it('lists datasets for authenticated user', function () {
    $otherUser = User::factory()->create();
    CpfDataset::factory()->create(['user_id' => $this->user->id, 'name' => 'My Dataset']);
    CpfDataset::factory()->create(['user_id' => $otherUser->id, 'name' => 'Other User Dataset']);

    $response = $this->actingAs($this->user)->getJson(route('laboratory.datasets.index'));

    $response->assertOk();
    $response->assertJsonCount(1, 'data');
    $response->assertJsonPath('data.0.name', 'My Dataset');
});

it('uploads csv dataset', function () {
    $csv = "cpf,nome\n01113404116,LUIZA QUEVEDO\n03082303889,REINALDO JORGE\n";
    $file = UploadedFile::fake()->createWithContent('test.csv', $csv);

    $response = $this->actingAs($this->user)->postJson(route('laboratory.datasets.store'), [
        'file' => $file,
        'name' => 'Uploaded Dataset',
        'description' => 'Test upload',
    ]);

    $response->assertCreated();
    $response->assertJsonPath('data.name', 'Uploaded Dataset');
    $response->assertJsonPath('data.total_entries', 2);

    $dataset = CpfDataset::where('user_id', $this->user->id)->first();
    expect($dataset)->not->toBeNull();
    expect(CpfDatasetEntry::where('cpf_dataset_id', $dataset->id)->count())->toBe(2);
});

it('starts a stress test run', function () {
    Queue::fake();

    $response = $this->actingAs($this->user)->postJson(route('laboratory.stress-tests.store'), [
        'label' => 'My Stress Test',
        'objective' => 'Test fidelity of credit values',
        'cpf_dataset_id' => null,
        'config' => [
            'cycles' => 3,
            'rounds_per_cycle' => 2,
        ],
    ]);

    $response->assertCreated();
    $response->assertJsonPath('data.label', 'My Stress Test');
    $response->assertJsonPath('data.status', 'pending');
    $response->assertJsonPath('data.total_cycles', 3);

    $run = StressTestRun::where('user_id', $this->user->id)->first();
    expect($run)->not->toBeNull();
    Queue::assertPushed(RunStressTestJob::class, fn ($job) => $job->run->id === $run->id);
});

it('shows stress test run with cycles', function () {
    $run = StressTestRun::factory()->create([
        'user_id' => $this->user->id,
        'label' => 'Completed Run',
        'status' => 'completed',
    ]);
    $run->cycles()->create([
        'cycle_number' => 1,
        'status' => 'completed',
        'fidelity_score' => 95.5,
    ]);

    $response = $this->actingAs($this->user)->getJson(route('laboratory.stress-tests.show', $run));

    $response->assertOk();
    $response->assertJsonPath('data.label', 'Completed Run');
    $response->assertJsonPath('data.cycles.0.fidelity_score', 95.5);
});

it('caps the results page cycle list and flags truncation (FE-03)', function () {
    config(['laboratory.stress_result_max_cycles' => 2]);

    $run = StressTestRun::factory()->create([
        'user_id' => $this->user->id,
        'status' => 'running',
        'total_cycles' => 3,
    ]);
    foreach ([1, 2, 3] as $n) {
        $run->cycles()->create(['cycle_number' => $n, 'status' => 'completed']);
    }

    // Only the 2 most-recent cycles are hydrated (returned oldest-first: #2 then #3),
    // and the page is told the array was truncated so the poll never re-ships all 3.
    $this->actingAs($this->user)
        ->get(route('laboratory.stress-test.results', $run))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('laboratory/StressTestResults')
            ->where('run.cycles_truncated', true)
            ->has('run.cycles', 2)
            ->where('run.cycles.0.cycle_number', 2)
            ->where('run.cycles.1.cycle_number', 3)
        );
});

it('cancels a running stress test', function () {
    $run = StressTestRun::factory()->create([
        'user_id' => $this->user->id,
        'status' => 'running',
    ]);

    $response = $this->actingAs($this->user)->postJson(route('laboratory.stress-tests.cancel', $run));

    $response->assertOk();
    $response->assertJsonPath('data.status', 'cancelled');
    expect($run->fresh()->status)->toBe('cancelled');
});

it('rejects invalid stress test config', function () {
    $response = $this->actingAs($this->user)->postJson(route('laboratory.stress-tests.store'), [
        'label' => 'Bad Config',
        'objective' => 'Test',
        'config' => [
            'cycles' => 100,
            'rounds_per_cycle' => 20,
        ],
    ]);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors(['config.cycles', 'config.rounds_per_cycle']);
});

it('scopes datasets and runs to authenticated user', function () {
    $otherUser = User::factory()->create();
    $dataset = CpfDataset::factory()->create(['user_id' => $otherUser->id]);
    $run = StressTestRun::factory()->create(['user_id' => $otherUser->id]);

    $listDatasets = $this->actingAs($this->user)->getJson(route('laboratory.datasets.index'));
    $listDatasets->assertOk();
    $listDatasets->assertJsonCount(0, 'data');

    $showDataset = $this->actingAs($this->user)->getJson(route('laboratory.datasets.show', $dataset));
    $showDataset->assertForbidden();

    $showRun = $this->actingAs($this->user)->getJson(route('laboratory.stress-tests.show', $run));
    $showRun->assertForbidden();
});
