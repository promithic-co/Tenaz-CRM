<?php

use App\Http\Middleware\AuthenticateApiKey;
use App\Http\Middleware\AuthenticateUraApiKey;
use App\Http\Middleware\EnsureOnboarded;
use App\Http\Middleware\EnsureSuperAdmin;
use App\Http\Middleware\EnsureTenantRole;
use App\Http\Middleware\FlushLangfuse;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\ShareBackofficeContext;
use App\Http\Middleware\ValidateTwilioSignature;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(at: '*');

        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        $middleware->web(append: [
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
            FlushLangfuse::class,
        ]);

        $middleware->alias([
            'api.key' => AuthenticateApiKey::class,
            'twilio.signature' => ValidateTwilioSignature::class,
            'ura.api_key' => AuthenticateUraApiKey::class,
            'role' => EnsureTenantRole::class,
            'super_admin' => EnsureSuperAdmin::class,
            'backoffice.context' => ShareBackofficeContext::class,
            'onboarded' => EnsureOnboarded::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
