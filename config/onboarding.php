<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Onboarding Wizard Enabled
    |--------------------------------------------------------------------------
    |
    | When false, the EnsureOnboarded middleware is bypassed for every request
    | and incomplete tenant owners are never redirected to the guided wizard
    | at /onboarding. Agents, WhatsApp instances and personas are then created
    | manually through the regular application screens. Set ONBOARDING_ENABLED
    | back to true to re-enable the guided wizard for new owners.
    |
    */
    'enabled' => (bool) env('ONBOARDING_ENABLED', true),
];
