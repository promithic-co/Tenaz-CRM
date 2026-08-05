# Path 2 — Direct Agent API `POST /api/tenaz` (and the legacy `/api/aria` alias)

Requirement: RUNT-01
Success criteria: SC3, SC5
Schema version: 1
Characterized: 2026-08-05
Characterization test: TenazApiCharacterization

> D-29 binds this document. No raw phone number, message body, CPF, financial value, credential or
> tool payload appears here. Identifiers are written as placeholders (`<lead_id>`, `<tenant_id>`,
> `<interaction_id>`, `<api_key>`) and behaviour is described by classification, length and status.

> **Alias coverage.** `/api/aria` is not a seventh path. It is the same controller method behind a
> second route name, and it is characterized here rather than in a document of its own.

---

## 1. Entry & Trigger

**Trigger.** A bearer-token authenticated `POST` from an external integrator (n8n and integration
tests today) to `POST /api/tenaz` (`routes/api.php:41-43`) → `AgentController::tenaz()`
(`app/Http/Controllers/AgentController.php:22-25`), or to the legacy `POST /api/aria`
(`routes/api.php:46-48`) → `AgentController::aria()`
(`app/Http/Controllers/AgentController.php:33-38`). Both delegate to the same
`AgentController::handle()` (`app/Http/Controllers/AgentController.php:40-82`); `aria()` differs
**only** by attaching RFC 8594 `Deprecation: true` and
`Link: <…/api/tenaz>; rel="successor-version"` headers to the response it gets back from `handle()`.
The alias remains live — nothing rejects or redirects it.

**Authentication.** `AuthenticateApiKey` middleware (`app/Http/Middleware/AuthenticateApiKey.php:14-26`),
applied to both routes as a group (`routes/api.php:39`). It reads the `Authorization: Bearer` token
and resolves it against the configured key→tenant map with a **timing-safe `hash_equals` comparison
that never short-circuits on the first match**
(`app/Http/Middleware/AuthenticateApiKey.php:32-43`) — audit finding **F2**, fixed and confirmed
live. No match → `401`. This is a strength and is not modified by this phase.

**Tenant resolution.** Tenancy comes from the token, not the payload. The resolved tenant is written
onto the request as `AuthenticateApiKey::TENANT_ATTRIBUTE`
(`app/Http/Middleware/AuthenticateApiKey.php:23`) and read back at
`app/Http/Controllers/AgentController.php:50`. A payload `tenant_id` that **disagrees** with the
token's tenant is rejected with `403 Forbidden: tenant mismatch`
(`app/Http/Controllers/AgentController.php:53-55`) before any row is written — **record this as a
strength**: cross-tenant addressing is fail-closed at the door, and
`tests/Feature/TenazApiTenantIsolationTest.php` is the standing regression guard. A legacy key with
no per-tenant entry binds to `services.credflow.default_tenant_id`
(`app/Http/Middleware/AuthenticateApiKey.php:55-58`).

**Synchronous vs queued.** **Fully synchronous and in-request.** There is no queue, no debounce, no
collection window, and no `WithoutOverlapping` anywhere on this path. `AgentService::process()` is
called inline at `app/Http/Controllers/AgentController.php:79` and its return value is serialized
straight into the HTTP response at `:81`. The model call happens while the integrator's socket is
open.

**What Tenaz does *not* do on this path.** It does not send the WhatsApp message. The answer is
returned as `{"response": "<text>"}` and **the calling system performs the send**.
`WhatsappOutboxService` is never invoked; no send boundary exists on Tenaz's side of this path. That
single architectural fact drives most of § 4 and § 5.

**Rate limiting is not a concurrency guard.** Both routes carry a per-minute limiter
(`throttle:tenaz-direct` / `throttle:aria-direct`, `routes/api.php:42`, `:47`, defined at
`app/Providers/AppServiceProvider.php:60-70`, default 120/min from `credflow.api.rate_limit`). It
bounds request *volume*; it serializes nothing and knows nothing about a lead. Worth recording
precisely because it is the only rate-shaped mechanism here and must not be mistaken for one:
the limiter keys on the `X-API-Key` header, falling back to the client IP, while authentication reads
the `Authorization: Bearer` token — so an integrator that authenticates as designed and sends no
`X-API-Key` header is throttled **per source IP**, not per key or per tenant.

---

## 2. Ordered Call Chain

1. **Bearer-token gate** — `AuthenticateApiKey::handle()`
   (`app/Http/Middleware/AuthenticateApiKey.php:14-26`) resolves the tenant via
   `resolveTenantForToken()` (`app/Http/Middleware/AuthenticateApiKey.php:32-43`). No match → `401`
   and nothing downstream runs. Applied to both routes at `routes/api.php:39`.

2. **Validation and tenant-mismatch gate** — `AgentController::handle()`
   (`app/Http/Controllers/AgentController.php:40-82`) validates the payload at
   `app/Http/Controllers/AgentController.php:42-48` (`whatsapp` must match `/^\d{10,15}$/`, `message`
   ≤ 2000 chars), reads the token's tenant at `:50`, and returns `403` when the payload's `tenant_id`
   contradicts it at `app/Http/Controllers/AgentController.php:53-55`.

3. **Lead lookup or create** — `app/Http/Controllers/AgentController.php:60-74`. The lookup is
   explicitly scoped `->where('tenant_id', $tenantId)` at
   `app/Http/Controllers/AgentController.php:62`; a miss creates the `Lead` at `:66-71`. An existing
   lead with no agent adopts the requested `agent_id` at `:72-74`. Then
   `ContactSyncService::syncFromLead($lead, Contact::SOURCE_AGENT_API)` at
   `app/Http/Controllers/AgentController.php:76`, followed by `$lead->refresh()` at `:77`.

4. **Model turn, inline in the request** — `app/Http/Controllers/AgentController.php:79` calls
   `AgentService::process($lead, $request->message)` (`app/Services/AgentService.php:44`) with **no**
   `$interactionId` argument, so the correlation id is minted *inside* the service at
   `app/Services/AgentService.php:46`. `process()` then: returns `null` immediately for an
   opted-out lead (`app/Services/AgentService.php:48-59`); stamps `last_interaction_at` and
   `last_inbound_at` (`:64-67`); starts the `ai_runs` row via `AiRunRecorder::start()`
   (`app/Services/AgentService.php:112-117`); continues or opens the agent conversation
   (`:119-152`); applies the fact-check guardrail at `app/Services/AgentService.php:165`
   (`applyFactCheckGuardrail()`, `:322`); and flushes buffered evidence in `finally`
   (`app/Services/AgentService.php:275-282`).

5. **JSON response** — `app/Http/Controllers/AgentController.php:81` returns
   `{"response": <string|null>}`. For `/api/aria`, `app/Http/Controllers/AgentController.php:35-37`
   then attaches `Deprecation: true` and the `Link` successor-version header to that same response
   object. **The chain ends here.** No job is dispatched, no outbox row is written, and the response
   body carries no correlation id, so the caller cannot join its request to `<interaction_id>`.

---

## 3. Golden Trace

One successful turn: an integrator posts one message for a known `whatsapp` + tenant, the agent
answers, `200` is returned with the answer in the body.

**Join key.** The `<interaction_id>` UUID minted at `app/Services/AgentService.php:46` — note this is
minted *inside the service*, unlike Path 1 where the controller mints it at the door. It threads into
`AiRunRecorder::start(runId: …)` (`app/Services/AgentService.php:112-117`),
`AgentInteractionContext` (`:94-100`, which is how `AuditLogMiddleware` sees it), every buffered
`agent_interaction_events` row, and the Langfuse trace id. It is **never returned to the caller**.

| Evidence table | Rows for one successful turn | Notes |
|---|---|---|
| `agent_interaction_events` | **Several rows**, one trail keyed by `<interaction_id>` | `agent_started` (`app/Services/AgentService.php:82-92`), `context_synced` when prior timeline rows were pending (`:125-131`), `model_called` per LLM call from `AuditLogMiddleware` (`app/Ai/Middleware/AuditLogMiddleware.php:57-70`), `fact_check_passed` or `fact_check_failed` (`app/Services/AgentService.php:326-331`, `:342-349`), and `agent_response_ready` (`:192-198`). All buffered and written in one bulk insert by the `finally` flush at `app/Services/AgentService.php:279`. Every row carries `tenant_id` as a string. **No `outbound_queued` and no `outbound_sent` row is ever produced on this path** — those event types belong to the outbox, which is never reached. |
| `ai_runs` | **One row**, `run_id = <interaction_id>` | Path 2 goes through `AgentService::process()`, the only caller of `AiRunRecorder::start()` (`app/Services/AgentService.php:112-117`). Carries `started_at`, `duration_ms`, `estimated_cost_usd`, `architecture_version`, and `tenant_id` as a string. Path 2 shares this strength with Path 1 and is the reason its cost/latency evidence is intact while its delivery evidence is not. |
| `whatsapp_outbox_messages` | **No row** | Stated explicitly because absence is the defining fact of this path: `AgentController::handle()` returns the answer as JSON at `app/Http/Controllers/AgentController.php:81` and **the calling system, not Tenaz, sends the message**. `WhatsappOutboxService` is never constructed, never called, and has no entry point here. Tenaz therefore holds no record that the answer was ever delivered, refused, or dropped. |
| `campaign_messages` | **No row** | Written only by Path 4. |
| `followup_messages` | **No row** | Written only by Path 3. Note the indirect coupling: `AgentService::process()` stamps `last_inbound_at` at `app/Services/AgentService.php:64-67`, so a direct-API call *does* reset Path 3's recent-inbound skip guard (`app/Jobs/ProcessLeadFollowUpJob.php:126-153`) for that lead. |
| `voice_campaign_calls` | **No row** | Written only by Path 5. |

**Supporting rows outside the six tables** (named because a Phase 62 reader will look for them):
a `leads` row is created on first contact (`app/Http/Controllers/AgentController.php:66-71`) and a
`contacts` row is synced (`:76`). `agent_conversation_messages` gains the turn's user and assistant
rows from inside `laravel/ai`'s own conversation store. **`conversation_timeline_messages` gains no
row at all** — nothing on this path writes one, because `IncomingConversationPersister` is a Path 1
component; `ConversationContextSynchronizer::markTurnSynced()`
(`app/Services/ConversationContextSynchronizer.php:135-163`), called at
`app/Services/AgentService.php:162`, therefore updates zero rows here. Likewise **no
`conversation_sessions` (atendimento) row** is opened. A conversation driven entirely through this
path is invisible in the operator-facing Conversas timeline.

---

## 4. Failure Map

| ID | Failure mode | Trigger | Current behaviour | Evidence produced | Labeled finding |
|---|---|---|---|---|---|
| P2-F01 | **No concurrency guard of any kind for rapid repeated calls with the same `whatsapp` + `tenant_id`** | Two requests for the same lead in flight, or arriving back to back faster than a turn completes | This is **strictly worse than Path 1**, which at least serializes with `WithoutOverlapping("incoming_msg_{tenantId}_{phone}")`. Here there is no lock, no queue, no debounce, and no collection window: `AgentController::handle()` (`app/Http/Controllers/AgentController.php:40-82`) runs to completion per request and calls `AgentService::process()` inline at `:79`. Two workers can be inside the same lead's model turn simultaneously. Nothing detects it, nothing serializes it, and both callers receive an answer. | Two independent `<interaction_id>` trails and two `ai_runs` rows for the same lead with overlapping `started_at`/`ended_at`. That overlap is the *only* residue, and nothing reads it. | — (new; recorded here, not fixed in this phase) |
| P2-F02 | **Unguarded `conversation_id` race** | Two simultaneous first-contact requests for the same lead | Both requests take the "no conversation yet" branch and each writes `$lead->update(['conversation_id' => $response->conversationId])` — `app/Services/AgentService.php:151` (new conversation) and `app/Services/AgentService.php:147` (recovery path after a failed `continue()`, which also nulls it at `:145`). Last write wins; the losing conversation is orphaned with its history stranded, and the next turn continues from whichever id survived. No lock, no `updateOrCreate` guard, no compare-and-set. | Nothing. The orphaned `agent_conversation_messages` rows remain but no row records that a conversation id was overwritten. | — (new) |
| P2-F03 | **D-06 / D-07 / D-17 cannot be enforced by Tenaz on this path at all — the send boundary is outside the application** | Any turn whose answer stops being current before the integrator sends it | Tenaz's involvement ends at `app/Http/Controllers/AgentController.php:81`. There is no outbox row, no send job, and no callback: what the integrator does with `{"response": …}` — send it, delay it, drop it, send it an hour later — is unobservable and unstoppable. D-17's "checked again at the real send boundary" has **no boundary to attach to**. **Consequence for Phase 62:** the future contract for **D-18** must signal staleness *in the API response itself* — for example a supersession marker and the `<interaction_id>` in the response body, so an integrator can be contractually required to discard a superseded answer — because there is no WhatsApp send for Tenaz to gate. A Phase 62 design that only adds send-boundary ownership checks will leave this path uncovered. | None on Tenaz's side. Delivery, non-delivery and late delivery are indistinguishable in every table. | — (new; the D-18 half of the phase's headline gap) |
| P2-F04 | **The response carries no correlation id, so the caller cannot participate in supersession** | Any integration that would need to report or reconcile which turn it sent | `app/Http/Controllers/AgentController.php:81` returns exactly `{"response": …}`. `<interaction_id>` is minted inside the service (`app/Services/AgentService.php:46`) and never surfaces. Even if Phase 62 defines a staleness contract, today's response shape has no field to carry it and no id for a caller to echo back. | `ai_runs` and `agent_interaction_events` hold the id; the integrator holds nothing. Correlation is impossible from the caller's side. | — (new; prerequisite for the D-18 contract in P2-F03) |
| P2-F05 | **Rate limiting is mistakable for a guard, and its key does not match the auth mechanism** | Sustained traffic from one integrator | `throttle:tenaz-direct` / `throttle:aria-direct` (`routes/api.php:42`, `:47`) resolve to `Limit::perMinute(config('credflow.api.rate_limit', 120))` keyed by `X-API-Key` **or the client IP** (`app/Providers/AppServiceProvider.php:60-70`), while `AuthenticateApiKey` authenticates from the `Authorization: Bearer` token (`app/Http/Middleware/AuthenticateApiKey.php:16`). A conforming integrator that sends only the bearer token is therefore bucketed by source IP. This bounds volume; it neither serializes turns for one lead nor isolates tenants. | `429` responses only. Nothing lead-scoped or tenant-scoped. | — (new; low severity, recorded so P2-F01 is not "already mitigated by throttling") |
| P2-F06 | **The legacy `/api/aria` alias is fully live, with deprecation as advisory metadata only** | Any integrator that never migrated | `app/Http/Controllers/AgentController.php:33-38` calls the identical `handle()` and only decorates the response with `Deprecation: true` and a `Link` successor-version header. There is no sunset date, no `429`/`410` escalation, no per-alias metric, and no way to tell from the evidence tables which route a turn arrived on — the `agent_interaction_events` and `ai_runs` rows are byte-identical for both routes. Alias traffic is therefore unmeasurable, so "is anyone still using it" cannot be answered from Tenaz's own data. | None distinguishing the alias. Web-server access logs are the only discriminator. | — (new; low severity, but it blocks any evidence-based retirement decision) |
| P2-F07 | Bearer-token authentication and tenant binding are correct — a strength, not a gap | A wrong, missing, or other-tenant token | `resolveTenantForToken()` compares against every configured key with `hash_equals` and does not return early on the first match (`app/Http/Middleware/AuthenticateApiKey.php:32-43`), so response time does not leak which key matched — audit finding **F2**, fixed. A payload `tenant_id` contradicting the token is rejected `403` before any write (`app/Http/Controllers/AgentController.php:53-55`), and `Lead` lookup is tenant-scoped at `:62`. `tests/Feature/TenazApiTenantIsolationTest.php` covers the whole matrix. **Phase 62 must preserve this, not re-derive it.** | `401` / `403` responses; no `Lead` row is created on either rejection. | **F2** (fixed) |
| P2-F08 | **Financial tool output reaches the response with the fact-check guardrail applied — but the credit payload behind it is still unencrypted at rest** | Any turn where the agent calls the credit-lookup tool | Unlike Path 3, this path **does** run `applyFactCheckGuardrail()` (`app/Services/AgentService.php:165`, `:322`), so F1/F6 coverage is intact here. What is not fixed is the underlying storage: `credito_json` is cast as a plain array with no encryption, and the credit webhook is an unsigned POST. Recorded so the reader does not infer from "guardrail present" that this path is clean end to end. | The guardrail's own `fact_check_passed` / `fact_check_failed` events. Nothing records the at-rest exposure. | **F13** (still open, out of scope for Phase 61) |

---

## 5. Complementary-Message Collision Scenarios

All eight D-31 collision points, restated for this path. "Complementary message" here means a second
API call for the same `whatsapp` + `tenant_id`, since the integrator — not the customer — is the
immediate caller.

| # | Collision point | Applicable? | Current outcome |
|---|---|---|---|
| 1 | **Arrival during collection** | **Not applicable** | There is no collection window on this path. Nothing buffers, aggregates or debounces: `AgentController::handle()` processes each request immediately and in full. Two messages that Path 1 would merge into one turn become two independent turns here, with no aggregation and no record that they were adjacent. |
| 2 | **Arrival during model work** | **Applicable — the defining collision for this path** | Request A is inside `AgentService::process()` (`app/Http/Controllers/AgentController.php:79`). Request B arrives for the same lead. Nothing blocks it — no lock, no queue, no lease — so B runs concurrently, and both requests return a `200` with an answer. **Whichever response reaches the caller first is trusted as current by construction**: nothing in the protocol lets a late answer say "I am stale", and nothing lets the caller ask. Both turns also race on `conversation_id` (P2-F02). Pinned by `TenazApiCharacterizationTest`, which drives the sequential form of this collision and asserts that neither response is marked stale and that no supersession evidence exists. |
| 3 | **Arrival during internal work** | **Applicable** | Same shape as #2 with a longer window. Internal work means tool calls and the `ToolCallGuard` / `FactCheck` / `TokenBudget` middleware chain inside `process()`; a credit-lookup tool call can extend a turn by seconds. Nothing re-reads conversation state after a tool returns, and no state is re-read before the response is serialized at `app/Http/Controllers/AgentController.php:81`. |
| 4 | **Arrival during external action** | **Applicable, scoped to tool calls only** | The only external action Tenaz performs on this path is a tool call (credit lookup), which has its own circuit breaker but no supersession awareness — a lookup started for a superseded turn completes and its figures reach the answer. The *customer-facing* external action, the WhatsApp send, is outside the application entirely (P2-F03), so D-21/D-22's confirmed / proven-not-performed / uncertain trichotomy has nothing to attach to on this path. |
| 5 | **Arrival during response queueing** | **Not applicable** | No Tenaz-side send boundary on this path: nothing is queued. The answer is serialized into the HTTP response at `app/Http/Controllers/AgentController.php:81` in one step with no intermediate state a message could arrive "during". |
| 6 | **Arrival during partial send** | **Not applicable** | No Tenaz-side send boundary on this path: the answer is returned whole and is never split. `WhatsappOutboxService::queueSplitTextForLead()` is never called, so there is no multi-part sequence to interleave and D-23 has no parts to cancel. Whether the integrator splits the text before sending it is invisible to Tenaz. |
| 7 | **Arrival during retry** | **Not applicable** | No Tenaz-side send boundary on this path, and no queue: there is no retry inside the application. A `500` is returned to the caller and the retry decision is entirely the caller's. A caller-side retry re-enters as an ordinary new request and is indistinguishable from a genuine new message — the duplicate is admitted as a fresh turn, minting a fresh `<interaction_id>` and a fresh `ai_runs` row. |
| 8 | **Arrival during crash recovery** | **Applicable** | A crashed turn cannot deadlock the conversation, because no authority and no lock exist to be stranded (the accidental upside, identical in shape to Path 1's). The downside is sharper here: `AgentService` catches most throwables and returns fallback prose (`app/Services/AgentService.php:234-274`) that the integrator will send to the customer as if it were an answer, while `PDOException` / `InvalidArgumentException` rethrow into a `500` (`:207-214`). The `finally` block closes the `ai_runs` row (`:275-282`), so the evidence shows a finished run either way. Nothing records whether the customer received the fallback text, the real answer, or nothing at all. |

---

## 6. Latest-State Autonomy Comparison

| Decision | Verdict | Justification |
|---|---|---|
| **D-06** — a newer relevant message makes any unsent response obsolete immediately | `absent` | Nothing on this path evaluates relevance. `app/Http/Controllers/AgentController.php:79-81` returns whatever `process()` produced. There is not even a place to hook the check, because "unsent" is not a state Tenaz can observe here — the answer leaves the application the instant it is produced. |
| **D-07** — the obsolete execution stops at the next safe point and never regains permission to answer | `absent` | No stop concept and no permission concept. The only early return is the opt-out check at `app/Services/AgentService.php:48-59`, evaluated before the model call and unrelated to currency. |
| **D-08** — re-evaluate from the latest complete state; only the current execution answers | `absent` | Concurrent requests both answer (P2-F01). The path has no notion of a "current execution" and no lock that would let one exist. |
| **D-15** — one current execution authority per conversation, tied to the exact state | `absent` | Nothing mints, stores or checks an authority. Unlike Path 1, there is not even a job-level mutex to mistake for one — `WithoutOverlapping` has no analogue here. |
| **D-16** — authority is temporary and renewable; a new message revokes it, a crash expires it | `absent` | No authority exists to revoke or expire. The crash half is satisfied vacuously — a crashed request holds nothing, so nothing needs releasing — which is absence, not implementation. |
| **D-17** — authority checked throughout the cycle **and again at the real send boundary** | `absent` | Zero checks, and structurally no send boundary to add one to (P2-F03). This is the one path where implementing D-17 as written is impossible without changing the contract with the caller. |
| **D-18** — an outdated request must not return an old answer as if current | `absent` | **This is D-18's home path — the decision names `/api/tenaz` explicitly — and it is unimplemented.** The response shape is `{"response": …}` (`app/Http/Controllers/AgentController.php:81`): no staleness marker, no interaction id, no timestamp. A slow answer and a current answer are byte-identical to the caller. Flagged here as the decision that most directly shapes this path's future contract: because Tenaz has no send boundary, D-18 must be satisfied *in the response body and the published API contract*, not by gating a send. |
| **D-23** — cancel every not-yet-sent part as soon as the response becomes obsolete | `absent` | No mechanism exists, and on this path there is nothing for it to act on: the answer is never split into parts and never queued, so there is no not-yet-sent part under Tenaz's control. If the integrator splits the text before sending, those parts are outside the application and uncancellable by construction. |
| **D-24** — an already-sent part stays canonical history; the next execution sees it and continues naturally | `partial` | The memory half works: the answer is written into `agent_conversation_messages` by `laravel/ai`, so the next turn's `continue()` sees it and builds on it. The gap is that this history is **assumed, not observed** — Tenaz records the answer as though it were delivered while having no evidence that the integrator ever sent it. If the caller drops the answer, agent memory permanently contains a message the customer never received, and the next turn will continue as if it had. Note too that none of this reaches `conversation_timeline_messages`, so operators see nothing (§ 3). |
| **D-25** — serial processing alone is not evidence against stale or out-of-order replies | `absent` | On this path there is no serial processing to over-trust in the first place: turns for one lead can genuinely overlap (P2-F01). D-25's warning still binds Phase 62 in a stronger form — neither serialization *nor* the request/response shape may be counted as evidence of ownership, since the send that matters happens after Tenaz has stopped observing. |

---

## 7. Evidence Available Today

Per-path verdicts. These differ from the repository-wide table in `61-RESEARCH.md` § Evidence Field
Mapping, which is the starting reference, not the answer for this path. The pattern worth noticing:
Path 2's **model-side** evidence is as strong as Path 1's, and its **delivery-side** evidence is
absent outright rather than merely incomplete.

| # | Evidence field | Verdict | What exists on Path 2 |
|---|---|---|---|
| 1 | Collection window | `GAP` | No collection window exists to record (§ 5 #1). Two adjacent calls leave two unrelated trails with nothing linking them as one customer thought. |
| 2 | Execution start | `EXISTS` | `ai_runs.started_at` via `AiRunRecorder::start()` (`app/Services/AgentService.php:112-117`) plus the `agent_started` event (`:82-92`). Equal in strength to Path 1. |
| 3 | Supersession | `GAP` | No `execution_superseded` event type exists in the codebase. Pinned as a negative assertion by the characterization test. |
| 4 | Stop point | `GAP` | An execution never stops early for relevance. The opt-out return at `app/Services/AgentService.php:48-59` records why work never started, which is a different fact. |
| 5 | Restart count | `GAP` | D-11/D-12's restart concept does not exist, and there is no retry counter either — a caller-side retry is admitted as a new turn with a new run id (§ 5 #7), so repeated attempts are not even countable after the fact. |
| 6 | Preserved result references | `GAP` | No preservation or reuse concept. Every turn re-runs the model; tool results are not carried across turns with provenance. |
| 7 | External-action outcome | `GAP` | **The sharpest per-path difference from Path 1, where this field is `EXISTS`.** There is no `whatsapp_outbox_messages` row, no `status`, no `provider_attempted_at`, and no `outbound_sent` event, because the send happens outside Tenaz (P2-F03). Confirmed, proven-not-performed and uncertain are not merely unrecorded — they are unobservable. |
| 8 | Obsolete response blocked | `GAP` | Nothing blocks a response as obsolete, so there is nothing to record. No `response_blocked_stale` event type exists. |
| 9 | Execution that sent | `GAP` | **Also weaker than Path 1, which is `PARTIAL`.** Path 1 can at least answer "which execution sent this" after the fact through `whatsapp_outbox_messages.interaction_id`. Here there is no send record to correlate, and the response body carries no id for the caller to echo back (P2-F04), so the question is unanswerable from either side. |
| 10 | Elapsed time | `EXISTS` | `ai_runs.duration_ms` (`AiRunRecorder::finish()`, `app/Services/AiRunRecorder.php:71-94`). Note it measures the model turn only. On this path the model turn is very nearly the whole request, so it is a closer proxy for end-to-end latency than on Path 1 — but it still excludes whatever delay the integrator adds before sending. |
| 11 | Cost | `EXISTS` | `ai_runs.estimated_cost_usd`, per turn. Cost per *conversation cycle* (D-26/D-30) is not aggregated anywhere, and two racing turns for one lead (P2-F01) bill as two. |

---

## 8. Characterization Test Reference

**File:** `tests/Feature/TenazApiCharacterizationTest.php`

**Command:**

```
php artisan test --compact --filter=TenazApiCharacterization
```

The test is a Kent-Beck characterization oracle: it asserts the **current, undesirable** behaviour of
unmodified production code as a receipt for Phase 62, not a specification. It pins four facts — that
`/api/tenaz` answers synchronously and creates the lead under the token's tenant; that the legacy
`/api/aria` alias returns the same body and is still live, carrying only the advisory
RFC 8594 `Deprecation` header; that two sequential calls for the same `whatsapp` + `tenant_id` **both**
return a response body with neither marked stale or superseded; and that after those calls no
`whatsapp_outbox_messages` row exists for the lead and no `execution_superseded` event was recorded.

When Phase 62 gives this path a supersession contract, this file is **expected to fail** — most
likely at the assertion that both responses come back undifferentiated. It must then be rewritten
deliberately as part of that change, never quietly adjusted to describe the new behaviour, which
would erase the before/after this phase exists to create.

**Fixture-accuracy note.** The collision is driven as two sequential HTTP calls rather than two truly
concurrent ones. That is a deliberate under-statement of the real gap, not an approximation of it:
production has **no lock at all** (P2-F01), so genuine concurrency is possible and strictly worse
than what the fixture reproduces. The sequential form is sufficient to pin the fact the phase needs —
that the earlier answer is never marked superseded by the later one — while remaining deterministic
under `RefreshDatabase` and a single-process test runner. `AgentService` is mocked into the container
following the established `tests/Feature/ApiAgentRenameTest.php` shape, so the **real** middleware,
route, controller, tenant gate and persistence run; only the model call is stubbed.
