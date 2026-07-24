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
    });
