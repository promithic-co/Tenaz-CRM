<?php

it('keeps the global mobile viewport and overflow safeguards enabled', function () {
    $document = file_get_contents(resource_path('views/app.blade.php'));
    $styles = file_get_contents(resource_path('css/app.css'));

    expect($document)
        ->toContain('width=device-width, initial-scale=1')
        ->and($styles)
        ->toContain('min-height: 100svh;')
        ->toContain('overflow-x: hidden;')
        ->toContain('@media (width < 40rem)');
});

it('keeps critical application surfaces responsive', function (string $path, array $markers) {
    $content = file_get_contents(base_path($path));

    foreach ($markers as $marker) {
        expect($content)->toContain($marker);
    }
})->with([
    'application shell' => [
        'resources/js/layouts/app/AppSidebarLayout.vue',
        ['min-w-0 overflow-x-hidden'],
    ],
    'mobile sidebar' => [
        'resources/js/components/ui/sidebar/Sidebar.vue',
        ['v-else-if="isMobile"', '<SheetContent'],
    ],
    'conversations' => [
        'resources/js/pages/conversas/Index.vue',
        ['100svh', 'showMobileDetails', '<SheetContent'],
    ],
    'pipeline' => [
        'resources/js/pages/pipeline/Index.vue',
        ['100svh', 'snap-x', 'touch-none'],
    ],
    'playground' => [
        'resources/js/pages/playground/Index.vue',
        ['100svh', 'showDebugPanel', 'xl:hidden'],
    ],
    'contacts table' => [
        'resources/js/pages/contatos/Index.vue',
        ['overflow-x-auto', 'min-w-[60rem]'],
    ],
    'campaigns table' => [
        'resources/js/pages/campanhas/Index.vue',
        ['overflow-x-auto', 'min-w-[56rem]'],
    ],
]);
