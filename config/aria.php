<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Smart Lists
    |--------------------------------------------------------------------------
    |
    | Configuration for dynamic contact list resolution (Phase 51).
    |
    */
    'smart_lists' => [
        /*
         * Maximum number of leads a dynamic list may resolve before materialize
         * throws InvalidFiltersException. Protects against unbounded INSERT
         * operations that could exhaust memory or lock contact_list_entries.
         *
         * Operators must add more filters to reduce scope below this cap.
         */
        'max_resolve' => env('ARIA_SMART_LIST_MAX_RESOLVE', 100_000),
    ],
];
