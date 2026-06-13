# PRD — Aria: AI SDR Platform for Brazilian Credit Market

**Version:** 4.0 (Current State + Roadmap)  
**Date:** 2026-05-25  
**Status:** Living Document  
**Supersedes:** PRD_ARIA_SaaS.md v3.0 (2026-03-09)

---

## 1. Product Vision

Aria is a **multi-tenant AI SDR SaaS** for the Brazilian credit market (consignado INSS and SIAPE). It enables credit brokers (CORBANs) to fully automate lead qualification and sales via WhatsApp — from first contact to closed deal — using AI agents that conduct conversations, consult credit bureaus, handle objections, and escalate to human operators when needed.

**Core value proposition:** Replace manual WhatsApp outreach with an autonomous AI pipeline that operates 24/7, qualifies leads in real time, and costs a fraction of human operators.

---

## 2. Target Users

**Primary:** CORBANs (Correspondentes Bancários) in the Brazilian consignado credit market.

| Attribute | Profile |
|-----------|---------|
| Company size | 1–50 operators |
| Revenue model | Commission per closed loan |
| Primary channel | WhatsApp (Meta Cloud API official) |
| Technical literacy | Low — needs simple UI |
| Domain vocabulary | margem, refin, porta, troco, glosa, RCC/RMC, SIAPE |

**Secondary:** Operations managers who monitor agent performance, respond to escalations, and manage campaigns.

---

## 3. Current Feature Set (Implemented)

### 3.1 AI Agent Engine

- **Three agent modes per lead:**
  - `automatic` — AI handles fully autonomous
  - `qualify_then_handoff` — AI qualifies, then escalates to human
  - `manual` — Human handles; AI assists
  - `assisted` — Human composes; AI suggests
- **Niche support:** INSS consignado, SIAPE (extensible via `config/credflow.php`)
- **LLM providers:** OpenRouter + OpenAI via configurable routing
- **Token budget control:** per-request ceiling via `TokenBudgetMiddleware`
- **Tool call guard:** max steps enforced via `ToolCallGuardMiddleware`
- **Audit trail:** every AI interaction logged to `agent_interaction_events` with UUID `interaction_id`
- **Fact-check guardrail:** `FactCheckService` validates AI response against lead credit data before sending
- **Recovery loop:** `InteractionRecoveryService` records transient failures to `failed_interactions`; `RetryFailedInteractionJob` replays on 5-min schedule

### 3.2 AI Tools Available per Agent

| Tool | Purpose |
|------|---------|
| Credit consultation (INSS) | Queries Promosys API for credit margin |
| Credit consultation (SIAPE) | Queries SIAPE credit API |
| Update lead status | Moves lead through status machine |
| Escalate to human | Creates `ServiceTicket`, pauses AI |
| Register no-credit | Marks lead as `sem_credito`, creates ticket |
| Send webhook | Calls custom tenant-defined webhooks |
| Register data | Updates lead fields (CPF, nome, etc.) |
| Send media | Sends document/image to lead |
| Schedule follow-up | Sets custom follow-up timing |

### 3.3 WhatsApp Integration

- **Meta Cloud API** — official WhatsApp Business API; HMAC-signed webhooks; only supported provider
- **Outbox queue:** all outbound messages queued to `whatsapp_outbox_messages` before dispatch; ensures ordering and retry
- **Message debouncing:** configurable window (`DEBOUNCE_SECONDS`) aggregates rapid inbound messages before triggering AI
- **Instance management:** per-tenant WhatsApp instance lifecycle (connect, status, disconnect) via dashboard
- **URA integration:** URA-sourced inbound leads receive follow-up WhatsApp template messages dispatched via Meta Cloud API

### 3.4 Campaigns

Two campaign types, both with scheduling, rate limiting, and delivery tracking:

| Type | Provider | Use case |
|------|----------|---------|
| WhatsApp Template | Meta Cloud API | Official bulk messaging with approved templates |
| Voice IVR | Twilio | Outbound call campaigns with DTMF scripting |

**Campaign features:** draft → scheduled → sending → paused → finished lifecycle, per-minute rate cap, error threshold auto-pause, delivery event tracking.

### 3.5 Lead & Contact Management

- **Lead** — one conversation per contact per WhatsApp instance/agent
- **Contact** — canonical person record; synced from lead data
- **Status machine** — per-tenant configurable pipeline statuses + transitions (7 canonical AI slugs protected)
- **Pipeline/Kanban** — drag-and-drop board view by status; cursor-paginated columns
- **Custom fields** — EAV extension for lead data (`CustomField` / `CustomFieldValue`)
- **Tags** — polymorphic tagging with AI vs manual source semantics; hot tags
- **Auto-tagging** — `LeadAutoTaggingService` runs LLM-based tag suggestions via `TagLeadFromConversationJob`
- **Service tickets** — human escalation records with SLA tracking and assignment
- **Assignment** — leads assignable to specific operators; `User` role sees only own/assigned leads

### 3.6 Contact Lists & Smart Lists

- **Static lists** — manually curated contact lists
- **Smart lists** — dynamic lists resolved at runtime from filter predicates (capped at `ARIA_SMART_LIST_MAX_RESOLVE`)
- **Filter builder** — UI filter builder for smart list predicates (status, tags, date ranges, custom fields)
- Campaign targeting uses contact lists as the audience source

### 3.7 Follow-Up Automation

- Fully automated follow-up scheduling via `ProcessLeadFollowUpJob`
- Configurable: first delay, daily send time, max attempts, zombie cutoff (default 14 days)
- 24h WhatsApp service window tracking (`FollowUpWindowService`)
- Tone variation across attempts
- Per-lead follow-up history visible in conversation UI

### 3.8 Multi-Tenant & RBAC

- Each registration creates a `Tenant` + `Owner` user
- Tenant invitations with role assignment
- **Roles:** Owner, Administrator, User
- `User` role restricted to own agents' leads + assigned leads + unassigned queue
- Global Eloquent scope on all tenant-scoped models (`BelongsToTenant` trait)

### 3.9 Real-Time Dashboard

- KPI snapshot: active leads, escalations, sent messages, follow-up queue
- Live updates via Laravel Reverb WebSocket
- Debounced broadcasts prevent flood on high-activity periods

### 3.10 Laboratory (Observability)

- **Health monitor** — system health checks on schedule
- **Stress tests** — configurable load simulations on AI pipeline
- **AI usage tracking** — daily token cost aggregation per tenant/model (`ai_usage_dailies`)
- **Datasets** — exportable lead data subsets for analysis
- **Langfuse integration** — LLM call tracing and scoring

### 3.11 Playground (Sandbox)

- Isolated sandbox per agent config
- Simulates full AI conversation without real WhatsApp dispatch or real credit API calls
- Used for prompt iteration before live deployment

### 3.12 Prompt Management

- DB-stored prompt templates (`PromptTemplate`) scoped by tenant + agent
- A/B prompt experiments (`PromptExperiment`) referencing template variants
- Niche-based template system via `config/agent_templates.php`

---

## 4. Architecture Summary

See `ARCHITECTURE.md` for full detail. Key constraints:

| Dimension | Decision |
|-----------|---------|
| Backend | Laravel 12, PHP 8.3 |
| Frontend | Inertia v2 + Vue 3 + Tailwind v4 (SPA, no SSR) |
| Database | PostgreSQL + Redis |
| Queues | Redis via Horizon; `messages` queue isolated for AI pipeline |
| WebSocket | Laravel Reverb v1 |
| LLM | OpenRouter (primary) + OpenAI, routed per agent config |
| WhatsApp | Meta Cloud API (official only) |
| Voice | Twilio IVR |
| Observability | Langfuse + Laboratory module |

---

## 5. Business Model

### Subscription Plans (B2B SaaS)

| Resource | Starter | Pro | Enterprise |
|----------|---------|-----|------------|
| Monthly price | R$ 297/mo | R$ 797/mo | Custom |
| Active leads/mo | 200 | 1,000 | Unlimited |
| Credit consultations | 200 | 1,000 | Custom |
| AI token quota | Basic (1.5M input) | High (8M input) | BYOK / Wallet |
| Operators | 1 | 5 | Unlimited |
| WhatsApp instances | 1 | 3 | Custom |
| Support | Email | SLA + WhatsApp | Account manager |

**Overages:** Wallet (pre-paid credits) for extra leads and tokens. Auto top-up on Pro plan.

**Status:** Billing system not yet implemented — plans defined but no payment gateway integrated.

---

## 6. Integrations

| Integration | Purpose | Status |
|-------------|---------|--------|
| Meta Cloud API | WhatsApp messaging (official, only provider) | Implemented |
| Promosys API | INSS credit margin queries | Implemented |
| SIAPE API | SIAPE credit queries | Implemented |
| Twilio | Voice IVR campaigns | Implemented |
| OpenRouter | LLM routing | Implemented |
| OpenAI | LLM fallback / direct | Implemented |
| Langfuse | LLM observability | Implemented |
| Sentry | Error tracking | Implemented |
| Stripe / Asaas | Billing | **Not implemented** |
| Google TTS | Voice text-to-speech | Implemented |

---

## 7. Roadmap

### ✅ Completed
- AI agent engine with tool calling, fact-check guardrail, recovery loop
- WhatsApp integration (Meta Cloud API)
- Multi-tenant RBAC
- Lead/contact management with custom fields, tags, auto-tagging
- Pipeline/Kanban board
- Service tickets with SLA
- Campaign types: WhatsApp Template (Meta Cloud) + Voice IVR (Twilio)
- Smart lists with dynamic filter builder
- Follow-up automation
- Real-time dashboard
- Laboratory observability
- Playground sandbox
- Prompt experiments

### 🔄 In Progress / Next
- **Billing system** — Stripe/Asaas integration; plan enforcement; token quota consumption tracking; Wallet top-up
- **Plan enforcement** — gate features by subscription tier; block on quota exhaustion
- **Lead quota tracking** — count active leads against plan limit; block when exceeded

### 📋 Planned
- **Alicia** — generic SDR fork of Aria for non-credit niches with CRM integrations (all plans)
- **Additional niches** — beyond INSS/SIAPE; configurable via agent templates
- **CRM integrations** — available on all subscription plans, not gated
- **Mobile operator app** — lightweight WhatsApp-style UI for field operators
- **Advanced analytics** — conversion funnel, cohort retention, revenue attribution

---

## 8. Compliance & Security

- **LGPD:** CPF masked based on user role; data expiry configurable per tenant
- **Webhook security:** HMAC-SHA256 on Meta webhooks; Twilio signature validation on IVR
- **Prompt injection defense:** system prompt hardcoded guard against lead manipulation of bot scope
- **Meta compliance:** bot scoped strictly to utility/service functions (credit qualification); no general-purpose AI exposed
- **DB safety:** `DB::prohibitDestructiveCommands()` active in production
- **Rate limiting:** per-phone, per-tenant, per-provider rate limiters registered in `AppServiceProvider`

---

## 9. Success Metrics

| Metric | Target |
|--------|--------|
| Leads qualified without human intervention | > 80% |
| Lead → escalation conversion | > 30% |
| AI response latency (message received → reply sent) | < 30s |
| Cost per lead (AI tokens) | < R$ 0.50 |
| Dashboard load time | < 5s |
| AI pipeline uptime | > 99.5% |

---

## 10. Glossary

| Term | Definition |
|------|-----------|
| CORBAN | Correspondente Bancário — credit broker licensed to originate loans |
| Consignado | Payroll-deducted credit (INSS = pensioners, SIAPE = public servants) |
| Margem | Available credit margin for a borrower |
| Refin | Refinancing of existing consignado loan |
| Porta | New consignado credit product |
| Troco | Cash difference when refinancing above original balance |
| ServiceTicket | Escalation record when AI hands off to human operator |
| Playground | Isolated sandbox for AI prompt iteration without real dispatches |
| Smart List | Dynamically resolved contact list from filter predicates |
| Interaction ID | UUID threaded through all events in a single AI turn, enabling full audit trail |
| Niche | Credit market segment (INSS, SIAPE) that maps to a specific Agent class |
