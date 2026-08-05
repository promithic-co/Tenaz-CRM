# Path 6 — Manual/Test Invocation Through Playground

Requirement: RUNT-01
Success criteria: SC3, SC4
Schema version: 1
Characterized: 2026-08-05
Characterization test: PlaygroundCharacterization

> D-29 binds this document. No raw phone number, message body, CPF, financial figure, credential
> or tool payload appears here. Identifiers are written as placeholders (`<lead_id>`, `<tenant_id>`,
> `<operator_id>`, `<conversation_id>`) and behaviour is described by classification, length and
> status.

> **This dossier carries audit finding [F16](#f16), still open.** Playground constructs its agent
> with `new CredFlowAgent(...)` directly (`app/Actions/RunPlaygroundChatAction.php:28`), bypassing
> `AgentFactory`, `AgentService`, `FactCheckService`, `AiRunRecorder` and
> `AgentInteractionEventService` all at once. The consequence characterized here is that a
> Playground turn is **invisible to the first-party evidence system** — no `ai_runs` row and no
> `agent_interaction_events` row — while remaining visible to Langfuse and to the daily usage
> aggregate. F16 is **documented, not fixed**, in Phase 61. See § 4.

---

## 1. Entry & Trigger

**Trigger.** A super-admin operator typing into the Playground UI. `POST` to
`backoffice.playground.chat` (`routes/backoffice.php:93`) reaches
`PlaygroundController::chat()` (`app/Http/Controllers/PlaygroundController.php:135-144`). There is
no customer, no external system, no schedule and no queue — this is the only human-driven model
invocation surface in the inventory.

**Authentication and authorization — the strongest gate on any path.** Four layers, all present:

1. `auth` — an authenticated session is required (`routes/backoffice.php:22`).
2. `super_admin` — `EnsureSuperAdmin` (`app/Http/Middleware/EnsureSuperAdmin.php`) aborts `401`
   without a user and `403` without `is_super_admin`. Not a feature gate: the route group's own
   comment records that every run bills the platform account
   (`routes/backoffice.php:77-79`).
3. `backoffice.context` — `ShareBackofficeContext` (`routes/backoffice.php:22`) resolves the active
   tenant the super-admin is acting as, which is what `$user->tenantId` then returns.
4. `throttle:30,1` — applied to exactly the five LLM-invoking Playground routes
   (`routes/backoffice.php:90-96`), abuse control from audit finding **F8**, fixed.

Route-model binding adds a fifth: the `Lead` global tenant scope 404s a cross-tenant lead before the
controller's own `sandbox` policy check is reached, and the policy then 403s a same-tenant
non-sandbox lead. Both are already characterized by the pre-existing tests in this file. The
backoffice prefix is additionally environment-configurable (`config('backoffice.path')`,
`routes/backoffice.php:17-21`) as an obscurity layer *on top of* the gate, explicitly not a
replacement for it.

**Tenant resolution.** From the authenticated operator's active-tenant context
(`$user->tenantId`), used to scope sandbox-lead listing and creation
(`app/Http/Controllers/PlaygroundController.php:43-46`, `:201`, `:207`). The chat turn itself
operates on a `Lead` with `is_sandbox = true`, so no real customer record is touched.

**Synchronous vs queued.** **Fully synchronous, in-request.** `RunPlaygroundChatAction::execute()`
(`app/Actions/RunPlaygroundChatAction.php:22-93`) calls the model inline and the controller returns
its result as JSON (`app/Http/Controllers/PlaygroundController.php:143`). No queue is involved on
the request path at all; the only asynchronous work is the fire-and-forget `LogAiUsageJob` dispatched
from the middleware (§ 3).

**No real WhatsApp send.** `WhatsappOutboxService` is never called, no `WhatsappOutboxMessage` row is
created and no provider is contacted. The reply is returned to the operator's browser and persisted
only as conversation history. Pinned by a `doesntExist()` assertion so the sandbox boundary cannot
erode silently.

**Model override, allow-listed.** `ChatSandboxRequest`
(`app/Http/Requests/Playground/ChatSandboxRequest.php:20-23`) validates `model_override` with
`Rule::in(config('credflow.agent.playground_models'))` against a fixed twelve-model list
(`config/credflow.php:29-35`). Audit finding **F8**, fixed; recorded in § 4 rather than re-verified
by new code here, since `tests/Feature/PlaygroundProviderAllowlistTest.php` already covers it.

---

## 2. Ordered Call Chain

1. **Middleware stack and validation** — `routes/backoffice.php:22` applies
   `auth` + `super_admin` + `backoffice.context`; `routes/backoffice.php:90-96` wraps the five
   LLM routes in `throttle:30,1`; `ChatSandboxRequest`
   (`app/Http/Requests/Playground/ChatSandboxRequest.php:10-24`) authorizes via the `sandbox`
   policy and validates `message` (required, ≤ 2000 chars) and the allow-listed `model_override`.
   `PlaygroundController::chat()` (`app/Http/Controllers/PlaygroundController.php:135-144`) then
   delegates immediately.

2. **Direct agent construction — the bypass** —
   `app/Actions/RunPlaygroundChatAction.php:28`:
   `new CredFlowAgent($lead, $lead->sandbox_system_prompt ?: null)`. `AgentFactory` is not
   consulted, so none of the factory's resolution logic (agent selection, niche template, tenant
   configuration) applies, and the sandbox system prompt overrides `instructions()` wholesale
   (`app/Ai/Agents/CredFlowAgent.php:20-23`). An optional
   `withModelOverride()` follows at `app/Actions/RunPlaygroundChatAction.php:30-33`. This is
   [F16](#f16).

3. **The model turn** — `app/Actions/RunPlaygroundChatAction.php:35-42`: either
   `$agent->continue($lead->conversation_id, as: $lead)->prompt($message)` (`:36-38`) for an
   existing conversation, or `$agent->forUser($lead)->prompt($message)` (`:40`) for a new one,
   in which case the minted `conversationId` is persisted onto the lead (`:41`).
   **`AgentService::process()` is never called** — the file contains no reference to it. The whole
   call is wrapped in one `try/catch (Throwable)` that converts any failure into a `500` envelope
   carrying the raw exception message (`app/Actions/RunPlaygroundChatAction.php:83-92`).

4. **Debug assembly and JSON return** —
   `app/Actions/RunPlaygroundChatAction.php:44-82` computes a wall-clock `duration`, reads
   `promptTokens`/`completionTokens` off the response usage, flattens `steps` into a
   `tool_calls` list (`:51-66`), and returns
   `{reply, messages, debug: {tokens_in, tokens_out, duration, steps, tool_calls, model}}`
   (`:68-82`), which `PlaygroundController::chat()` serialises at
   `app/Http/Controllers/PlaygroundController.php:143`. **This `debug` object is the only
   per-turn evidence this path produces that a human can see**, and it is not persisted anywhere.

---

## 3. Golden Trace

One successful execution: a super-admin sends one message to a sandbox lead and the agent replies.

**Join key — there is none.** No `<interaction_id>` is minted anywhere on this path.
`AgentInteractionEventService::newInteractionId()` is never called, and
`AgentInteractionContext::set()` is called from exactly two places in the codebase —
`app/Services/AgentService.php:35` (constructor-injected, set inside `process()`) and
`app/Jobs/ProcessLeadFollowUpJob.php:70` — neither of which this path reaches. The only durable
correlator is `<conversation_id>` on the `agent_conversations` / `agent_conversation_messages`
tables, which is `laravel/ai`'s own storage, not this system's evidence layer.

**The split evidence picture — the precise mechanism.** `AuditLogMiddleware` is attached at the
**agent** level, not behind `AgentService`: `BaseCustomerServiceAgent::middleware()` registers
`ToolCallGuardMiddleware`, `AuditLogMiddleware`, `TokenBudgetMiddleware`
(`app/Ai/Agents/BaseCustomerServiceAgent.php:137-144`), and `CredFlowAgent` extends that base
without overriding `middleware()`. So the middleware **does** run on a Playground turn. What it
then does splits in two at one line:

- **Gated on the interaction id** (`app/Ai/Middleware/AuditLogMiddleware.php:48`) — and therefore
  **skipped** here, because `interactionId()` returns `null`
  (`app/Services/AgentInteractionContext.php:23-28`): `AiRunRecorder::recordModelCall()`
  (`app/Ai/Middleware/AuditLogMiddleware.php:49-55`) and the `model_called` interaction event
  (`:57-70`). Both are *not called at all* on this path.
- **Unconditional** (`app/Ai/Middleware/AuditLogMiddleware.php:73-102`) — and therefore **fired**:
  `LogAiUsageJob::dispatch()` (`:73-79`), which upserts an `ai_usage_daily` row keyed by tenant,
  agent, model and date; and `LangfuseService::logAgentLlmTurn()` (`:85-102`), whose `traceId`
  falls back to a throwaway `Str::uuid()` at `:86` precisely because no interaction id exists,
  and whose metadata carries `is_sandbox` (`:100`). An `aria.audit` log line is written before
  either (`:32-41`).

**This is where Path 6 differs from Path 3.** The follow-up path also bypasses `AgentService`, but
its job *does* set the interaction context (`app/Jobs/ProcessLeadFollowUpJob.php:70`, `:201-207`),
so `model_called` fires there and the trail is *partly* populated
([F19](path-3-followup-scheduler.md#f19)). Playground sets nothing, so the trail is **entirely**
empty. Even if `AiRunRecorder::start()` were somehow called, `recordModelCall()` would still be
skipped here by the same gate — the ledger gap on this path has two independent causes, not one.

| Evidence table | Rows for one successful turn | Notes |
|---|---|---|
| `agent_interaction_events` | **No row** | [F16](#f16), trail half. `AgentInteractionEventService` is never invoked from `RunPlaygroundChatAction` or `PlaygroundController::chat()`, and the middleware's own write is gated on an interaction id that is never set (`app/Ai/Middleware/AuditLogMiddleware.php:48`). Pinned by a `doesntExist()` assertion. |
| `ai_runs` | **No row** | [F16](#f16), ledger half. `AiRunRecorder::start()` is called from exactly one place in the codebase, `app/Services/AgentService.php:112-117`, which this path never reaches — and `recordModelCall()` is not even attempted here (above). Per-turn duration, cost, token counts, model name, prompt hash and outcome are therefore unrecoverable for every Playground turn, and Playground spend is excluded from Laboratory's `aiRunSummary()` and `architectureComparison()`. Pinned by a `doesntExist()` assertion. |
| `whatsapp_outbox_messages` | **No row** | By design, and a strength: no real send can escape the sandbox. Pinned positively so a Phase 62 wiring change cannot silently grant this path an outbound channel. |
| `campaign_messages` | **No row** | Written only by Path 4. |
| `followup_messages` | **No row** | Written only by Path 3. A sandbox lead is excluded from follow-up eligibility. |
| `voice_campaign_calls` | **No row** | Written only by Path 5. |

**Supporting rows outside the six tables:** `agent_conversations` and
`agent_conversation_messages` gain the turn's user and assistant rows through `laravel/ai`'s own
conversation storage (the tables the pre-existing tests in this file already characterize);
`ai_usage_daily` gains or increments one row per tenant/agent/model/day via `LogAiUsageJob`
(`app/Jobs/LogAiUsageJob.php:16-40`), which is **additive and `tries = 1`** so it is not
per-turn-attributable; and one Langfuse trace is posted to an external system under a throwaway
id. **The returned `debug` JSON — tokens in/out, duration, steps, tool calls, model
(`app/Actions/RunPlaygroundChatAction.php:73-80`) — is the only first-party per-turn evidence
available, and it is never persisted.**

---

## 4. Failure Map

<a id="f16"></a>
<a id="p6-f02"></a>
<a id="p6-f03"></a>
<a id="p6-f04"></a>
<a id="p6-f05"></a>

| ID | Failure mode | Trigger | Current behaviour | Evidence produced | Labeled finding |
|---|---|---|---|---|---|
| P6-F01 | **`AgentFactory` is bypassed by direct construction, so the Playground path is invisible to the first-party evidence system** | Every Playground turn, unconditionally | `RunPlaygroundChatAction::execute()` builds `new CredFlowAgent($lead, …)` at `app/Actions/RunPlaygroundChatAction.php:28` and prompts it directly at `:36-40`. `AgentService::process()` is never called, so `AiRunRecorder::start()` never runs and no `ai_runs` row exists; `AgentInteractionEventService` is never invoked at all, and the middleware's own event write is additionally gated on an interaction id this path never sets (`app/Ai/Middleware/AuditLogMiddleware.php:48`), so no `agent_interaction_events` row exists either. Confirmed by grep, the direct-construction sites are: `app/Actions/RunPlaygroundChatAction.php:28`, `app/Http/Controllers/PlaygroundController.php:171`, `app/Http/Controllers/PlaygroundController.php:210`, `app/Services/EvaluatePlaygroundRunService.php:38`, `app/Services/StressTestOrchestrator.php:64`, `app/Services/StressTestOrchestrator.php:78`, `app/Services/StressTestOrchestrator.php:176`, and `app/Services/LeadAutoTaggingService.php:78` — eight sites, two more than the original audit's "6+". **Disposition: documented, not fixed in Phase 61.** Routing Playground through `AgentFactory`/`AgentService` would change runtime behaviour (it would add the fact-check guardrail, the `AiRun` row and the interaction trail to a surface that has never had them), which a characterization phase must not do. Phase 62/63 owns it. | Nothing in the six evidence tables. Externally: an `aria.audit` log line (`app/Ai/Middleware/AuditLogMiddleware.php:32-41`), a Langfuse trace under a throwaway id (`:85-102`), and an additive `ai_usage_daily` upsert (`:73-79`). Both absences are measured, not argued: `PlaygroundCharacterizationTest` asserts `AiRun::doesntExist()` and `AgentInteractionEvent::doesntExist()` after a real controller round-trip. | **F16** (still open; documented-not-fixed, Phase 62) |
| P6-F02 | Playground `model_override` allow-list and route throttle | An operator attempting an arbitrary or expensive model | **Already fixed.** `ChatSandboxRequest` validates `model_override` with `Rule::in(config('credflow.agent.playground_models'))` (`app/Http/Requests/Playground/ChatSandboxRequest.php:22`) against the twelve-model list at `config/credflow.php:29-35`; `GenerateScenarioRequest` and `ScanBlindspotsRequest` carry the same rule; and all five LLM-invoking routes sit behind `throttle:30,1` (`routes/backoffice.php:90-96`). Recorded here as fixed rather than re-verified by new code — `tests/Feature/PlaygroundProviderAllowlistTest.php` already covers it and was re-run green during `61-RESEARCH.md`. | The `422` validation response; the `429` throttle response. | **F8** (fixed) |
| P6-F03 | Access control on the only human-driven model surface | A non-super-admin reaching a Playground LLM route | `auth` + `super_admin` + `backoffice.context` (`routes/backoffice.php:22`) with `EnsureSuperAdmin` aborting `401`/`403` (`app/Http/Middleware/EnsureSuperAdmin.php:16-27`), plus route-model binding's tenant scope and the `sandbox` policy. Healthy; this plan adds a regression assertion that a non-super-admin is rejected **before the model is reached**, so the boundary cannot erode silently. | `403` with no model call and no evidence row of any kind. Pinned by the characterization test. | — (healthy; keep. Threat T-61-23, ASVS V4) |
| P6-F04 | **A model failure returns the raw exception message to the operator and records nothing** | Any throwable inside the model turn | `app/Actions/RunPlaygroundChatAction.php:83-92` catches every `Throwable` and returns `{'reply': 'Erro no agente: '.$e->getMessage(), 'debug': {'error': …}}` with status `500`. The message is not sanitised the way `MetaApiException::sanitizeMessage()` sanitises provider errors elsewhere, so a provider error string can surface verbatim in the operator's browser. And because no event or run row is written, a failed turn leaves **no** first-party record at all — not even the `agent_failed` event `AgentService` would have produced. Low exploitability (super-admin only), recorded because Phase 62 will need a failure contract for this surface, not just a success one. | The `500` JSON envelope, and nothing durable. | — (new; low severity) |
| P6-F05 | **The `debug` payload is the only per-turn evidence and it is never persisted** | Every Playground turn | Tokens, duration, step count and tool calls are computed inline (`app/Actions/RunPlaygroundChatAction.php:44-66`) and returned to the browser (`:73-80`). Nothing writes them to a table. Two consequences for later phases: cost and latency for the operator-driven surface cannot be compared against production traffic, and — the sharper one — **a Playground-driven collision fixture would have no evidence to inspect**. See the D-31 note below. | The HTTP response body only. | — (new) |

### 4.1 Forward-looking note for D-31

`61-CONTEXT.md` contemplates Playground as a controlled surface for reproducing the D-31 collision
scenarios. This dossier's evidence gap constrains that plan concretely: a Playground-driven
collision fixture would produce **no `agent_interaction_events` trail, no `ai_runs` row and no
outbox rows** to inspect, so it would have to either (a) carry its own evidence-capture wiring —
which is itself a runtime change and therefore Phase 62 work — or (b) rely on the returned `debug`
JSON alone, which is per-turn, unpersisted, and carries no supersession, ownership or send-boundary
information whatsoever.

There is a second constraint worth stating now: Playground is **synchronous and single-request**, so
the collision points that matter most on the queued paths (arrival during model work, during
response queueing, during partial send) have no natural analogue here. Reproducing them would mean
issuing two concurrent HTTP requests against the same sandbox lead, which exercises this path's own
(absent) concurrency guards rather than the queue semantics Phase 62 is designing for.

**This is a Phase 62/63 design input, not work for this phase.** Recorded so the option is chosen
with its cost visible rather than assumed to be free.

---

## 5. Complementary-Message Collision Scenarios

All eight D-31 collision points, restated for this path. Playground is synchronous, has no queue,
sends nothing to a customer, and has no complementary message to collide with — an operator types
one message and waits for one reply. Most points are therefore structurally inapplicable and are
marked as such with a reason rather than omitted.

| # | Collision point | Applicable? | Current outcome |
|---|---|---|---|
| 1 | **Arrival during collection** | **Not applicable** | There is no collection window and no debounce: the operator's message goes straight to the model in the same HTTP request (`app/Actions/RunPlaygroundChatAction.php:35-42`). `DebounceService` is not on this path. |
| 2 | **Arrival during model work** | **Not applicable in the D-31 sense — but the underlying hazard is worse here** | There is no second sender: the operator is the only participant and their browser blocks on the response. What *can* happen is the operator issuing a second request before the first returns, and this path has **no concurrency guard of any kind** — no `WithoutOverlapping`, no uniqueness key, no lock. Two concurrent turns for one sandbox lead would race on `$lead->update(['conversation_id' => …])` (`app/Actions/RunPlaygroundChatAction.php:41`), the same unguarded write Path 2 has. Marked not applicable because no complementary *customer* message exists, not because the path is safe. |
| 3 | **Arrival during internal work** | **Not applicable** | Same reason as #2. Tool calls do run — `CredFlowAgent` carries `ConsultarCreditoInssTool` — and `ToolCallGuardMiddleware` bounds the loop, but there is no second message stream to arrive during them. |
| 4 | **Arrival during external action** | **Not applicable for the send; applicable for tool calls** | No WhatsApp send exists on this path, so the principal external action is absent. Credit-lookup tool calls invoked inside the turn *are* real external effects with a real circuit breaker (`app/Ai/Tools/AbstractConsultaCreditoTool.php`) — and they hit the same upstream endpoint production traffic does, from a sandbox lead. Recorded because "Playground is a sandbox" is true of the WhatsApp side and only partly true of the tool side. |
| 5 | **Arrival during response queueing** | **Not applicable** | Nothing is queued. The reply is returned as JSON in the same request (`app/Http/Controllers/PlaygroundController.php:143`); there is no interval between producing the answer and delivering it. |
| 6 | **Arrival during partial send** | **Not applicable** | The reply is not split into parts. `WhatsappOutboxService::queueSplitTextForLead()` — the source of the D-23 gap on Paths 1 and 3 — is never called; the full text is returned in one field. |
| 7 | **Arrival during retry** | **Not applicable** | There is no retry. The action catches every throwable and returns a `500` envelope (`app/Actions/RunPlaygroundChatAction.php:83-92`); no queue re-executes anything, and the operator's browser decides whether to try again. |
| 8 | **Arrival during crash recovery** | **Not applicable** | There is no execution to recover. A crashed request leaves no lock, no claim and no half-finished state — the accidental upside of having no durable execution record at all. The downside is the same gap as P6-F04: nothing records that the turn happened, so a crash is indistinguishable from a request never made. |

---

## 6. Latest-State Autonomy Comparison

The ten CONTEXT.md decisions against current behaviour on this path. Every decision presumes a
customer conversation whose state can change while work is in progress; Playground has one operator
and one blocking request. The verdicts are stated with the required vocabulary and the
justifications say why.

| Decision | Verdict | Justification |
|---|---|---|
| **D-06** — a newer relevant message makes any unsent response obsolete immediately | `absent` | No obsolescence concept exists. In practice the window is the narrowest of any path — the operator is blocked on the response — but "narrow by construction" is not "implemented", and nothing would detect a second concurrent request superseding the first. |
| **D-07** — the obsolete execution stops at the next safe point and never regains permission to answer | `absent` | There is no stop concept and no permission concept. The single `try/catch` (`app/Actions/RunPlaygroundChatAction.php:83-92`) is error handling, not supersession, and it always returns *something* to the caller. |
| **D-08** — re-evaluate from the latest complete state; only the current execution answers | `absent` | Nothing is re-evaluated and there is no notion of a current execution. `$agent->continue($lead->conversation_id, …)` (`:36-38`) reads the stored conversation once at prompt time. |
| **D-15** — one current execution authority per conversation, tied to the exact current state | `absent` | Nothing mints, stores or checks an authority, and unlike every queued path there is not even an incidental lock to mistake for one — no `WithoutOverlapping`, no uniqueness key, no cache claim. |
| **D-16** — authority is temporary and renewable; a new relevant message revokes it, a crash expires it | `absent` | No authority exists. The crash half is trivially satisfied because a synchronous request leaves nothing behind, but that is the absence of state, not the expiry of authority. |
| **D-17** — authority checked throughout the cycle **and again at the real send boundary** | `absent` | There is no send boundary on this path to check at — the reply is returned to the operator, not sent to a customer — so D-17 has no place to attach. Phase 62 should treat Playground as a path that will need a *new* boundary if it is ever to carry one, not as one whose boundary is unchecked. |
| **D-18** — an outdated request must not return an old answer as if current | `absent` | D-18 is written for the direct API path, and Playground is architecturally the same shape: a synchronous request whose response is trusted as current by construction. Two concurrent Playground requests would both return answers with nothing marking either stale. The difference from Path 2 is only that the consumer is a human operator who can see the ordering. |
| **D-23** — cancel every not-yet-sent part as soon as the response becomes obsolete | `absent` | There are no parts and no queue, so there is nothing to cancel. Recorded as `absent` rather than not-applicable because the *capability* is genuinely missing: an operator cannot abort an in-flight turn, and a closed browser tab neither stops the model call nor stops it billing. |
| **D-24** — an already-sent part stays canonical history; the next execution sees it and continues naturally | `partial` | The preservation half works through `laravel/ai`'s own conversation storage: the turn is written to `agent_conversations` / `agent_conversation_messages` and `$agent->continue($lead->conversation_id, …)` replays it on the next turn (`app/Actions/RunPlaygroundChatAction.php:36-38`), bounded by `maxConversationMessages()`. The gap: `ConversationContextSynchronizer::syncPending()` — the mechanism Paths 1 and 3 use to mirror operator and inbound turns into agent memory — is **not** on this path, so a sandbox lead's `conversation_timeline_messages` and its agent memory can diverge with nothing reconciling them. |
| **D-25** — serial processing alone is not evidence against stale or out-of-order replies | `absent` | This path has no serial processing at all: concurrent requests for one sandbox lead are unguarded (§ 5 #2). D-25's warning binds Phase 62 here in its simplest form — the fact that a human operator normally waits for a reply is a usage convention, not a system guarantee, and must not be counted as evidence of ownership. |

---

## 7. Evidence Available Today

Per-path verdicts. These differ from the repository-wide table in `61-RESEARCH.md` § Evidence Field
Mapping, which is the starting reference, not the answer for this path. Note the shape: this is the
only path where every one of the eleven fields is a `GAP`, and where the reason is a **single**
structural cause ([F16](#f16)) rather than several independent ones.

| # | Evidence field | Verdict | What exists on Path 6 |
|---|---|---|---|
| 1 | Collection window | `GAP` | No collection window exists (§ 5 #1), so nothing is recorded. |
| 2 | Execution start | `GAP` | **Differs from Paths 1, 2 and 3, where this field is `EXISTS` or `PARTIAL`.** No `ai_runs.started_at` (no run) and no `agent_started` event (`AgentInteractionEventService` is never invoked). `microtime(true)` is captured at `app/Actions/RunPlaygroundChatAction.php:24` purely to compute the returned `duration` and is never persisted. The turn's start time exists only inside the request. |
| 3 | Supersession | `GAP` | No `execution_superseded` event type exists in the codebase, and this path writes no events at all. |
| 4 | Stop point | `GAP` | An execution never stops for relevance. A failed turn returns a `500` envelope and records nothing (P6-F04), so not even "it failed" is durably captured. |
| 5 | Restart count | `GAP` | No retry mechanism exists on this path (§ 5 #7), so there is nothing to count. An operator re-sending manually is indistinguishable from a first attempt. |
| 6 | Preserved result references | `GAP` | No preservation or reuse concept. Tool results are flattened into the returned `tool_calls` array (`app/Actions/RunPlaygroundChatAction.php:51-66`) and discarded with the response. |
| 7 | External-action outcome | `GAP` | **The one path where this field is a `GAP` for a benign reason:** the principal external action — the WhatsApp send — does not exist here, so there is correctly no outcome to record. Credit-lookup tool calls *are* real external effects (§ 5 #4) and their outcomes are equally unrecorded, which is not benign. Do not read this `GAP` as equivalent to Path 5's. |
| 8 | Obsolete response blocked | `GAP` | Nothing blocks a response as obsolete, so there is nothing to record. |
| 9 | Execution that sent | `GAP` | No execution identifier exists — no `<interaction_id>` is minted anywhere on this path (§ 3) — and nothing is sent. The question is not merely unadjudicated, it is unanswerable. |
| 10 | Elapsed time | `GAP` | A wall-clock `duration` is computed (`app/Actions/RunPlaygroundChatAction.php:44`) and returned in `debug` (`:76`), and `AuditLogMiddleware` computes its own and ships it to Langfuse and the `aria.audit` log (`app/Ai/Middleware/AuditLogMiddleware.php:29`, `:40`, `:96`). Neither reaches a queryable table, because `AiRunRecorder` is not called (P6-F01). Durations exist in three places and none of them is the ledger every Laboratory view reads. |
| 11 | Cost | `GAP` | `estimated_cost_usd` is computed only by `AiRunRecorder::recordModelCall()`, which is never called here. Token counts do reach the `LogAiUsageJob` daily aggregate (`app/Ai/Middleware/AuditLogMiddleware.php:73-79`) and Langfuse, so Playground spend is not invisible **in total** — but it cannot be attributed to a turn, an operator, or an experiment, and it is excluded from `aiRunSummary()` and `architectureComparison()`. Since the route group's own comment records that every Playground run bills the platform account (`routes/backoffice.php:77-79`), this is the surface where per-turn cost attribution matters most and exists least. |

---

## 8. Characterization Test Reference

**File:** `tests/Feature/PlaygroundCharacterizationTest.php`

**Command:**

```
php artisan test --compact --filter=PlaygroundCharacterization
```

**Two concerns share this file, deliberately.** It already existed as the Phase D
`PlaygroundController` refactor oracle, and `61-VALIDATION.md`'s Wave 0 note requires the
established `{Subject}CharacterizationTest` convention be **extended rather than duplicated**.
Phase 61 appended three tests under the heading `Phase 61 — RUNT-01 path 6 evidence gap` and
extended the file's docblock to say why; no pre-existing test was modified or removed.

The Phase 61 additions are a Kent-Beck characterization oracle: they assert the **current,
undesirable** behaviour of unmodified production code as a receipt for Phase 62, not a
specification. They pin three facts — **[F16](#f16)'s practical consequence, that a real controller
round-trip producing a real reply and a real `debug` envelope writes no `ai_runs` row and no
`agent_interaction_events` row at all**; that the sandbox boundary holds, with no
`whatsapp_outbox_messages` row created (asserted positively, because Phase 62 must not gain an
outbound channel here while gaining a ledger row); and that a non-super-admin is rejected with `403`
before the model is reached, so the access-control boundary of the only human-driven model surface
cannot erode silently (threat T-61-23).

When Phase 62 closes F16 by routing Playground through `AgentFactory`/`AgentService`, the first of
those is **expected to fail** at both `doesntExist()` assertions. It must then be rewritten
deliberately as part of that change, never quietly adjusted to describe the new behaviour, which
would erase the before/after this phase exists to create. The outbox and authorization assertions
are **not** expected to fail and should survive that change unchanged.

**Fixture-accuracy note.** The model is stubbed at the narrowest available seam —
`Ai::fakeAgent(CredFlowAgent::class, …)`, the idiom already established in this file for
`EvaluatorAgent`, `BlindspotScannerAgent` and `ScenarioGeneratorAgent` — so the real route stack
(`auth` + `super_admin` + `backoffice.context` + `throttle:30,1`), the real `ChatSandboxRequest`,
the real `PlaygroundController::chat()`, the real `RunPlaygroundChatAction` and the real
`new CredFlowAgent(...)` construction all execute. Only the provider call is replaced. No production
file was modified to make this possible, which is itself part of the receipt: the gap is reachable
without any seam being introduced for it.

**Fixture-accuracy caveat.** The test asserts the absences, not the presences. That
`LogAiUsageJob` and `LangfuseService::logAgentLlmTurn()` **do** fire on this path is established by
code reading (§ 3), not by assertion — whether `Ai::fakeAgent` short-circuits the agent middleware
chain is an implementation detail of `laravel/ai` that a characterization test should not depend on.
The absences do not depend on it either way: neither `AiRunRecorder::start()` nor
`AgentInteractionEventService` is reachable from this path with or without the middleware running.
