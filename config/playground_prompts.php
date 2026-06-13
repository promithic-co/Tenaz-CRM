<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Playground red-team agent instructions
    |--------------------------------------------------------------------------
    |
    | Static system-prompt strings for the named playground agents promoted from
    | the former anonymous Agent subclasses. The interpolated user prompts
    | (attack-plan / scenario-director text) are built at call time and are NOT
    | stored here.
    */
    'blindspot_scanner_instructions' => 'Você é um Red Team AI expert.',

    'scenario_generator_instructions' => 'Exija desafios em nivel operacional.',

    /*
    | Default model used by both playground red-team agents when the caller does
    | not pass a tester_model override.
    */
    'default_model' => 'gpt-4o',
];
