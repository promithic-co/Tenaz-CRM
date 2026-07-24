<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Backoffice URL prefix
    |--------------------------------------------------------------------------
    |
    | The super-admin backoffice is mounted under this path. In production it
    | should be a long random string so the area is not discoverable by
    | crawling. This is an obscurity layer ON TOP of the `super_admin`
    | middleware gate, never a replacement for it.
    |
    | The prefix must never be baked into the JS bundle at build time — the
    | frontend reads it from the `backoffice.path` Inertia prop instead of
    | Wayfinder, which resolves URLs at build time.
    |
    */

    'path' => env('BACKOFFICE_PATH', 'backoffice'),

];
