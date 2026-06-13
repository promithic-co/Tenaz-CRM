<?php

use Illuminate\Support\Facades\Route;

it('removes Gupshup provider classes', function () {
    $deletedFiles = [
        base_path('app/Services/WhatsApp/Providers/GupshupProvider.php'),
        base_path('app/Services/WhatsApp/Providers/GupshupInstanceManager.php'),
        base_path('app/Services/WhatsApp/GupshupOptInService.php'),
        base_path('app/Services/WhatsApp/GupshupSetupService.php'),
        base_path('app/Services/WhatsApp/GupshupSubscriptionService.php'),
        base_path('app/Services/WhatsApp/GupshupTemplateService.php'),
        base_path('app/Console/Commands/DiagnoseGupshupInstanceCommand.php'),
        base_path('app/Console/Commands/SetupGupshupSubscriptionsCommand.php'),
        base_path('app/Jobs/SyncGupshupTemplatesJob.php'),
        base_path('app/Jobs/ProcessOptInBatchJob.php'),
        base_path('app/Jobs/ProcessUserOptInEventJob.php'),
    ];

    foreach ($deletedFiles as $path) {
        expect(file_exists($path))->toBeFalse("Expected {$path} to be deleted");
    }
});

it('removes Waha provider classes', function () {
    $deletedFiles = [
        base_path('app/Services/WhatsApp/Providers/WahaProvider.php'),
        base_path('app/Services/WhatsApp/Providers/WahaInstanceManager.php'),
    ];

    foreach ($deletedFiles as $path) {
        expect(file_exists($path))->toBeFalse("Expected {$path} to be deleted");
    }
});

it('routes/api.php has no gupshup webhook', function () {
    $routes = Route::getRoutes();

    expect($routes->getByName('api.webhook.gupshup'))->toBeNull()
        ->and($routes->getByName('api.webhook.gupshup.app'))->toBeNull();

    $apiContent = file_get_contents(base_path('routes/api.php'));
    expect($apiContent)->not->toContain('gupshup');
});

it('test_no_gupshup_string_in_app_dir', function () {
    $pattern = 'gupshup\|waha\|Gupshup\|Waha';

    // Directories to search (relative to base_path).
    // resources/js/actions and resources/js/routes are Wayfinder-generated and must be clean after npm run build.
    $searchPaths = [
        'app',
        'resources/js/pages',
        'resources/js/actions',
        'resources/js/routes',
        'routes',
        'config',
    ];

    $found = [];

    foreach ($searchPaths as $dir) {
        $fullDir = base_path($dir);

        if (! is_dir($fullDir)) {
            continue;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($fullDir, RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if (! $file->isFile()) {
                continue;
            }

            $ext = $file->getExtension();

            if (! in_array($ext, ['php', 'ts', 'vue', 'js'])) {
                continue;
            }

            $content = file_get_contents($file->getRealPath());

            if (
                preg_match('/gupshup/i', $content) ||
                (preg_match('/waha/i', $content) && ! str_contains($file->getRealPath(), 'Phase43'))
            ) {
                $found[] = str_replace(base_path('/'), '', $file->getRealPath());
            }
        }
    }

    expect($found)->toBe([], 'Found Gupshup/Waha references in: '.implode(', ', $found));
});
