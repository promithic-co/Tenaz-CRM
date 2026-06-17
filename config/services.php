<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'credflow' => [
        'api_key' => env('TENAZ_API_KEY') ?: env('CREDFLOW_API_KEY') ?: env('ARIA_API_KEY'),
        'default_tenant_id' => env('TENAZ_DEFAULT_TENANT_ID', 'default'),
        'api_keys' => collect(explode(',', (string) env('TENAZ_API_KEYS', '')))
            ->mapWithKeys(function (string $pair): array {
                $parts = explode(':', trim($pair), 2);

                if (count($parts) !== 2 || trim($parts[0]) === '' || trim($parts[1]) === '') {
                    return [];
                }

                return [trim($parts[0]) => trim($parts[1])];
            })
            ->all(),
        'webhook_consulta' => env('TENAZ_WEBHOOK_CONSULTA') ?: env('CREDFLOW_WEBHOOK_CONSULTA') ?: env('ARIA_WEBHOOK_CONSULTA'),
        'webhook_consulta_siape' => env('TENAZ_WEBHOOK_CONSULTA_SIAPE') ?: env('CREDFLOW_WEBHOOK_CONSULTA_SIAPE') ?: env('ARIA_WEBHOOK_CONSULTA_SIAPE'),
        'webhook_escalar' => env('TENAZ_WEBHOOK_ESCALAR') ?: env('CREDFLOW_WEBHOOK_ESCALAR') ?: env('ARIA_WEBHOOK_ESCALAR'),
        'webhook_registrar' => env('TENAZ_WEBHOOK_REGISTRAR') ?: env('CREDFLOW_WEBHOOK_REGISTRAR') ?: env('ARIA_WEBHOOK_REGISTRAR'),
    ],

    'promosys' => [
        'base_url' => env('PROMOSYS_BASE_URL', 'https://jcf.promosysweb.com/services'),
        'usuario' => env('PROMOSYS_USUARIO'),
        'senha' => env('PROMOSYS_SENHA'),
    ],

    'meta' => [
        'app_id' => env('META_APP_ID'),
        'app_secret' => env('META_APP_SECRET'),
        'verify_token' => env('META_WEBHOOK_VERIFY_TOKEN'),
        'app_config_id' => env('META_APP_CONFIG_ID'),
        'app_config_id_coexistence' => env('META_APP_CONFIG_ID_COEXISTENCE'),
        'graph_api_version' => env('META_GRAPH_API_VERSION', 'v23.0'),
    ],

    'twilio' => [
        'sid' => env('TWILIO_ACCOUNT_SID'),
        'token' => env('TWILIO_AUTH_TOKEN'),
        'phone_number' => env('TWILIO_PHONE_NUMBER'),
    ],

    'ura' => [
        'api_key' => env('URA_API_KEY'),
    ],

    'google_tts' => [
        'api_key' => env('GOOGLE_TTS_API_KEY'),
    ],

];
