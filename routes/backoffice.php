<?php

use App\Http\Controllers\Backoffice\BackofficeActiveTenantController;
use App\Http\Controllers\Backoffice\BackofficeAgentController;
use App\Http\Controllers\Backoffice\BackofficeAgentModelController;
use App\Http\Controllers\Backoffice\BackofficeAgentPromptController;
use App\Http\Controllers\Backoffice\BackofficeAgentToolController;
use App\Http\Controllers\Backoffice\BackofficeController;
use App\Http\Controllers\Backoffice\BackofficeNicheTemplateController;
use App\Http\Controllers\Backoffice\BackofficeTemplateController;
use App\Http\Controllers\Backoffice\BackofficeTenantController;
use App\Http\Controllers\LaboratoryController;
use App\Http\Controllers\PlaygroundController;
use App\Http\Controllers\StressTestController;
use Illuminate\Support\Facades\Route;

/**
 * The prefix is environment-configurable (BACKOFFICE_PATH) so production can
 * hide the backoffice behind a random path. That is an obscurity layer on top
 * of the `super_admin` gate, not a replacement for it.
 */
Route::middleware(['auth', 'super_admin', 'backoffice.context'])
    ->prefix(config('backoffice.path'))
    ->name('backoffice.')
    ->group(function () {
        Route::get('/', [BackofficeController::class, 'index'])->name('index');

        Route::post('empresa-ativa', [BackofficeActiveTenantController::class, 'store'])->name('active-tenant.store');
        Route::delete('empresa-ativa', [BackofficeActiveTenantController::class, 'destroy'])->name('active-tenant.destroy');

        Route::get('agentes', [BackofficeAgentController::class, 'index'])->name('agents.index');
        Route::get('agentes/{agent}', [BackofficeAgentController::class, 'show'])->name('agents.show');
        Route::match(['put', 'patch'], 'agentes/{agent}/modelo', [BackofficeAgentModelController::class, 'update'])->name('agents.model.update');
        Route::get('agentes/{agent}/ferramentas', [BackofficeAgentToolController::class, 'edit'])->name('agents.tools.edit');
        Route::match(['put', 'patch'], 'agentes/{agent}/ferramentas', [BackofficeAgentToolController::class, 'update'])->name('agents.tools.update');
        Route::get('agentes/{agent}/prompt', [BackofficeAgentPromptController::class, 'edit'])->name('agents.prompt.edit');
        Route::match(['put', 'patch'], 'agentes/{agent}/prompt', [BackofficeAgentPromptController::class, 'update'])->name('agents.prompt.update');
        Route::delete('agentes/{agent}/prompt', [BackofficeAgentPromptController::class, 'destroy'])->name('agents.prompt.destroy');

        Route::get('templates', [BackofficeTemplateController::class, 'index'])->name('templates.index');
        Route::get('templates/{template_slug}/edit', [BackofficeTemplateController::class, 'edit'])->name('templates.edit');
        Route::match(['put', 'patch'], 'templates/{template_slug}', [BackofficeTemplateController::class, 'update'])->name('templates.update');

        Route::get('modelos', [BackofficeNicheTemplateController::class, 'index'])->name('niche-templates.index');
        Route::match(['put', 'patch'], 'modelos/{nicheTemplate}', [BackofficeNicheTemplateController::class, 'update'])->name('niche-templates.update');

        Route::get('tenants', [BackofficeTenantController::class, 'index'])->name('tenants.index');

        /**
         * Laboratory — observability, AI cost and infrastructure health. Scoped
         * to whichever tenant the company switcher points at: the controllers
         * read `$user->tenantId`, which resolves the session's active tenant
         * before falling back to the user's own membership.
         */
        Route::get('laboratory', [LaboratoryController::class, 'index'])->name('laboratory');
        Route::get('laboratory/datasets-page', [LaboratoryController::class, 'datasets'])->name('laboratory.datasets');
        Route::get('laboratory/stress-test', [LaboratoryController::class, 'stressTest'])->name('laboratory.stress-test');
        Route::get('laboratory/stress-test/{run}', [LaboratoryController::class, 'stressTestResults'])->name('laboratory.stress-test.results');
        Route::get('laboratory/ai-usage', [LaboratoryController::class, 'aiUsage'])->name('laboratory.ai-usage');
        Route::get('laboratory/health', [LaboratoryController::class, 'health'])->name('laboratory.health');
        Route::get('laboratory/interactions/{interactionId}', [LaboratoryController::class, 'interactionTimeline'])->name('laboratory.interactions.show');
        Route::get('laboratory/leads/{lead}/interactions', [LaboratoryController::class, 'leadInteractionTimeline'])->name('laboratory.leads.interactions');

        Route::prefix('laboratory')->name('laboratory.')->group(function () {
            Route::get('datasets', [StressTestController::class, 'datasets'])->name('datasets.index');
            Route::post('datasets', [StressTestController::class, 'storeDataset'])->name('datasets.store');
            Route::get('datasets/{dataset}', [StressTestController::class, 'showDataset'])->name('datasets.show');
            Route::delete('datasets/{dataset}', [StressTestController::class, 'destroyDataset'])->name('datasets.destroy');
            Route::post('datasets/{dataset}/prefetch', [StressTestController::class, 'prefetchDataset'])->name('datasets.prefetch');
            Route::get('stress-tests', [StressTestController::class, 'runs'])->name('stress-tests.index');
            Route::post('stress-tests', [StressTestController::class, 'storeRun'])->name('stress-tests.store');
            Route::get('stress-tests/{run}', [StressTestController::class, 'showRun'])->name('stress-tests.show');
            Route::post('stress-tests/{run}/cancel', [StressTestController::class, 'cancelRun'])->name('stress-tests.cancel');
        });

        /**
         * Playground — LLM sandbox for rehearsing an agent against a fake lead.
         * Every run bills the platform account, so it stays operator-only.
         */
        Route::prefix('playground')->name('playground.')->group(function () {
            Route::get('/', [PlaygroundController::class, 'index'])->name('index');
            Route::post('/', [PlaygroundController::class, 'store'])->name('store');
            Route::delete('{lead}', [PlaygroundController::class, 'destroy'])->name('destroy');
            Route::post('{lead}/reset', [PlaygroundController::class, 'reset'])->name('reset');
            Route::post('{lead}/prompt', [PlaygroundController::class, 'updatePrompt'])->name('updatePrompt');
            Route::get('{lead}/messages', [PlaygroundController::class, 'messages'])->name('messages');

            // LLM-invoking endpoints — throttled as abuse control (F8). Applies to all
            // users equally; not a feature gate.
            Route::middleware('throttle:30,1')->group(function () {
                Route::post('generate-scenario', [PlaygroundController::class, 'generateScenario'])->name('generateScenario');
                Route::post('scan-blindspots', [PlaygroundController::class, 'scanBlindspots'])->name('scanBlindspots');
                Route::post('{lead}/chat', [PlaygroundController::class, 'chat'])->name('chat');
                Route::post('{lead}/tester-chat', [PlaygroundController::class, 'testerChat'])->name('testerChat');
                Route::post('{lead}/evaluate', [PlaygroundController::class, 'evaluate'])->name('evaluate');
            });
        });
    });
