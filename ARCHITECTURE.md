# Aria — Architecture Decision Record

**Status:** Living Document  
**Last Updated:** 2026-05-27  
**Stack:** Laravel 12 · Inertia v2 · Vue 3 · Tailwind v4 · Pest 4  

> **For AI coding agents:** Read this before writing code. Every section has explicit RULES at the bottom. Follow them exactly — do not invent patterns not listed here.

---

## Table of Contents

1. [System Overview](#1-system-overview)
2. [Domain Model](#2-domain-model)
3. [Multi-Tenancy](#3-multi-tenancy)
4. [AI Agent Pipeline](#4-ai-agent-pipeline)
5. [HTTP Layer](#5-http-layer)
6. [Frontend Architecture](#6-frontend-architecture)
7. [Authentication & Authorization](#7-authentication--authorization)
8. [Queue & Background Processing](#8-queue--background-processing)
9. [Real-Time (Reverb/Echo)](#9-real-time-reverbreverb)
10. [Human Handoff Pipeline](#10-human-handoff-pipeline)
11. [Configuration](#11-configuration)
12. [Known Issues & Anti-Patterns](#12-known-issues--anti-patterns)
13. [Coding Rules Summary](#13-coding-rules-summary)

---

## 1. System Overview

Aria is a **multi-tenant AI SDR SaaS** for the Brazilian credit/loan market. The core loop:

```
External WhatsApp message
  → Meta Cloud API webhook (POST /api/webhook/*)
  → ProcessIncomingWhatsAppMessageJob (queue: messages)
    → Phase 1: Persist CRM state (lead upsert, timeline record)
    → Phase 2: ConversationAutomationService resolves AI mode
      → if automation ON: AgentService::process() → LLM → Tools → reply
      → if human: notify operators via Reverb broadcast
```

**Primary external integrations:**
- **Meta Cloud API** — official WhatsApp Business API
- **OpenRouter / OpenAI** — LLM calls
- **Promosys / INSS / SIAPE** — Brazilian credit bureau APIs
- **Twilio** — voice/IVR campaigns
- **Langfuse** — LLM observability
- **Reverb** — first-party WebSocket server (Laravel Reverb v1)

**Language note:** Route names, URLs, and domain terms use Brazilian Portuguese (e.g., `conversas`, `agentes`, `campanhas`). Code identifiers use English. Never mix them.

---

## 2. Domain Model

### Core Hierarchy

```
Tenant
  └── User (pivot: TenantRole = Owner | Administrator | User)
        └── Agent (AI bot config)
              ├── AgentConfig (LLM settings)
              ├── WhatsappInstance (connected WA number)
              └── Lead (one conversation per contact per agent)
                    ├── Contact (canonical person record)
                    ├── ServiceTicket (human escalation / handoff)
                    ├── ConversationTimelineMessage (message log)
                    ├── AgentInteractionEvent (AI audit trail)
                    ├── FollowupMessage (scheduled follow-ups)
                    └── CustomFieldValue (EAV extension)
```

### Key Model Relationships

| Model | Critical Relationships |
|-------|----------------------|
| `Tenant` | `belongsToMany(User)` via pivot with `role` |
| `User` | `belongsToMany(Tenant)` with role pivot |
| `Agent` | `belongsTo(User)` · `hasOne(AgentConfig)` · `hasOne(WhatsappInstance)` · `hasMany(Lead)` |
| `Lead` | `belongsTo(Agent)` · `belongsTo(Contact)` · `belongsTo(User, 'assigned_user_id')` · `hasMany(ServiceTicket)` · `morphToMany(Tag)` |
| `Contact` | `hasMany(Lead)` · `hasMany(ContactListEntry)` |
| `Campaign` | `belongsTo(WhatsappInstance)` · `belongsTo(ContactList)` · `belongsTo(WhatsappTemplate)` |
| `StatusMachine` | Per-tenant JSON state machine (statuses + transitions arrays). Has `CANONICAL_SLUGS` constant — 7 protected AI-hardcoded status values |
| `ServiceTicket` | `belongsTo(Lead)` · `belongsTo(User, 'assigned_user_id')`. Status: `open` · `assigned` · `waiting_customer` · `waiting_internal` · `resolved` · `closed`. Type: `escalation` · `no_credit`. Resolution: `converted` · `lost` · `returned_to_ai` · `manual_keep` · `duplicate` · `no_response`. `ACTIVE_STATUSES` + `CLAIMABLE_STATUSES` constants. SLA on `sla_due_at`. Scope `activeEscalation($leadId)` — at most one active escalation ticket per lead (enforced as invariant in `HumanHandoffTransferService`). |

### Traits on Models

- `BelongsToTenant` — adds global Eloquent scope; **mandatory on every new tenant-scoped model**
- `SoftDeletes` — used on Agent, Lead, Contact, Campaign, ContactList, Tag, ServiceTicket
- `HasTags` — polymorphic tagging; `syncAiTags()` only touches `source='ai'` pivots, never manual tags

### RULES — Domain Model
- Always use relationship methods with return type hints: `public function lead(): BelongsTo`
- Never use `DB::` — use `Model::query()` or Eloquent relationships
- Every query on a tenant-scoped model must reach through the global scope or explicitly justify bypassing it with `->withoutGlobalScope('tenant')`
- Add `BelongsToTenant` and `SoftDeletes` to every new model that stores tenant data
- Use `CustomField` / `CustomFieldValue` EAV for Lead extensions — do not add ad-hoc columns to `leads`
- `StatusMachine::CANONICAL_SLUGS` are immutable — AI tools must use these exact strings

---

## 3. Multi-Tenancy

### How It Works

1. `User::tenantId` accessor → reads `session('active_tenant_id')` → falls back to first tenant
2. `BelongsToTenant` trait boots a global Eloquent scope: `where('tenant_id', auth()->user()->tenantId)`
3. Scope **skips automatically** when `app()->runningInConsole() && !app()->runningUnitTests()`
4. Queue workers run in console → **jobs do not get the global scope** — must filter explicitly

### Tenant ID Type History

`tenant_id` was historically the user's integer `id` (not a separate `Tenant` record). A migration `realign_legacy_tenant_id_string_keys` partially remediated this. The `BelongsToTenant` trait compares as `(string)`. When scoping manual queries, always use `(string) $tenantId`.

### RULES — Multi-Tenancy
- Never query tenant data in a job without explicitly scoping by `tenant_id` — the global scope is OFF in console
- Always compare `tenant_id` as string: `where('tenant_id', (string) $tenantId)`
- Pass `$tenantId` as a job constructor property when the job must work with tenant data
- Use `->withoutGlobalScope('tenant')` only when doing cross-tenant lookups (e.g., webhook ingest finding the correct instance)
- New tenant-scoped models: add `BelongsToTenant` trait before writing any queries

---

## 4. AI Agent Pipeline

### Architecture

```
AgentService::process($lead, $message)
  → AgentInteractionContext (request-scoped, sets interaction_id UUID)
  → AgentConfigResolver::resolve($lead) — merges AgentConfig + AppSetting + defaults
  → ConversationContextSynchronizer::sync() — mirrors timeline → agent_conversation_messages
  → AgentFactory::make($niche, $mode) — returns concrete Agent FQCN from credflow.agents config
  → Agent::handle() (extends BaseCustomerServiceAgent)
      Middleware stack applied:
        TokenBudgetMiddleware → ToolCallGuardMiddleware → AuditLogMiddleware
      → LLM turn loop (max steps from config)
      → Tool calls dispatched (webhook, credit consult, status update, escalate, etc.)
  → FactCheckService::validate() — guardrail against hallucinated credit data
  → AgentInteractionEventService::append() — writes audit event
  → ConversationTimelineService::write() — persists reply + broadcasts
  → WhatsappOutboxService::queue() — queues outbound message
```

### AI Tools (app/Ai/Tools/)

All tools are invokable classes. They receive the current `Lead` and context via constructor injection. Available tools per agent are declared in the agent's `tools()` method.

Tools that update CRM state **must** use the service layer — never write models directly from a tool.

### Agent Configuration

Resolved at runtime per-lead via `AgentConfigResolver`:
1. Per-agent `AgentConfig` DB record (if set)
2. Per-tenant `AppSetting` key-value store
3. Hardcoded defaults from `config('credflow.agent.*')`

### Interaction IDs

Every inbound pipeline generates a UUID `interaction_id` via `AgentInteractionEventService::newInteractionId()`. This UUID is threaded through all service calls, job dispatches, and event records — creating a complete audit trail in `agent_interaction_events`. **Always pass `$interactionId` when calling services that accept it.**

### RULES — AI Agent Pipeline
- New niches must be added to `config/credflow.php` under `agents` map, then a concrete class created extending `BaseCustomerServiceAgent`
- New AI tools go in `app/Ai/Tools/` as invokable classes; register them in the relevant agent's `tools()` method
- Tools must NOT write models directly — call a Service
- Always call `AgentInteractionEventService::newInteractionId()` at the start of new pipeline entries; pass it downstream
- Do not change LLM defaults (temperature, max_tokens, max_steps) in code — change `config/credflow.php`
- `ToolCallTracker` is scoped per-request; do not share it across jobs
- `AgentInteractionContext` binding is `scoped` — cleared in `finally` of `AgentService::process()`; ensure new entry points also clear it

---

## 5. HTTP Layer

### Route Organization

| File | Purpose |
|------|---------|
| `routes/web.php` | All Inertia browser routes (~120). Auth+verified gated. |
| `routes/api.php` | Webhooks + IVR + URA + direct agent endpoint |
| `routes/settings.php` | User/team settings (required at bottom of web.php) |
| `routes/channels.php` | Reverb broadcast channel auth |
| `routes/console.php` | Scheduler definitions |

### Route Naming

Named routes use **plural Portuguese nouns**: `conversas.*`, `agentes.*`, `campanhas.*`, `listas-contato.*`, `atendimentos.*`, `configuracoes.pipeline.*`. Follow this pattern exactly for new routes.

### Conversas Operator Routes

Operator actions on individual conversations — all under `auth+verified`:

| Route | Verb | Controller method | Purpose |
|-------|------|-------------------|---------|
| `conversas.send` | POST | `ConversasController::send` | Send message as operator (bypasses AI, queues via `WhatsappOutboxService`) |
| `conversas.pause` | POST | `ConversasController::pause` | Manually pause AI for this lead (sets `ai_paused_until`, writes audit event) |
| `conversas.resume` | POST | `ConversasController::resume` | Resume AI after manual pause |
| `conversas.claim` | POST | `ConversasController::claim` | Assign lead to the authenticated user |
| `conversas.ai-mode` | PATCH | `ConversasController::updateAiMode` | Override AI mode for this lead (`automatic` / `manual` / `assisted` / `qualify_then_handoff` / null = inherit) |
| `conversas.followup.pause` | POST | `LeadFollowUpController::pause` | Pause follow-up sequence for lead |
| `conversas.followup.resume` | POST | `LeadFollowUpController::resume` | Resume paused follow-up sequence |
| `conversas.followup.disable` | POST | `LeadFollowUpController::disable` | Permanently disable follow-up for lead |
| `conversas.followup.reactivate` | POST | `LeadFollowUpController::reactivate` | Re-enable a disabled follow-up sequence |

### Handoff Queue Routes

The human atendimento queue (Phase 54) is mounted at `/atendimentos` with these named routes — all under `auth+verified`:

| Route | Verb | Controller method |
|-------|------|-------------------|
| `atendimentos.index` | GET | `ServiceTicketController::index` |
| `atendimentos.claim` | POST | `ServiceTicketController::claim` |
| `atendimentos.followup.disable` | POST | `ServiceTicketController::disableFollowUp` |
| `atendimentos.resolve` | POST | `ServiceTicketController::resolve` |
| `atendimentos.close` | POST | `ServiceTicketController::close` |
| `atendimentos.return-to-ai` | POST | `ServiceTicketController::returnToAi` |
| `atendimentos.keep-manual` | POST | `ServiceTicketController::keepManual` |

### Controller Style

- Conventional controllers with named methods (not invokable), except single-action utilities
- Constructor-inject services as `private readonly ServiceClass $service`
- Method-inject when only one action needs the dependency
- Authorize via policies: `$this->authorize('update', $lead)`
- Return Inertia responses: `Inertia::render('Page/ComponentName', $props)`
- Return JSON for AJAX: `response()->json([...])`
- Redirect with flash: `redirect()->route('name')->with('flash', 'Message text')`

### Form Requests

Every mutation needs a FormRequest. Location: `app/Http/Requests/`. Include both `rules()` and `messages()`. Use array-based rules: `['required', 'string', 'max:255']`.

### Inertia Prop Shapes

Props are built as flat associative arrays in controllers. There is no DTO or transformer layer for Inertia — build the array directly. If a props builder grows beyond ~40 lines, extract it to a private `buildProps()` method on the controller.

### Rate Limiting

Custom rate limiters registered in `AppServiceProvider`: `meta-webhook`, `aria-direct`, `ura-inbound`, `auto-tags`. All tunable via `config/credflow.php`. Do not add new inline `throttle:N` decorators — add a named limiter.

### RULES — HTTP Layer
- Use `php artisan make:` commands to create controllers, requests, etc.
- Never use `Route::resource()` unless the route is truly a standard CRUD resource — use explicit named routes
- Never hardcode a URL string in PHP — use `route('name')` or `action([Controller::class, 'method'])`
- Always create a FormRequest for mutations; never validate inline in a controller
- Never use `env()` outside a config file
- All new web routes must be inside the `auth` + `verified` middleware group unless explicitly public

---

## 6. Frontend Architecture

### Page Structure

Pages in `resources/js/pages/` mirror the route hierarchy and use **PascalCase Vue SFC files**:

```
pages/
├── conversas/     Index.vue, Show.vue + partials/
├── agentes/       Index.vue, Create.vue
├── campanhas/     Index.vue, Show.vue, Create.vue
├── listas-contato/ Index.vue + FilterBuilder.vue, etc.
├── atendimentos/  Index.vue (human handoff queue UI — phase 54)
├── pipeline/      Index.vue
├── laboratory/    Index.vue, Datasets.vue, AiUsage.vue, Health.vue
└── settings/      Profile.vue, Password.vue, Team.vue, etc.
```

### Component Library

`resources/js/components/ui/` — shadcn-vue style (alert, badge, button, card, checkbox, dialog, dropdown-menu, input, select, sheet, sidebar, tooltip). Each folder has `index.ts` barrel export. **Check here before writing a new component.**

### State Management

- **No Pinia.** All state via Inertia server-rendered props.
- Forms: `useForm` from `@inertiajs/vue3`
- Real-time: Laravel Echo + Reverb (`resources/js/echo.ts`)
- Shared auth/tenant state: `HandleInertiaRequests` middleware pushes `auth`, `role`, `escalation_count` as shared props

### Composables

`resources/js/composables/` — keep thin. Current: `useAppearance`, `useCurrentUrl`, `useInitials`, `useTwoFactorAuth`, `useDashboardMetrics`, `useFilterPreview`. Add new composables only for reusable stateful logic, not one-off component logic.

### Route References

**Always use Wayfinder.** Never hardcode URL strings in Vue.

```ts
// CORRECT
import { show } from '@/actions/App/Http/Controllers/ConversasController'
const url = show({ id: lead.id })

// WRONG
const url = `/conversas/${lead.id}`
```

Activate the `wayfinder-development` skill when working on any frontend route reference.

### Conversas Page — Operator Controls

The `/conversas` page is a 3-column inbox (sidebar list / thread / details panel) that doubles as the primary human operator workspace. Key capabilities live in `resources/js/pages/conversas/partials/`:

**`ConversationThread.vue`** — message thread + human send box
- Displays messages with three roles: `user` (customer, primary-colored), `assistant` (AI, muted), `operator` (human, blue)
- Operator can type and send messages via `POST conversas.send`; accepts text and file attachments (image/pdf)
- Real-time: subscribes to `conversation.{leadId}` private channel, listens `.message.new`

**`LeadDetailsPanel.vue`** — right-panel operator controls (xl breakpoint only)
- **Controle do Agente** section:
  - `<select>` for per-lead AI mode override: `automatic` / `manual` / `assisted` / `qualify_then_handoff` / `` (inherit from instance). Sends `PATCH conversas.ai-mode` on change.
  - **"Assumir e pausar IA"** — `POST conversas.pause` — sets `ai_paused_until` + audit event; hides AI send loop
  - **"Retomar IA"** — `POST conversas.resume` — clears pause; shown with amber warning "Agente pausado - responda manualmente"
- **Atendimento** section (shown only when `handoff_state !== 'ai_active'`):
  - Displays handoff state badge, reason, summary, responsible operator, SLA
  - **"Devolver para IA"** — `POST atendimentos.return-to-ai` — resolution `returned_to_ai`, re-enables AI
  - **"Manter manual"** — `POST atendimentos.keep-manual` — resolution `manual_keep`, locks lead to manual mode
  - Available actions driven by `handoff_actions[]` array from `HumanHandoffStateService`
- **Controle do Follow-up** section: pause / resume / disable / reactivate follow-up sequence

> **Pause vs Handoff:** AI pause (`conversas.pause`) is a lightweight manual hold — operator takes over without creating a `ServiceTicket`. Human handoff (via `EscalarParaHumanoTool`) creates a `ServiceTicket` and routes the lead to `/atendimentos` queue. Both suppress the AI pipeline via `ConversationAutomationService::resolveMode()`.

**`ConversationSidebar.vue`** — lead list with filters (status, instance, AI mode, stage, assigned)

### Pipeline Page — Kanban Board

`/pipeline` (`pipeline/Index.vue` + `PipelineController`) is a drag-and-drop Kanban view of all tenant leads grouped by `lead.status`.

**Status ↔ Column — bidirectional sync:**
`lead.status` is the single source of truth. The Kanban is a live grouped view of that field:
- **Drag card to column** → `POST pipeline.move` → updates `lead.status` → also sets `ai_paused_until = now+24h` (reason `manual_status_override`) — drag implies operator takeover
- **Status changed elsewhere** (e.g. `StatusSelect` in `/conversas`) → board reflects new column on next load/reload

Columns = all `StatusMachine` statuses for the tenant, **except** `sem_credito` (hidden via `HIDDEN_BOARD_STATUS_SLUGS`). Column headers show total count across all pages; each column loads up to 30 cards via cursor pagination.

**Card shape** (`toCardShape()`):
- Name, phone, lead ID, source label (campaign name or `modo`)
- `automation_state`: `active` (Sparkles icon) when AI mode is automatic/qualify_then_handoff AND not paused; `manual` otherwise
- Follow-up active badge, tags (with `is_hot` → destructive color)
- Last interaction time, "Ver Contato" link

**Instance AI mode** resolved batch-style via `preloadInstanceModes()` — single `whereIn` before card mapping. Never query per-card.

**Filters:** agent, instance, tags (multi), date range, search (lead name). Passed as `IndexPipelineRequest` validated props.

**Routes:**

| Route | Verb | Purpose |
|-------|------|---------|
| `pipeline.index` | GET | Render board with all columns |
| `pipeline.column` | GET | JSON — load next cursor page for one column |
| `pipeline.move` | POST | Drag drop: update status + pause AI 24h |

### RULES — Frontend
- Vue components must have a single root element
- No Pinia — use Inertia props for all state
- Check `components/ui/` before creating new components
- All route references via Wayfinder imports — never hardcode URLs
- Activate `tailwindcss-development` skill before adding Tailwind classes; check docs for v4 syntax
- Always use `useForm` for form submissions, not `axios` directly; exception: `conversas.send` uses raw `fetch` with `FormData` to support file uploads
- Deferred props: always add skeleton loading state

---

## 7. Authentication & Authorization

### Auth Stack

- **Fortify** (headless) handles: login, logout, register, password reset, email verify, 2FA/TOTP
- Rate limiting: `login` (5/min), `two-factor` (5/min)
- Registration flow: `CreateNewUser` Fortify action → creates `Tenant` + attaches user as `Owner` in a transaction

### Tenant RBAC

| Role | Capabilities |
|------|-------------|
| `Owner` | Full access |
| `Administrator` | Full access |
| `User` | Read + own agents' leads + assigned leads only |

`EnsureTenantRole` middleware: `->middleware('role:owner,administrator')` on mutation routes.

Policies check both tenant membership AND role. `LeadPolicy` restricts `User` role to own agents' leads or explicitly assigned leads.

### API Authentication

| Surface | Method |
|---------|--------|
| `/api/aria` | `AuthenticateApiKey` Bearer token |
| IVR routes | `ValidateTwilioSignature` HMAC |
| URA routes | `AuthenticateUraApiKey` + `throttle:ura-inbound` |
| Meta webhook | HMAC-SHA256 inline in controller |

### RULES — Auth
- Never bypass policy checks in controllers — always `$this->authorize()`
- New admin-only routes must use `->middleware('role:owner,administrator')`
- New API endpoints must use `AuthenticateApiKey` or a scoped equivalent — never expose unauthenticated write endpoints
- Activate `developing-with-fortify` skill before touching auth flows
- `User::tenantId` is the canonical tenant resolver — never read `active_tenant_id` from session directly in controllers

---

## 8. Queue & Background Processing

### Queue Architecture

- **`messages` queue** — `ProcessIncomingWhatsAppMessageJob` only. `WithoutOverlapping` per phone+tenant. 3 tries, backoff [10, 30, 60].
- **`default` queue** — everything else
- Production: Redis via Horizon. Dev: database driver.

### Job Patterns

All jobs implement `ShouldQueue`. Key patterns from existing jobs:
- Pass `$tenantId` as constructor property (scope is off in console)
- Use `WithoutOverlapping` for per-lead/per-phone uniqueness
- Write to `FailedInteraction` on recoverable failures (not `failed_jobs`) — `InteractionRecoveryService::record()`
- `RetryFailedInteractionJob` replays these on a 5-min schedule

### Scheduler (routes/console.php)

| Command | Schedule |
|---------|---------|
| `credflow:check-followups` | Every 5 min |
| `ProcessPendingRetriesCommand` | Every 5 min |
| `credflow:start-scheduled-campaigns` | Every minute |
| `credflow:monitor-campaigns` | Every 5 min |
| `LaboratoryHealthCheckCommand` | Every 15 min |
| `credflow:aggregate-usage` | Daily 01:00 |
| `CleanOldMediaFilesCommand` | Daily 03:00 |
| `credflow:purge-old-credits` | Daily 04:00 |
| `SyncTemplatesCommand` | Daily |

### RULES — Queue
- New jobs go in `app/Jobs/` and implement `ShouldQueue`
- Always pass `$tenantId` as a job property — never rely on the global scope
- For recoverable AI/API failures: use `InteractionRecoveryService::record()` + retry job pattern. Do not use `->fail()` for transient errors.
- New scheduled commands: add to `routes/console.php`, not `app/Console/Kernel.php` (doesn't exist in Laravel 12)
- Log AI token usage via `LogAiUsageJob` — do not write `AiUsageDaily` directly

---

## 9. Real-Time (Reverb/Echo)

### Architecture

- **Reverb** (Laravel Reverb v1) WebSocket server
- **Laravel Echo** v2 on the frontend (`resources/js/echo.ts`)
- Broadcast events in `app/Events/` — all extend `ShouldBroadcast`

### Key Channels

All channels are private; authorization lives in `routes/channels.php`.

| Channel | Events (broadcastAs) | Consumer | Auth rule |
|---------|----------------------|----------|-----------|
| `dashboard.{tenantId}` | `DashboardMetricsUpdated` | `useDashboardMetrics` composable | `user->tenantId === tenantId` |
| `conversation.{leadId}` | `NewConversationMessage`, `ConversationUpdated`, `handoff.created`, `handoff.claimed`, `handoff.resolved`, `handoff.returned_to_ai`, `conversation.assignment.changed` | Conversation Show / Inbox | tenant match + (restricted users: assignee or own agent) |
| `conversations.{tenantId}` | tenant-wide conversation broadcasts | Inbox list | `user->tenantId === tenantId` |
| `atendimentos.{tenantId}` | `handoff.created`, `handoff.claimed`, `handoff.resolved`, `handoff.returned_to_ai`, `atendimento.counters.updated` | Atendimento queue UI | `user->tenantId === tenantId` |
| `campaigns.{campaignId}` | `CampaignProgressUpdated`, `CampaignStatusChanged` | Campaign Show | campaign tenant match |
| `instances.{instanceId}` | `InstanceStatusChanged`, `InstanceQualityRatingChanged` | Instance detail / sidebar | instance tenant match |

Note: the older `conversation.{tenantId}.{leadId}` channel name has been replaced by `conversation.{leadId}` with tenant assertion inside the channel callback. Frontend uses `window.Echo.private('conversation.' + leadId)`.

### Broadcast Debouncing

`DashboardMetricsService` uses `BroadcastDebouncer` (Redis-backed) to avoid flooding the WebSocket on rapid metric changes. Default `DEBOUNCE_SECONDS=3`. **Use this pattern for any high-frequency broadcast event.**

### RULES — Real-Time
- New broadcast events go in `app/Events/` extending `ShouldBroadcast`
- Add channel authorization in `routes/channels.php`
- High-frequency events must use `BroadcastDebouncer` with a sensible Redis TTL
- Frontend consumers use `window.Echo.private('channel').listen('.EventName', callback)`
- Handoff/atendimento events use **dot-prefixed event names** in Echo listeners (e.g., `.handoff.created`) because they override `broadcastAs()`
- When the active lead changes in the conversas page, **always leave and re-subscribe** the per-lead handoff channel (`conversation.{leadId}`) — see fix in `conversas/Index.vue` (commit 5e75805)

---

## 10. Human Handoff Pipeline

Phases 52–55 introduced a first-class human-handoff queue. AI agents transfer a conversation to a queue of human operators when the LLM decides escalation is needed (proposal accepted, customer requests, technical problem, etc.). Operators claim, work, and resolve tickets from `/atendimentos`.

### Data Model

`ServiceTicket` is the canonical handoff record (one **active** escalation per `lead_id` is invariant — enforced via `activeEscalation` scope + row lock in `HumanHandoffTransferService`):

```
type:        escalation | no_credit
status:      open → assigned → waiting_customer / waiting_internal → resolved → closed
priority:    low | normal | high | urgent
resolution:  converted | lost | returned_to_ai | manual_keep | duplicate | no_response
sla_due_at:  computed from priority at creation
```

### State Derivation

`HumanHandoffStateService::deriveState($lead)` returns a frontend-friendly machine state:

| Active ticket status | Derived state |
|----------------------|---------------|
| (none) | `ai_active` |
| `open` | `waiting_human` |
| `assigned`, `waiting_internal` | `human_active` |
| `waiting_customer` | `waiting_customer` |
| `resolved`, `closed` | `closed` |

`HumanHandoffStateService::activeHandoffPayload($lead)` returns the structured ticket payload (id, type, status, priority, sla, assigned user, claimed_at, first_response_at) used by Inertia props and broadcast payloads.

### Transfer Flows

| Direction | Service | Notes |
|-----------|---------|-------|
| AI → human queue | `HumanHandoffTransferService::transferFromAi($lead, $data)` | Wrapped in `DB::transaction` with `lockForUpdate` on lead + active ticket. Audit event written **outside** transaction so event failure cannot undo CRM state. Caches `human_pending` pause for 10 hours. |
| Human → human (re-assign) | `ConversationTransferService` + `BulkTransferRequest` | Used by sidebar single transfer and bulk transfer endpoint. |
| Human → AI (return) | `ServiceTicketLifecycleService::returnToAi($ticket)` | Resolution `returned_to_ai`. Re-enables automation. |
| Human keeps manual | `ServiceTicketLifecycleService::keepManual($ticket)` | Resolution `manual_keep`. Locks lead to manual mode. |

### AI Tool: `EscalarParaHumanoTool`

Lives at `app/Ai/Tools/EscalarParaHumanoTool.php`. Registered in `CredFlowAgent` and `SiapeAgent`. Required schema fields: `motivo` (one of `proposta_aceita`, `solicitacao_cliente`, `problema_tecnico`, `outro`), `resumo` (free text). Optional: `produto_escolhido`, `valor_total`.

Idempotency: if an active escalation already exists, the tool returns `ToolResult::alreadyDone(...)` instead of creating a duplicate ticket. Tools never write models directly — this one calls `HumanHandoffTransferService::transferFromAi()` via the container.

### Automation Pause

When a ticket is active, `ConversationAutomationService::resolveMode()` returns `human` and the LLM pipeline is short-circuited. The AI follow-up scheduler (`ProcessLeadFollowUpJob`) is also gated by `FollowUpWindowService` to suppress follow-ups during human ownership.

### Broadcast Events (Phase 55)

| Event class | `broadcastAs` | Channels |
|-------------|---------------|----------|
| `HumanHandoffCreated` | `handoff.created` | `atendimentos.{tenantId}` |
| `HumanHandoffClaimed` | `handoff.claimed` | `atendimentos.{tenantId}` + `conversation.{leadId}` |
| `HumanHandoffResolved` | `handoff.resolved` | `atendimentos.{tenantId}` + `conversation.{leadId}` |
| `HumanHandoffReturnedToAi` | `handoff.returned_to_ai` | `atendimentos.{tenantId}` + `conversation.{leadId}` |
| `ConversationAssignmentChanged` | `conversation.assignment.changed` | `conversation.{leadId}` |
| `AtendimentoCountersUpdated` | `atendimento.counters.updated` | `atendimentos.{tenantId}` — payload: `waiting`, `mine`, `overdue` |

Counter updates are coalesced via the same `BroadcastDebouncer` pattern as dashboard metrics — high mutation traffic must not flood the websocket.

### RBAC

- `LeadPolicy` restricts `User`-role operators to **own agents' leads or explicitly assigned leads**
- `ServiceTicketPolicy` enforces claim/resolve/close authorization
- Restricted users only see their own slice of the queue (`HandleInertiaRequests` shares an `escalation_count` prop scoped to that view)
- Concurrency: claim uses optimistic locking — second claimer gets a 409-style conflict (see `AtendimentoClaimConcurrencyTest`)

### RULES — Human Handoff
- Never create or mutate a `ServiceTicket` directly from a controller or tool — go through `HumanHandoffTransferService`, `ConversationTransferService`, or `ServiceTicketLifecycleService`
- One active escalation ticket per lead is an invariant — preserve the `lockForUpdate` + `activeEscalation` scope pattern in any new mutation path
- `EscalarParaHumanoTool` is the **only** AI-facing handoff surface — do not add a second one
- New handoff broadcast events use a dot-prefixed `broadcastAs` string (e.g., `handoff.*`) and frontend listeners must use the matching `.handoff.*` prefix
- Use `HumanHandoffStateService::deriveState()` for any UI that needs to display handoff state — do not re-derive from raw ticket status

---

## 11. Configuration

### Config Files

| File | Purpose |
|------|---------|
| `config/credflow.php` | Master: agent params, rate limits, campaign limits, costs |
| `config/aria.php` | Smart lists config |
| `config/laboratory.php` | Observability layer |
| `config/agent_templates.php` | Niche template definitions |
| `config/ai.php` | Laravel AI package provider/model defaults |

### Tunable Behavior via credflow.php

All LLM behavior tuning goes through `config/credflow.php`, not hardcoded values:
- `agent.temperature`, `agent.max_tokens`, `agent.max_steps`, `agent.timeout_seconds`
- `debounce_seconds` — message aggregation window
- `followup.*` — follow-up scheduling parameters
- `campaigns.rate_per_minute` — campaign send rate cap

### RULES — Configuration
- **Never use `env()` outside a config file** — use `config('credflow.something')`
- New behavior knobs go in `config/credflow.php` with an env var fallback
- New niche/agent types go in `config/credflow.php` under `agents` map
- All cost tracking models listed in `config/credflow.php` under `model_costs`

---

## 12. Known Issues & Anti-Patterns

These are confirmed issues in the current codebase. Do not replicate them. Fix them when touching the affected code.

### N+1 Queries

**Pipeline Kanban (PipelineController) — FIXED (2026-05-25):**
- `PipelineController` now pre-loads all `WhatsappInstance.default_ai_mode` values via a single `whereIn` query before card mapping. `toCardShape()` receives a pre-built `Collection $defaultModesByInstance` map.
- Do not replicate the old per-card instance query pattern in new list views.

### Tenant ID Type Inconsistency

- Legacy models used user's integer `id` as `tenant_id` (not a `Tenant` model PK)
- Partially remediated by `realign_legacy_tenant_id_string_keys` migration
- `BelongsToTenant` trait compares as `(string)` which papers over integer vs string mismatches
- **Fixed in:** `StoreCampaignRequest`, `UpdateCampaignRequest`, `StoreUraApiKeyRequest` (2026-05-25)
- **Fix when touching:** Use `$this->user()->tenantId` consistently; never use `$this->user()->id` as a tenant scope

### Missing Inertia Prop DTOs

- Controller methods build large prop arrays inline (e.g., `ConversasController::inboxProps()` is ~100 lines)
- No DTO or transformer layer for Inertia responses
- **Acceptable for now** but extract to private `buildProps()` methods when methods exceed ~40 lines

### StatusMachine Request-Level Cache

- Uses `app()->instance("status_machine.{$tenantId}")` as per-request cache
- `StatusMachineObserver::saved()` flushes it on save, but concurrent requests won't see the flush
- **Low risk** but do not rely on the cache being fresh in multi-step operations

### Global Scope Off in Jobs

- `BelongsToTenant` skips scope in console — queue workers silently query all tenant data if not explicitly scoped
- Existing jobs pass `$tenantId` as a property but the pattern is manual, not enforced
- **Always check:** Any new job that queries tenant models must include `->where('tenant_id', $this->tenantId)`

### Evolution API Provider — REMOVED (2026-05-26)

Commit `81dfa03` removed the Evolution WhatsApp provider; **MetaCloud is the only provider**. All `EvolutionCampaign*` models, `Evolution*Service` classes, and `campanhas-evolution/*` pages are gone. Do not reintroduce them or `WhatsAppProvider::Evolution` references — `WhatsAppProvider::MetaCloud` is the sole enum case. Migration `2026_05_25_195150_migrate_whatsapp_instances_provider_to_meta_cloud` realigned existing rows.

### Handoff Channel Subscription Bug — FIXED (2026-05-27)

The `conversas/Index.vue` page subscribed once to `conversation.{leadId}` for the initial active lead and never re-subscribed when the user switched leads. Commit `5e75805` fixed this by tearing down and re-creating the Echo subscription on `activeLead` change. **Pattern:** any per-entity private channel in a list-detail page must be re-subscribed on selection change.

---

## 13. Coding Rules Summary

Quick-reference checklist for AI coding agents before writing code:

### Must Do
- [ ] Run `vendor/bin/pint --dirty --format agent` before finalizing PHP changes
- [ ] Every mutation has a `FormRequest` with `rules()` and `messages()`
- [ ] Every new tenant-scoped model has `BelongsToTenant` trait
- [ ] Every new queued job passes `$tenantId` as constructor property
- [ ] All AI tool results route through a Service — no direct model writes from tools
- [ ] Frontend route references use Wayfinder imports, never string URLs
- [ ] All config values via `config()` helper — never `env()` in non-config files
- [ ] Activate domain skill before working: `pest-testing`, `inertia-vue-development`, `tailwindcss-development`, `wayfinder-development`, `mcp-development`, `developing-with-fortify`
- [ ] Write or update a test for every change; run `php artisan test --compact` to verify

### Must Not Do
- [ ] Do not use `DB::` — use `Model::query()` or Eloquent relationships
- [ ] Do not hardcode URL strings in Vue or PHP — use Wayfinder / `route()`
- [ ] Do not add Pinia — state comes from Inertia props
- [ ] Do not create new base folders in `app/` without approval
- [ ] Do not add dependencies to `composer.json` / `package.json` without approval
- [ ] Do not use `StatusMachine::CANONICAL_SLUGS` values as magic strings — import the constant
- [ ] Do not add AI tools that write models directly — they must go through Services
- [ ] Do not replicate the per-card instance query pattern — batch instance lookups with a single `whereIn` before any card/lead mapping loop
- [ ] Do not use `$this->user()->id` as a tenant scope in FormRequests — use `->tenantId`

---

## Appendix: Key File Map

| What you need | File |
|---------------|------|
| Middleware registration | `bootstrap/app.php` |
| All web routes + RBAC groups | `routes/web.php` |
| Webhook + API surface | `routes/api.php` |
| Scheduler definitions | `routes/console.php` |
| Tenant global scope | `app/Models/Concerns/BelongsToTenant.php` |
| Tenant resolution on User | `app/Models/User.php` |
| Central domain entity | `app/Models/Lead.php` |
| AI bot config | `app/Models/Agent.php` + `app/Models/AgentConfig.php` |
| State machine with canonical slugs | `app/Models/StatusMachine.php` |
| Polymorphic tagging | `app/Models/Concerns/HasTags.php` |
| Core message pipeline entry | `app/Jobs/ProcessIncomingWhatsAppMessageJob.php` |
| LLM orchestration | `app/Services/AgentService.php` |
| Agent class resolution | `app/Ai/AgentFactory.php` |
| Abstract LLM agent base | `app/Ai/Agents/BaseCustomerServiceAgent.php` |
| AI mode resolution | `app/Services/ConversationAutomationService.php` |
| Message persistence + broadcast | `app/Services/ConversationTimelineService.php` |
| RBAC middleware | `app/Http/Middleware/EnsureTenantRole.php` |
| Shared Inertia props | `app/Http/Middleware/HandleInertiaRequests.php` |
| Container bindings + rate limiters | `app/Providers/AppServiceProvider.php` |
| All tunable behavior | `config/credflow.php` |
| Frontend app bootstrap | `resources/js/app.ts` |
| WebSocket config | `resources/js/echo.ts` |
| Real-time metrics pattern | `resources/js/composables/useDashboardMetrics.ts` |
| Broadcast channel auth | `routes/channels.php` |
| Human handoff ticket model | `app/Models/ServiceTicket.php` |
| AI → human transfer | `app/Services/HumanHandoffTransferService.php` |
| Human → human / re-assign | `app/Services/ConversationTransferService.php` |
| Ticket lifecycle (resolve/close/return-to-AI) | `app/Services/ServiceTicketLifecycleService.php` |
| Handoff state derivation for UI | `app/Services/HumanHandoffStateService.php` |
| Follow-up suppression during handoff | `app/Services/FollowUpWindowService.php` |
| AI escalation tool | `app/Ai/Tools/EscalarParaHumanoTool.php` |
| Handoff broadcast events | `app/Events/HumanHandoff*.php`, `ConversationAssignmentChanged.php`, `AtendimentoCountersUpdated.php` |
| Atendimento queue controller | `app/Http/Controllers/ServiceTicketController.php` |
| Atendimento queue UI | `resources/js/pages/atendimentos/Index.vue` |
