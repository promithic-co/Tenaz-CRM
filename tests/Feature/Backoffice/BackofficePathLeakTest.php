<?php

use Illuminate\Support\Facades\File;

/**
 * BACKOFFICE_PATH is a secret in production. Wayfinder resolves URLs at build
 * time, so `resources/js/routes/backoffice/**` and the Backoffice action files
 * contain whatever prefix was configured when `npm run build` ran. Those files
 * are generated, not authored — the leak only becomes real when a page imports
 * one, which would ship the secret prefix inside the public JS bundle.
 *
 * Frontend code must go through `useBackofficeRoutes`, which reads the prefix
 * from the Inertia props at runtime instead.
 */
it('never imports the generated backoffice routes, which bake the secret prefix into the bundle', function () {
    $generated = ['actions/', 'routes/'];

    $offenders = collect(File::allFiles(resource_path('js')))
        ->map(fn ($file): string => str_replace('\\', '/', $file->getRelativePathname()))
        ->reject(fn (string $path): bool => collect($generated)->contains(
            fn (string $prefix): bool => str_starts_with($path, $prefix)
        ))
        ->filter(fn (string $path): bool => in_array(pathinfo($path, PATHINFO_EXTENSION), ['vue', 'ts', 'js'], true))
        ->filter(function (string $path): bool {
            $source = (string) file_get_contents(resource_path("js/{$path}"));

            return str_contains($source, 'routes/backoffice')
                || str_contains($source, 'Controllers/Backoffice');
        })
        ->values()
        ->all();

    expect($offenders)->toBe([]);
});
