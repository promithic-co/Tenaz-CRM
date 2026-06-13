<?php

use App\Http\Controllers\OnboardingController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/onboarding', [OnboardingController::class, 'show'])->name('onboarding.show');
    Route::post('/onboarding/agent', [OnboardingController::class, 'storeAgent'])->name('onboarding.agent');
    Route::post('/onboarding/instance', [OnboardingController::class, 'storeInstance'])->name('onboarding.instance');
    Route::post('/onboarding/persona', [OnboardingController::class, 'storePersona'])->name('onboarding.persona');
    Route::get('/onboarding/complete/{agent}', [OnboardingController::class, 'complete'])->name('onboarding.complete');
});
