# Aria — Technical Feature Specifications

**Version:** 1.0  
**Date:** 2026-05-25  
**Purpose:** Behavioral contracts, data shapes, integration specs, and edge-case rules for all Aria features. Complements PRD (what/why) and ARCHITECTURE.md (how it's built).

> **For AI coding agents:** This is the source of truth for feature behavior. When implementing or modifying a feature, check the relevant section here first. If a spec is missing, document the decision in this file before writing code.

---

## Table of Contents

1. [AI Agent Pipeline](#1-ai-agent-pipeline)
2. [Lead Lifecycle](#2-lead-lifecycle)
3. [Status Machine](#3-status-machine)
4. [WhatsApp Messaging](#4-whatsapp-messaging)
5. [Campaigns](#5-campaigns)
6. [Follow-Up System](#6-follow-up-system)
7. [Service Tickets](#7-service-tickets)
8. [Tags & Auto-Tagging](#8-tags--auto-tagging)
9. [Smart Lists](#9-smart-lists)
10. [Multi-Tenancy & RBAC](#10-multi-tenancy--rbac)
11. [Real-Time Events](#11-real-time-events)
12. [Laboratory & Observability](#12-laboratory--observability)
13. [API Contracts](#13-api-contracts)
14. [Configuration Reference](#14-configuration-reference)
15. [Error Handling & Recovery](#15-error-handling--recovery)

---

## 1. AI Agent Pipeline

### 1.1 Trigger Conditions

AI automation runs when ALL of the following are true:
- `Lead.agent_id` is not null
- `effective_ai_mode` resolves to `automatic` or `qualify_then_handoff`
- `Lead.ai_paused_until` is null or in the past
- The inbound message is not a delivery/read receipt (no content)

### 1.2 Mode Resolution (effective_ai_mode)

Resolution order (first non-null wins):
1. `Lead.ai_mode` (per-lead override)
2. `WhatsappInstance.default_ai_mode` (instance default)
3. Fallback: `automatic`

Special case: if `Lead.agent_id === null`, always returns `manual` regardless of other values.

### 1.3 Agent Config Resolution

Per-lead config resolved by `AgentConfigResolver` in this order:
1. `AgentConfig` record linked to `Lead.agent_id`
2. `AppSetting` key-value store for `Lead.tenant_id`
3. Hardcoded defaults from `config/credflow.php`

| Config key | Default | Description |
|------------|---------|-------------|
| `agent.temperature` | 0.4 | LLM temperature |
| `agent.max_tokens` | 1024 | Max tokens per response |
| `agent.max_steps` | 10 | Max tool calls per turn |
| `agent.max_total_steps` | 12 | Hard ceiling across retries |
| `agent.timeout_seconds` | 45 | Per-turn timeout |
| `agent.max_conversation_messages` | 24 | Sliding window size |

### 1.4 Pipeline Steps (per inbound message)

```
1. ProcessIncomingWhatsAppMessageJob dispatched
   WithoutOverlapping: key = "{tenantId}:{phone}"
   tries: 3, backoff: [10s, 30s, 60s]

2. Phase 1 — CRM Persistence (IncomingConversationPersister)
   a. Upsert Lead (find by phone + instance + tenant)
   b. Write ConversationTimelineMessage (role=user)
   c. Sync Contact from Lead data
   d. Broadcast NewConversationMessage event

3. Phase 2 — Automation (ConversationAutomationService)
   a. Resolve effective_ai_mode
   b. shouldAutoRespond() check
   c. If human: update operational_stage, broadcast, return
   d. If AI: AgentService::process()

4. AgentService::process()
   a. Generate interaction_id UUID
   b. Resolve AgentConfig
   c. ConversationContextSynchronizer::sync() — mirror timeline → agent_conversation_messages
   d. AgentFactory::make(niche, mode) → concrete Agent class
   e. Agent::handle() — LLM turn loop with middleware stack
   f. FactCheckService::validate() — guardrail
   g. AgentInteractionEventService::append() — audit
   h. ConversationTimelineService::write() — persist reply
   i. WhatsappOutboxService::queue() — enqueue for dispatch
```

### 1.5 Fact-Check Guardrail

After AI generates a response:
- `FactCheckService` checks that any credit figures mentioned (margin, installment values) match the `Lead.credito_json` data
- On mismatch: AI response is modified or blocked before sending; event logged
- On pass: response proceeds normally

### 1.6 Interaction ID Threading

Every pipeline entry generates a UUID via `AgentInteractionEventService::newInteractionId()`. This ID must be passed to:
- All `AgentInteractionEventService::recordForLead()` calls
- All service calls that accept `$interactionId`
- `LogAiUsageJob` dispatch

### 1.7 Tool Calling

- Max tool calls per turn: `config('credflow.agent.max_steps')` (default 10)
- Hard ceiling: `config('credflow.agent.max_total_steps')` (default 12)
- `ToolCallTracker` is request-scoped (container `scoped` binding) — tracks count within one HTTP request / one job handle
- `ToolCallGuardMiddleware` throws `ToolCallCeilingExceededException` if ceiling exceeded
- `AuditLogMiddleware` records each tool call to `agent_interaction_events`

---

## 2. Lead Lifecycle

### 2.1 Lead Creation

Leads are created automatically when an inbound WhatsApp message arrives for an unknown phone number on a known instance. Manual creation is also possible from the UI.

**Upsert key:** `(tenant_id, evolution_instance, whatsapp)` — one lead per phone number per WhatsApp instance. Note: `evolution_instance` is a legacy column name; it stores the Meta Cloud instance name string for all leads.

**Initial state:**
- `status`: `novo` (or tenant's configured initial status)
- `operational_stage`: `new`
- `ai_mode`: null (inherits from instance)
- `followup_status`: `inactive`

### 2.2 Operational Stages

`operational_stage` tracks human-vs-AI activity state. Transitions:

| From | To | Trigger |
|------|----|---------|
| any | `ai_active` | AI responds to inbound |
| any | `human_active` | Human sends message; AI paused; escalation |
| any | `waiting_human` | AI escalated, no human responded yet |
| any | `closed` | Lead marked sem_credito or manually closed |

### 2.3 AI Pause

AI can be paused per-lead:
- **Automatic:** when human operator sends a message (`automationService.pauseForHuman()`)
- **Manual:** operator clicks "Pause AI" button
- **Post-status-move:** Pipeline Kanban move sets `ai_paused_until = now() + 24h`

**Pause storage:** dual — `Lead.ai_paused_until` (DB, survives restarts) + Redis key `pause:{tenantId}:{phone}` (fast check). Both must be cleared on resume.

**Auto-resume conditions:**
- `ai_paused_until` expires
- Operator clicks "Resume AI"

### 2.4 Contact Sync

`ContactSyncService` mirrors key Lead fields back to the canonical `Contact` record after each AI turn. Ensures Contact table stays up-to-date with latest data extracted by AI tools.

### 2.5 Custom Fields

- Defined per tenant as `CustomField` records (entity_type='lead')
- Values stored in `CustomFieldValue` (EAV: lead_id + custom_field_id + value)
- AI tools can write custom field values via `RegisterDataTool`
- UI renders custom fields in the conversation panel

---

## 3. Status Machine

### 3.1 Structure

Per-tenant `StatusMachine` record stores:
```json
{
  "statuses": [
    {"slug": "novo", "label": "Novo", "color": "#..."},
    ...
  ],
  "transitions": {
    "novo": ["em_atendimento", "sem_credito"],
    ...
  }
}
```

### 3.2 Canonical AI Slugs (Protected)

These 7 slugs are hardcoded into AI tools and must exist in every tenant's status machine:

| Slug | Meaning |
|------|---------|
| `novo` | New lead, not yet contacted |
| `em_atendimento` | Active conversation |
| `aguardando_retorno` | Waiting for lead response |
| `proposta_enviada` | Offer made to lead |
| `fechado` | Deal closed |
| `sem_credito` | No credit margin available |
| `perdido` | Lead lost/unresponsive |

**Never rename or remove canonical slugs.** AI tools reference them as string constants via `StatusMachine::CANONICAL_SLUGS`.

### 3.3 Pipeline Kanban

- `sem_credito` is hidden from Kanban by default (`HIDDEN_BOARD_STATUS_SLUGS` constant)
- Dragging a card to a new status sets `ai_paused_until = now() + 24h` and `ai_paused_reason = 'manual_status_override'`
- Counts pre-fetched in one `GROUP BY` query; cards fetched per-column with `cursorPaginate(30)`

---

## 4. WhatsApp Messaging

### 4.1 Outbox Pattern

All outbound messages go through `WhatsappOutboxService`:
1. Record created in `whatsapp_outbox_messages`
2. `ProcessWhatsappOutboxMessageJob` dispatched
3. Provider-specific client sends message
4. Delivery status updated via callback webhook

This guarantees ordering and enables retry without duplicate sends.

### 4.2 Message Debouncing

Inbound messages are debounced before triggering AI:
- Window: `config('credflow.debounce_seconds')` (default 3, env `DEBOUNCE_SECONDS`)
- Mechanism: `DebounceService` uses Redis TTL; new message within window resets timer
- Purpose: aggregate rapid bursts (e.g., user sends "oi" then "preciso de ajuda") into single AI turn

### 4.3 24h Service Window

Meta WhatsApp policy: business can only send free-form messages within 24h of last customer message.

- `FollowUpWindowService::isInsideWindow($lead)` checks `Lead.last_inbound_at`
- Outside window: only approved templates can be sent
- Follow-up system respects this window before scheduling

### 4.4 Provider

Meta Cloud API is the sole WhatsApp provider. `WhatsAppProviderFactory` always resolves `MetaCloudProvider` / `MetaCloudInstanceManager`. `WhatsappInstance.provider` will always be `WhatsAppProvider::MetaCloud`.

### 4.5 Media Handling

- Inbound media (image, audio, video, document) dispatches `DownloadIncomingMediaJob` for deferred download
- `MediaUnderstandingService` routes to transcription (audio → STT) or vision (image → description)
- Media context attached to AI turn as `MediaContext` DTO

---

## 5. Campaigns

### 5.1 Lifecycle States

```
draft → scheduled → sending → paused → finished
              ↑____________↓ (resume)
```

- `draft`: created, not started
- `scheduled`: start time set, waiting for scheduler
- `sending`: actively dispatching messages
- `paused`: manually paused or auto-paused on error threshold
- `finished`: all messages sent

Transitions validated in service layer; controller calls service, not model directly.

### 5.2 WhatsApp Template Campaign

- Requires `WhatsappTemplate.status = 'APPROVED'` before starting
- Meta Cloud API only
- `template_params_mapping`: maps template variable slots to lead fields
- Rate cap: `config('credflow.campaigns.rate_per_minute')` (default 80 messages/min)
- Error threshold: campaign auto-pauses if error % exceeds `error_threshold_percent`

### 5.3 Voice IVR Campaign

- Twilio outbound calls
- DTMF scripting via `VoiceCampaignCall` records
- Post-call WhatsApp follow-up via `SendPostCallWhatsAppJob`
- IVR callbacks: `/api/ivr/call/{call}/script`, `/dtmf`, `/status`

### 5.4 Contact List as Audience

All campaigns target a `ContactList`:
- Static lists: manually curated `ContactListEntry` records
- Smart lists: resolved at dispatch time from filter predicates (see §9)
- Resolution cap: `ARIA_SMART_LIST_MAX_RESOLVE` (default 100,000)

---

## 6. Follow-Up System

### 6.1 Trigger Conditions

`CheckFollowUpsCommand` runs every 5 minutes. A lead enters follow-up queue when:
- `followup_status = 'active'`
- `last_inbound_at` is older than first follow-up delay (default: configurable)
- Lead has not been marked `sem_credito` or `fechado`
- Max follow-up attempts not yet reached

### 6.2 Configuration

Resolved per-lead by `FollowUpSettingsResolver`:
1. Per-agent `AgentConfig` settings
2. Per-tenant `AppSetting`
3. `config/credflow.php` defaults:

| Setting | Default | Description |
|---------|---------|-------------|
| `followup.first_delay_hours` | Configurable | Hours after last inbound before first follow-up |
| `followup.daily_send_time` | Configurable | Time of day for follow-up attempts |
| `followup.max_attempts` | Configurable | Max follow-up messages before giving up |
| `followup.zombie_cutoff_days` | 14 | Days of silence before zombie status |

### 6.3 24h Window

Follow-ups that would violate the Meta 24h service window use approved WhatsApp templates instead of free-form messages. Template selection is configurable per agent.

### 6.4 Follow-Up Record

Each sent follow-up creates a `FollowupMessage` record with:
- `attempt` number
- `message_text`
- `tone` (varies per attempt for naturalness)
- `sent_at`

History visible in conversation UI (last 10 shown).

---

## 7. Service Tickets

### 7.1 Creation Triggers

A `ServiceTicket` is created when:
- AI calls `EscalarParaHumanoTool` (human escalation)
- AI calls `RegistrarLeadSemCreditoTool` (no credit margin)
- Operator manually escalates from conversation UI

### 7.2 SLA Tracking

- `opened_at` — ticket creation timestamp
- `sla_due_at` — configurable SLA deadline (set on creation)
- `first_human_response_at` — when first human message sent after escalation
- `closed_at` — resolution timestamp

`ServiceTicketLifecycleService::markHumanResponse()` called on every outbound human message in an escalated lead.

### 7.3 Assignment

- Tickets inherit `Lead.assigned_user_id`
- Tickets panel (`atendimentos`) shows all open tickets for the tenant
- Filtered by assignment, status, SLA breach

### 7.4 Reopening

Closing a ticket does not prevent a new one from being created if AI re-escalates the same lead later.

---

## 8. Tags & Auto-Tagging

### 8.1 Tag Sources

Tags on leads/contacts have a `source` field:
- `manual` — added by human operator
- `ai` — added by `LeadAutoTaggingService`

**`HasTags::syncAiTags()` only touches `source='ai'` pivots.** Manual tags are never modified by AI operations.

### 8.2 Auto-Tagging Pipeline

`TagLeadFromConversationJob` dispatches after each AI turn (configurable delay):
1. `LeadAutoTaggingService` calls `LeadSignalExtractorAgent` (LLM)
2. LLM returns suggested tag slugs + evidence strings
3. `AutoTagEvidenceSanitizer` cleans evidence
4. `syncAiTags()` replaces all `source='ai'` pivots with new set
5. Confidence + evidence stored in pivot table

### 8.3 Hot Tags

Tags have `is_hot` boolean. Hot tags displayed prominently in UI (inbox list, pipeline cards). Used for urgent signal flags (e.g., "ready_to_close").

### 8.4 Tag Limits

`TagLimitReachedException` thrown if tenant exceeds tag count limit. Handle in controllers — return 422 with user-friendly message.

---

## 9. Smart Lists

### 9.1 Filter Predicates

Smart lists store filter predicates as JSON. Supported filter types:

| Filter | Operators | Example |
|--------|-----------|---------|
| Status | is, is_not | `status = 'em_atendimento'` |
| Tag | has, not_has | `has tag 'ready_to_close'` |
| Custom field | eq, contains, gt, lt | `idade > 60` |
| Date | after, before, between | `created_at after 2026-01-01` |
| Assigned user | is, is_not, unassigned | `assigned = null` |
| Follow-up status | is | `followup_status = 'active'` |

### 9.2 Resolution

`ContactList::resolve()` (or equivalent) materializes the smart list at query time. Resolution cap: `ARIA_SMART_LIST_MAX_RESOLVE` (env `ARIA_SMART_LIST_MAX_RESOLVE`, default 100,000). Above cap: truncated with warning.

### 9.3 Campaign Dispatch

At campaign dispatch time, smart lists are resolved fresh. This means the audience reflects list state at dispatch moment, not at campaign creation.

---

## 10. Multi-Tenancy & RBAC

### 10.1 Tenant Isolation

**Every model that stores tenant data MUST use the `BelongsToTenant` trait.** The trait adds a global Eloquent scope `where('tenant_id', auth()->user()->tenantId)`.

**Scope is OFF in console/queue contexts.** Jobs must explicitly scope: `->where('tenant_id', $this->tenantId)`.

### 10.2 Role Capabilities

| Action | Owner | Administrator | User |
|--------|-------|--------------|------|
| All agent config | ✅ | ✅ | ❌ |
| All campaigns | ✅ | ✅ | ❌ |
| All contact lists | ✅ | ✅ | ❌ |
| Pipeline config | ✅ | ✅ | ❌ |
| View all leads | ✅ | ✅ | ❌ |
| View own agent leads | ✅ | ✅ | ✅ |
| View assigned leads | ✅ | ✅ | ✅ |
| View unassigned leads | ✅ | ✅ | ✅ |
| Send messages | ✅ | ✅ | ✅ |
| Team management | ✅ | ✅ | ❌ |
| Settings | ✅ | ✅ | Profile only |

### 10.3 Invitation Flow

1. Owner/Admin creates invitation → `TenantInvitation` with hashed token + role + expiry
2. Email sent to invitee
3. Invitee visits `/invite/{token}` → creates account (or logs in) → attached to tenant with specified role
4. Invitation marked used; expired invitations rejected

### 10.4 Active Tenant Selection

Users can belong to multiple tenants. Active tenant stored in session (`active_tenant_id`). `User::tenantId` accessor reads session → falls back to first tenant. UI should allow switching tenants without re-login.

---

## 11. Real-Time Events

### 11.1 Broadcast Channels

| Channel | Auth | Events |
|---------|------|--------|
| `private-dashboard.{tenantId}` | Tenant member | `DashboardMetricsUpdated` |
| `private-conversation.{tenantId}.{leadId}` | Can view lead | `NewConversationMessage`, `ConversationUpdated` |
| `private-tenant.{tenantId}` | Tenant member | General notifications |

### 11.2 Debounce Rules

High-frequency events must use `BroadcastDebouncer`:
- `DashboardMetricsUpdated`: debounced, triggered after metric changes settle
- `ConversationUpdated`: not debounced (state change events need immediacy)
- `NewConversationMessage`: not debounced (messages must appear in order)

### 11.3 Frontend Subscription Pattern

```ts
window.Echo.private(`conversation.${tenantId}.${leadId}`)
  .listen('.NewConversationMessage', (event) => {
    // append to messages array
  })
```

Event class name in listener: `.ClassName` (note leading dot — Laravel Echo convention).

---

## 12. Laboratory & Observability

### 12.1 AI Usage Tracking

- `LogAiUsageJob` dispatched after each AI turn
- Aggregated into `ai_usage_dailies` (tenant + model + date + token counts + cost USD)
- `credflow:aggregate-usage` command runs daily 01:00 for rollup
- `config('credflow.model_costs')` maps model identifiers to USD cost per token
- Daily cost alert threshold: `config('credflow.daily_cost_alert_threshold')` (default $10 USD)

### 12.2 Health Monitoring

`LaboratoryHealthCheckCommand` runs every 15 minutes. Checks:
- Queue worker connectivity
- Redis connectivity
- WhatsApp instance connection status
- LLM API reachability
- Database query time

Results stored and surfaced in Laboratory Health dashboard.

### 12.3 Stress Tests

`RunStressTestJob` runs configurable load simulation:
- Sends synthetic inbound messages through full pipeline
- Measures: latency, tool call count, token usage, error rate
- Results stored per `StressTest` record with run history

### 12.4 Langfuse Integration

- `LangfuseService` wraps LLM calls with trace IDs
- `FlushLangfuse` middleware flushes buffered traces after each request
- Traces include: model, prompt, completion, tool calls, latency, cost
- Scoring can be added from Langfuse UI for prompt quality feedback loops

---

## 13. API Contracts

### 13.1 Inbound WhatsApp Webhook (Meta Cloud)

```
GET  /api/webhooks/meta  — verification challenge (hub.mode, hub.challenge, hub.verify_token)
POST /api/webhooks/meta  — event payload
Auth: HMAC-SHA256 signature in X-Hub-Signature-256 header
Response: 200 OK
```

### 13.2 Direct Agent API

```
POST /api/aria
Auth: Bearer token (config('services.credflow.api_key'))
Content-Type: application/json

Body: {
  "phone": "+5511999998888",
  "message": "string",
  "tenant_id": "string",
  "instance_name": "string"
}

Response: {
  "reply": "string",
  "interaction_id": "uuid",
  "tool_calls": ["tool_name", ...]
}
```

Used for testing and n8n integrations.

### 13.3 URA Inbound Lead

```
POST /api/ura/inbound-lead
Auth: X-URA-API-Key header
Throttle: ura-inbound rate limiter

Body: {
  "phone": "+55...",
  "nome": "string",
  "cpf": "string",
  "agent_id": integer
}

Response: 200 {"status": "queued"} or 401/422
```

### 13.4 URA → Meta Cloud Template Flow

When a URA-sourced lead is created via `POST /api/ura/inbound-lead`, the system dispatches `SendInboundLeadWhatsAppJob` which sends a WhatsApp template message via Meta Cloud API. The template is configured on the `UraApiKey` record (`whatsapp_template_id`). This is the primary connection between URA and WhatsApp.

### 13.5 IVR Callbacks (Twilio)

```
POST /api/ivr/call/{call}/script   — returns TwiML script
POST /api/ivr/call/{call}/dtmf     — handles digit input
POST /api/ivr/call/{call}/status   — call status updates
Auth: ValidateTwilioSignature middleware (HMAC)
```

---

## 14. Configuration Reference

### 14.1 Key Environment Variables

| Variable | Default | Purpose |
|----------|---------|---------|
| `QUEUE_CONNECTION` | `database` | `redis` in production |
| `DEBOUNCE_SECONDS` | `3` | Message aggregation window |
| `CREDFLOW_AGENT_MAX_MESSAGES` | `24` | AI sliding window size |
| `ARIA_SMART_LIST_MAX_RESOLVE` | `100000` | Smart list resolution cap |
| `OPENROUTER_API_KEY` | — | OpenRouter LLM key |
| `OPENAI_API_KEY` | — | OpenAI direct key |
| `REVERB_HOST` / `_PORT` | — | WebSocket server |
| `LANGFUSE_PUBLIC_KEY` | — | LLM observability |
| `APP_BUILD_SHA` | — | Deployment version |

### 14.2 config/credflow.php Key Values

```php
'agent' => [
    'temperature'           => 0.4,
    'max_tokens'            => 1024,
    'max_steps'             => 10,
    'max_total_steps'       => 12,
    'timeout_seconds'       => 45,
    'max_conversation_messages' => 24,
],
'agents' => [
    'inss.receptivo' => App\Ai\Agents\InssReceptivoAgent::class,
    'inss.bulk'      => App\Ai\Agents\InssBulkAgent::class,
    'inss.followup'  => App\Ai\Agents\InssFollowupAgent::class,
    'siape.receptivo' => App\Ai\Agents\SiapeAgent::class,
],
'campaigns' => [
    'rate_per_minute' => 80,
],
'followup' => [
    // timing settings per AppSetting overrides
],
'debounce_seconds' => 3,
```

### 14.3 Adding a New Niche

1. Create agent class in `app/Ai/Agents/` extending `BaseCustomerServiceAgent`
2. Add entry to `config/credflow.php` under `agents`: `'niche.mode' => AgentClass::class`
3. Add niche template in `config/agent_templates.php`
4. No code changes required in routing or factory — `AgentFactory` reads the config map

---

## 15. Error Handling & Recovery

### 15.1 AI Pipeline Failures

**Transient failures** (LLM timeout, API rate limit, network error):
1. `InteractionRecoveryService::record()` writes to `failed_interactions` table
2. `RetryFailedInteractionJob` replays every 5 minutes
3. Max retries: configurable per failure type
4. On final failure: `agent_interaction_events` record with `severity=error`; human notified via dashboard

**Hard failures** (invalid tool call, schema mismatch):
1. Exception caught in `AgentService::process()` finally block
2. `AgentInteractionContext::clear()` called
3. Error event logged
4. Lead `operational_stage` set to `human_active` as fallback

### 15.2 WhatsApp Delivery Failures

- Failed `WhatsappOutboxMessage` has `status = 'failed'` + `error_detail`
- Not auto-retried (to avoid duplicate messages)
- Visible in conversation timeline as undelivered indicator

### 15.3 Circuit Breaker (Credit API)

`config/credflow.php`:
```php
'circuit_breaker' => [
    'consultas_falhas_threshold' => 5,  // failures before open
    'window_minutes'             => 5,  // observation window
]
```

When circuit is open: AI tool returns cached/stale data or "unable to consult at this time" — AI handles gracefully in conversation.

### 15.4 Campaign Error Threshold

Campaign auto-pauses when:
```
(failed_messages / total_sent) * 100 >= error_threshold_percent
```

Default `error_threshold_percent`: configurable per campaign (UI default: 10%).

### 15.5 Job Failure Policy

| Job | Retry strategy | On final failure |
|-----|---------------|-----------------|
| `ProcessIncomingWhatsAppMessageJob` | 3 tries, [10s, 30s, 60s] | `FailedInteraction` record |
| `SendWhatsAppMessageJob` | Default (3 tries) | Outbox message marked failed |
| `DispatchCampaignJob` | Default | Campaign auto-paused |
| `LogAiUsageJob` | Default | Usage gap (non-critical) |
| `RunStressTestJob` | No retry | Test marked failed |
