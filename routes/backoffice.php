<?php

use App\Http\Controllers\Backoffice\BackofficeController;
use App\Http\Controllers\Backoffice\BackofficeNicheTemplateController;
use App\Http\Controllers\Backoffice\BackofficeTemplateController;
use App\Http\Controllers\Backoffice\BackofficeTenantController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'super_admin'])->prefix('backoffice')->name('backoffice.')->group(function () {
    Route::get('/', [BackofficeController::class, 'index'])->name('index');

    Route::get('templates', [BackofficeTemplateController::class, 'index'])->name('templates.index');
    Route::get('templates/{template_slug}/edit', [BackofficeTemplateController::class, 'edit'])->name('templates.edit');
    Route::match(['put', 'patch'], 'templates/{template_slug}', [BackofficeTemplateController::class, 'update'])->name('templates.update');

    Route::get('modelos', [BackofficeNicheTemplateController::class, 'index'])->name('niche-templates.index');
    Route::match(['put', 'patch'], 'modelos/{nicheTemplate}', [BackofficeNicheTemplateController::class, 'update'])->name('niche-templates.update');

    Route::get('tenants', [BackofficeTenantController::class, 'index'])->name('tenants.index');
});
