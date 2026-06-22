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
        // Allow-list of model identifiers selectable from the Playground (F8). Mirrors the
        // frontend MODEL_CATALOG dropdown — any model_override / tester_model posted to the
        // LLM-invoking playground endpoints must be one of these (cost-abuse / provider-injection guard).
        'playground_models' => [
            'gpt-5.4-pro', 'gpt-5.4', 'o4-mini', 'gpt-4o', 'gpt-4o-mini',
            'anthropic/claude-sonnet-4-6', 'anthropic/claude-haiku-4-5',
            'google/gemini-2.5-flash', 'google/gemini-3-flash',
            'deepseek/deepseek-v3.2',
            'moonshotai/kimi-k2', 'moonshotai/kimi-k2.5',
        ],
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
        // Debounce window (seconds) for the per-failure auto-pause check (SCALE-1). Under a
        // failure storm every concurrent send worker used to take an exclusive row lock on the
        // one campaign row; this gate lets only the first caller per window reach the locked
        // evaluation, collapsing the convoy. MonitorCampaignsCommand is the backstop. 0 disables.
        'autopause_debounce_seconds' => (int) env('TENAZ_CAMPAIGN_AUTOPAUSE_DEBOUNCE', env('CREDFLOW_CAMPAIGN_AUTOPAUSE_DEBOUNCE', 3)),
        // Maximum campaign sends per minute per TENANT (SCALE-2 fairness gate). The `campaigns`
        // queue is a single FIFO shared by all tenants; without a per-tenant cap one large tenant's
        // fan-out monopolizes the workers and starves smaller tenants. Over-budget sends release
        // back to the queue tail (same mechanism as rate_per_minute above). 0 = disabled (default;
        // the per-instance rate_per_minute already bounds single-instance tenants). Set to a small
        // multiple of rate_per_minute to bound multi-instance mega-tenants while leaving headroom.
        'tenant_rate_per_minute' => (int) env('TENAZ_CAMPAIGN_TENANT_RATE_PER_MINUTE', env('CREDFLOW_CAMPAIGN_TENANT_RATE_PER_MINUTE', 0)),
        // Time-based retry budget (seconds) for SendCampaignMessageJob — see its retryUntil(). Each
        // fairness/throttle release() re-pops the job and counts as an attempt, so a pure attempt-count
        // `tries` would fail messages that merely waited behind the per-tenant fairness gate. This window
        // makes the worker ignore the attempt count while inside it (genuine errors are still capped by
        // the job's maxExceptions). Must outlast a large tenant's gated drain. Default 6h. 0 disables the
        // time budget and reverts to the plain attempt-count tries behaviour.
        'send_retry_window_seconds' => (int) env('TENAZ_CAMPAIGN_SEND_RETRY_WINDOW', env('CREDFLOW_CAMPAIGN_SEND_RETRY_WINDOW', 21600)),
        // Cache TTL (seconds) for a campaign's immutable WhatsApp instance + template, resolved once
        // per campaign_id instead of re-read on every fan-out message (SCALE-4). The live campaign
        // status is still queried per message; only the immutable config is cached. A token refresh or
        // a template-status change is picked up within this window. Default 300 (matches the project's
        // config-cache convention). 0 disables the cache and resolves fresh on every message.
        'send_config_cache_seconds' => (int) env('TENAZ_CAMPAIGN_SEND_CONFIG_CACHE', env('CREDFLOW_CAMPAIGN_SEND_CONFIG_CACHE', 300)),
    ],

    'api' => [
        // Rate limit: requisições por minuto por IP (webhook + /credflow)
        'rate_limit_per_minute' => (int) env('TENAZ_API_RATE_LIMIT', env('CREDFLOW_API_RATE_LIMIT', env('ARIA_API_RATE_LIMIT', 120))),
        // Rate limit: mensagens por telefone por hora (webhook pre-dispatch)
        'rate_limit_per_phone' => (int) env('TENAZ_RATE_LIMIT_PER_PHONE', env('CREDFLOW_RATE_LIMIT_PER_PHONE', env('ARIA_RATE_LIMIT_PER_PHONE', 30))),
        // Debounce last_used_at writes for DB-backed URA API keys. 0 disables the debounce.
        'ura_key_last_used_debounce_seconds' => (int) env('TENAZ_URA_KEY_LAST_USED_DEBOUNCE_SECONDS', env('CREDFLOW_URA_KEY_LAST_USED_DEBOUNCE_SECONDS', 300)),
    ],

    'jobs' => [
        // Per-job dispatch jitter (seconds) for the every-5-minute cron fan-out commands
        // (CheckFollowUpsCommand, ProcessPendingRetriesCommand). Both dispatch a whole batch in
        // one tight loop, so without jitter every job lands on the queue at the same instant and
        // the worker pool (and downstream provider) sees a herd at each minute boundary (SCALE-10).
        // Each dispatched job is delayed a uniform random 0..N seconds to spread the batch across
        // the window. Default 240 (kept under the 300s cron interval so a batch drains before the
        // next run). 0 disables the jitter (dispatch immediately). The sync queue ignores delays.
        'cron_dispatch_jitter_seconds' => (int) env('TENAZ_CRON_DISPATCH_JITTER_SECONDS', env('CREDFLOW_CRON_DISPATCH_JITTER_SECONDS', 240)),
        // Time-based retry budgets for jobs that intentionally release() under locks, schedules, or provider throttles.
        'auto_tag_retry_window_seconds' => (int) env('TENAZ_AUTO_TAG_RETRY_WINDOW_SECONDS', env('CREDFLOW_AUTO_TAG_RETRY_WINDOW_SECONDS', 1800)),
        'incoming_message_retry_window_seconds' => (int) env('TENAZ_INCOMING_MESSAGE_RETRY_WINDOW_SECONDS', env('CREDFLOW_INCOMING_MESSAGE_RETRY_WINDOW_SECONDS', 1800)),
        'outbox_retry_window_seconds' => (int) env('TENAZ_OUTBOX_RETRY_WINDOW_SECONDS', env('CREDFLOW_OUTBOX_RETRY_WINDOW_SECONDS', 21600)),
        'template_sync_retry_window_seconds' => (int) env('TENAZ_TEMPLATE_SYNC_RETRY_WINDOW_SECONDS', env('CREDFLOW_TEMPLATE_SYNC_RETRY_WINDOW_SECONDS', 3600)),
        'template_sync_max_retry_after_seconds' => (int) env('TENAZ_TEMPLATE_SYNC_MAX_RETRY_AFTER_SECONDS', env('CREDFLOW_TEMPLATE_SYNC_MAX_RETRY_AFTER_SECONDS', 3600)),
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
