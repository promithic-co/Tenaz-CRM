<?php

use App\Ai\Agents\CltAgent;
use App\Ai\Agents\CredFlowAgent;
use App\Ai\Agents\CredFlowBulkAgent;
use App\Ai\Agents\CredFlowFollowUpAgent;
use App\Ai\Agents\SiapeAgent;

return [
    // Janela de debounce em segundos (agrupa mensagens enviadas em sequência rápida)
    'debounce_seconds' => env('DEBOUNCE_SECONDS', 3),

    'agent' => [
        // Last-resort fallback LLM provider/model when no template config or client config is set (LLM-04 / Pitfall C1)
        'fallback_provider' => env('TENAZ_AGENT_FALLBACK_PROVIDER', env('CREDFLOW_AGENT_FALLBACK_PROVIDER', 'openrouter')),
        'fallback_model' => env('TENAZ_AGENT_FALLBACK_MODEL', env('CREDFLOW_AGENT_FALLBACK_MODEL', 'anthropic/claude-haiku-4-5')),
        // Allowed LLM provider values for super-admin template config (StoreAgentTemplateConfigRequest whitelist)
        'provider_whitelist' => ['openai', 'openrouter', 'groq', 'anthropic', 'gemini'],
        // Sliding window: últimas N mensagens enviadas ao LLM (reduz custo em conversas longas)
        'max_conversation_messages' => (int) env('TENAZ_AGENT_MAX_MESSAGES', env('CREDFLOW_AGENT_MAX_MESSAGES', env('ARIA_AGENT_MAX_MESSAGES', 24))),
        // Consistência e fluência (0.2–0.5 para CS; evita alucinações)
        'temperature' => (float) env('TENAZ_AGENT_TEMPERATURE', env('CREDFLOW_AGENT_TEMPERATURE', env('ARIA_AGENT_TEMPERATURE', 0.4))),
        'max_tokens' => (int) env('TENAZ_AGENT_MAX_TOKENS', env('CREDFLOW_AGENT_MAX_TOKENS', env('ARIA_AGENT_MAX_TOKENS', 1024))),
        'max_steps' => (int) env('TENAZ_AGENT_MAX_STEPS', env('CREDFLOW_AGENT_MAX_STEPS', env('ARIA_AGENT_MAX_STEPS', 10))),
        // Hard ceiling on total steps across the full request lifecycle (including fact-check retries)
        'max_total_steps' => (int) env('TENAZ_AGENT_MAX_TOTAL_STEPS', env('CREDFLOW_AGENT_MAX_TOTAL_STEPS', env('ARIA_AGENT_MAX_TOTAL_STEPS', 12))),
        // Maximum elapsed seconds before AgentService skips a new prompt call
        'timeout_seconds' => (int) env('TENAZ_AGENT_TIMEOUT_SECONDS', env('CREDFLOW_AGENT_TIMEOUT_SECONDS', env('ARIA_AGENT_TIMEOUT_SECONDS', 45))),
        // Minimal experiment marker used by Laboratory AI Runs.
        'architecture_version' => env('TENAZ_AGENT_ARCHITECTURE_VERSION', env('CREDFLOW_AGENT_ARCHITECTURE_VERSION', 'legacy_prompt')),
        // Runtime provider failover (F5): on a FailoverableException (rate limit / overload),
        // laravel/ai retries the prompt against this provider/model before bubbling the error.
        // Distinct from fallback_provider above, which is a config-default for resolution only.
        'failover' => [
            'enabled' => (bool) env('TENAZ_AGENT_FAILOVER_ENABLED', false),
            'provider' => env('TENAZ_AGENT_FAILOVER_PROVIDER'),
            'model' => env('TENAZ_AGENT_FAILOVER_MODEL'),
        ],
        // Token budget warning thresholds (configurable per environment)
        'token_warning_prompt' => (int) env('TENAZ_TOKEN_WARNING_PROMPT', env('CREDFLOW_TOKEN_WARNING_PROMPT', env('ARIA_TOKEN_WARNING_PROMPT', 3000))),
        'token_warning_total' => (int) env('TENAZ_TOKEN_WARNING_TOTAL', env('CREDFLOW_TOKEN_WARNING_TOTAL', env('ARIA_TOKEN_WARNING_TOTAL', 4000))),
    ],

    // Agent class registry: maps niche.modo → Agent FQCN
    'agents' => [
        'inss' => [
            'receptivo' => CredFlowAgent::class,
            'bulk' => CredFlowBulkAgent::class,
            'followup' => CredFlowFollowUpAgent::class,
        ],
        'siape' => [
            'receptivo' => SiapeAgent::class,
        ],
        'clt' => [
            'receptivo' => CltAgent::class,
        ],
    ],

    'agent_specializations' => [
        'inss' => [
            'label' => 'INSS',
            'description' => 'Aposentados e pensionistas com benefício consignável.',
            'badge_classes' => 'border-teal-500/30 bg-teal-500/10 text-teal-600 dark:text-teal-400',
        ],
        'clt' => [
            'label' => 'CLT',
            'description' => 'Trabalhadores com carteira assinada e vínculo empregatício privado.',
            'badge_classes' => 'border-sky-500/30 bg-sky-500/10 text-sky-600 dark:text-sky-400',
        ],
        'siape' => [
            'label' => 'SIAPE',
            'description' => 'Servidores públicos federais com matrícula SIAPE.',
            'badge_classes' => 'border-violet-500/30 bg-violet-500/10 text-violet-600 dark:text-violet-400',
        ],
    ],

    'followup' => [
        // Delay mínimo (em minutos) após qualificação para enviar o primeiro follow-up
        'first_delay_minutes' => env('FOLLOWUP_FIRST_DELAY_MINUTES', 10),
        // Horário diário para follow-ups subsequentes (HH:MM, fuso São Paulo)
        'daily_time' => env('FOLLOWUP_DAILY_TIME', '10:00'),
        // Número máximo de follow-ups por lead (sincronizado com AppSetting::defaults)
        'max_count' => env('FOLLOWUP_MAX_COUNT', 4),
        // Não enviar follow-up se o cliente enviou mensagem nos últimos N minutos (usa last_inbound_at)
        'skip_if_recent_inbound_minutes' => (int) env('FOLLOWUP_SKIP_IF_RECENT_INBOUND_MINUTES', 30),
    ],

    'circuit_breaker' => [
        'consultas_falhas_threshold' => (int) env('TENAZ_CIRCUIT_BREAKER_THRESHOLD', env('CREDFLOW_CIRCUIT_BREAKER_THRESHOLD', env('ARIA_CIRCUIT_BREAKER_THRESHOLD', 5))),
        'window_minutes' => (int) env('TENAZ_CIRCUIT_BREAKER_WINDOW', env('CREDFLOW_CIRCUIT_BREAKER_WINDOW', env('ARIA_CIRCUIT_BREAKER_WINDOW', 5))),
    ],

    'campaigns' => [
        // Maximum WhatsApp template sends per minute per instance. Meta tiers start at
        // 250/day (≈ unlimited under our throttle) and scale to 100k/day; tune accordingly.
        'rate_per_minute' => (int) env('TENAZ_CAMPAIGN_RATE_PER_MINUTE', env('CREDFLOW_CAMPAIGN_RATE_PER_MINUTE', 80)),
        // Backoff (seconds) when Meta returns a rate-limit error before retrying the message.
        'rate_limit_release_seconds' => (int) env('TENAZ_CAMPAIGN_RATE_LIMIT_RELEASE', env('CREDFLOW_CAMPAIGN_RATE_LIMIT_RELEASE', 60)),
    ],

    'api' => [
        // Rate limit: requisições por minuto por IP (webhook + /credflow)
        'rate_limit_per_minute' => (int) env('TENAZ_API_RATE_LIMIT', env('CREDFLOW_API_RATE_LIMIT', env('ARIA_API_RATE_LIMIT', 120))),
        // Rate limit: mensagens por telefone por hora (webhook pre-dispatch)
        'rate_limit_per_phone' => (int) env('TENAZ_RATE_LIMIT_PER_PHONE', env('CREDFLOW_RATE_LIMIT_PER_PHONE', env('ARIA_RATE_LIMIT_PER_PHONE', 30))),
    ],

    /*
    |--------------------------------------------------------------------------
    | Build / Deployed Version Introspection
    |--------------------------------------------------------------------------
    |
    | Useful when you don't know what version is currently deployed on a VPS.
    | Prefer setting these at deploy time:
    | - APP_BUILD_SHA (git commit hash)
    | - APP_BUILD_TAG (optional tag/release name)
    | - APP_BUILD_TIME (ISO8601 timestamp, optional)
    |
    | The /__version endpoint can be protected via APP_VERSION_TOKEN.
    */
    'build' => [
        'sha' => env('APP_BUILD_SHA'),
        'tag' => env('APP_BUILD_TAG'),
        'time' => env('APP_BUILD_TIME'),
    ],

    // If set, /__version requires header: X-Version-Token: <token> (or an authenticated user).
    'version_endpoint_token' => env('APP_VERSION_TOKEN'),

    // Emails allowed to access /horizon in non-local environments (comma-separated in .env)
    'admin_emails' => array_filter(array_map('trim', explode(',', env('TENAZ_ADMIN_EMAILS', env('CREDFLOW_ADMIN_EMAILS', env('ARIA_ADMIN_EMAILS', '')))))),

    // Cost per 1K tokens (USD) for AI usage aggregation
    'model_costs' => [
        'gpt-4o' => ['prompt' => 0.0025, 'completion' => 0.01],
        'gpt-4o-mini' => ['prompt' => 0.00015, 'completion' => 0.0006],
        'claude-sonnet-4-20250514' => ['prompt' => 0.003, 'completion' => 0.015],
        'claude-haiku-4-5' => ['prompt' => 0.0008, 'completion' => 0.004],
        'claude-haiku-3-5' => ['prompt' => 0.0008, 'completion' => 0.004],
        'claude-haiku-3-20240307' => ['prompt' => 0.00025, 'completion' => 0.00125],
    ],

    // Daily cost alert threshold (USD) — triggers AlertService when exceeded
    'daily_cost_alert_threshold' => (float) env('TENAZ_DAILY_COST_ALERT_USD', env('CREDFLOW_DAILY_COST_ALERT_USD', env('ARIA_DAILY_COST_ALERT_USD', 10))),
];
