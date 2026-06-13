<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default AI Provider Names
    |--------------------------------------------------------------------------
    */

    'default'                   => 'openai',
    'default_for_images'        => 'gemini',
    'default_for_audio'         => 'openai',
    'default_for_transcription' => 'openai',
    'default_for_embeddings'    => 'openai',
    'default_for_reranking'     => 'cohere',

    /*
    |--------------------------------------------------------------------------
    | Caching
    |--------------------------------------------------------------------------
    */

    'caching' => [
        'embeddings' => [
            'cache' => false,
            'store' => env('CACHE_STORE', 'database'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | AI Providers
    |--------------------------------------------------------------------------
    */

    'providers' => [
        'anthropic' => [
            'driver' => 'anthropic',
            'key'    => env('ANTHROPIC_API_KEY'),
        ],

        'azure' => [
            'driver'               => 'azure',
            'key'                  => env('AZURE_OPENAI_API_KEY'),
            'url'                  => env('AZURE_OPENAI_URL'),
            'api_version'          => env('AZURE_OPENAI_API_VERSION', '2024-10-21'),
            'deployment'           => env('AZURE_OPENAI_DEPLOYMENT', 'gpt-4o'),
            'embedding_deployment' => env('AZURE_OPENAI_EMBEDDING_DEPLOYMENT', 'text-embedding-3-small'),
        ],

        'cohere' => [
            'driver' => 'cohere',
            'key'    => env('COHERE_API_KEY'),
        ],

        'deepseek' => [
            'driver' => 'deepseek',
            'key'    => env('DEEPSEEK_API_KEY'),
        ],

        'eleven' => [
            'driver' => 'eleven',
            'key'    => env('ELEVENLABS_API_KEY'),
        ],

        'gemini' => [
            'driver' => 'gemini',
            'key'    => env('GEMINI_API_KEY'),
        ],

        'groq' => [
            'driver' => 'groq',
            'key'    => env('GROQ_API_KEY'),
        ],

        'jina' => [
            'driver' => 'jina',
            'key'    => env('JINA_API_KEY'),
        ],

        'mistral' => [
            'driver' => 'mistral',
            'key'    => env('MISTRAL_API_KEY'),
        ],

        'ollama' => [
            'driver' => 'ollama',
            'key'    => env('OLLAMA_API_KEY', ''),
            'url'    => env('OLLAMA_BASE_URL', 'http://localhost:11434'),
        ],

        'openai' => [
            'driver' => 'openai',
            'key'    => env('OPENAI_API_KEY'),
        ],

        'openrouter' => [
            'driver' => 'openrouter',
            'key'    => env('OPENROUTER_API_KEY'),
        ],

        'voyageai' => [
            'driver' => 'voyageai',
            'key'    => env('VOYAGEAI_API_KEY'),
        ],

        'xai' => [
            'driver' => 'xai',
            'key'    => env('XAI_API_KEY'),
        ],
    ],

];
