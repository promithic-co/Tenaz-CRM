<?php

use App\Http\Controllers\AgentController;
use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\IvrController;
use App\Http\Middleware\AuthenticateApiKey;
use Illuminate\Support\Facades\Route;

// Public health endpoint — no auth required
Route::get('/health', HealthController::class)->name('api.health');

// Twilio IVR/URA outbound call webhooks — assinatura Twilio (não API key)
Route::prefix('ivr/call')->middleware('twilio.signature')->group(function () {
    Route::post('{voiceCampaignCall}/script', [IvrController::class, 'script'])
        ->name('ivr.script');
    Route::post('{voiceCampaignCall}/dtmf', [IvrController::class, 'handleDtmf'])
        ->name('ivr.dtmf');
    Route::post('{voiceCampaignCall}/status', [IvrController::class, 'statusCallback'])
        ->name('ivr.status');
});

// Inbound URA lead API — external IVR systems push interested leads
Route::prefix('ura')->middleware(['ura.api_key', 'throttle:ura-inbound'])->group(function () {
    Route::post('inbound-lead', [\App\Http\Controllers\UraInboundController::class, 'store'])
        ->name('ura.inbound-lead');
    Route::post('trigger', [\App\Http\Controllers\UraInboundController::class, 'trigger'])
        ->name('ura.trigger');
});

// Meta Cloud API webhook — HMAC-SHA256 authenticated inside the controller, not via middleware.
Route::get('/webhooks/meta', [\App\Http\Controllers\MetaWebhookController::class, 'verify'])
    ->name('webhooks.meta.verify');
Route::post('/webhooks/meta', [\App\Http\Controllers\MetaWebhookController::class, 'handle'])
    ->middleware('throttle:meta-webhook')
    ->name('webhooks.meta.handle');

Route::middleware([AuthenticateApiKey::class])->group(function () {
    // Direct agent access (used by n8n or integration tests)
    Route::post('/tenaz', [AgentController::class, 'tenaz'])
        ->middleware('throttle:tenaz-direct')
        ->name('api.tenaz');

    // Legacy direct agent access kept for existing integrations.
    Route::post('/aria', [AgentController::class, 'aria'])
        ->middleware('throttle:aria-direct')
        ->name('api.aria');
});
