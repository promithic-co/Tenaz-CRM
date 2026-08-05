# Path 3 — Follow-Up Scheduler

Requirement: RUNT-01
Success criteria: SC3, SC4
Schema version: 1
Characterized: 2026-08-05
Characterization test: FollowUpSchedulerCharacterization

> D-29 binds this document. No raw phone number, message body, CPF, `credito_json` value, financial
> figure, credential or tool payload appears here. Identifiers are written as placeholders
> (`<lead_id>`, `<tenant_id>`, `<interaction_id>`) and behaviour is described by classification,
> length and status.

> **This dossier carries the phase's highest-value new finding, [F19](#f19).** The follow-up path
> resolves its agent directly and never calls `AgentService::process()`, so the F1/F6 fact-check
> guardrail never runs on a follow-up message and no `ai_runs` row is ever written. F19 is
> **documented, not fixed**, in Phase 61 — see § 4.

---

## 1. Entry & Trigger

**Trigger.** A cron schedule, not a customer and not an integrator. `routes/console.php:36`
registers `Schedule::command('credflow:check-followups')->everyFiveMinutes()->onOneServer()->withoutOverlapping(10)`,
which runs `CheckFollowUpsCommand` (`app/Console/Commands/CheckFollowUpsCommand.php:17`, signature
`credflow:check-followups`; `handle()` at `:21-31` delegating to `runCheck()` at `:33`). The command
sweeps eligible leads and dispatches one `ProcessLeadFollowUpJob` per lead onto the `followups`
queue (`app/Jobs/ProcessLeadFollowUpJob.php:50-53`). This is the only path in the inventory where
**Tenaz, not the customer, decides to speak**.

**Authentication.** None, and none is possible: this runs in console context with no authenticated
user. That makes the tenancy rules load-bearing rather than incidental.

**Tenant resolution.** From the serialized `Lead` alone. `BelongsToTenant`'s global scope is inert
in console, so every tenant-touching query must scope itself explicitly — and here they do:
`FollowupMessage::create()` sets `'tenant_id' => $this->lead->tenant_id`
(`app/Jobs/ProcessLeadFollowUpJob.php:284`, `:352`, `:403`), `resolveWhatsappInstance()` filters
`->where('tenant_id', $this->lead->tenant_id)` alongside `whereKey()`
(`app/Jobs/ProcessLeadFollowUpJob.php:374-384`), and the interaction context stamps
`(string) $this->lead->tenant_id` (`:203`). The job's own header comment
(`app/Jobs/ProcessLeadFollowUpJob.php:29-32`) states the reasoning. Note the deliberate
`withoutGlobalScopes()` at `:380` is paired with an explicit tenant filter — it widens the query to
console context, it does not silence a scope error.

**Synchronous vs queued.** Queued. Two queues in sequence: `followups` (the eligibility decision and
the model turn) and `outbox` (the real send, via `ProcessWhatsappOutboxMessageJob`). The model call
happens inside the queued job, not in the scheduler tick.

**Concurrency posture — the important asymmetry.** `ProcessLeadFollowUpJob` implements
`ShouldBeUniqueUntilProcessing` with `uniqueId() = "followup_{lead_id}"` and `uniqueFor = 600`
(`app/Jobs/ProcessLeadFollowUpJob.php:33`, `:48`, `:55-58`). That prevents **a second follow-up job
for the same lead**. It does nothing about a genuine new inbound customer message racing the turn.
There is **no `WithoutOverlapping`-equivalent guarding the model call itself** — Path 1 at least
serializes inbound processing per tenant+phone; this path has no such lock, so its unchecked window
is the longest of any path (§ 5 #2).

**Eligibility is decided once, up front.** Three gates run before any model work — follow-up still
`active` (`app/Jobs/ProcessLeadFollowUpJob.php:74-90`), the service/free-form window still open via
`FollowUpWindowService::evaluate()` (`:92-124`, `app/Services/FollowUpWindowService.php:128`), and no
very recent customer inbound (`:126-153`, threshold
`credflow.followup.skip_if_recent_inbound_minutes`, default 30). **None is re-checked afterwards.**

---

## 2. Ordered Call Chain

1. **Schedule tick** — `routes/console.php:36` runs `credflow:check-followups` every five minutes,
   pinned `onOneServer()` with `withoutOverlapping(10)`. `CheckFollowUpsCommand::handle()`
   (`app/Console/Commands/CheckFollowUpsCommand.php:21-31`) → `runCheck()` (`:33`) deactivates leads
   whose window has closed, then dispatches one `ProcessLeadFollowUpJob` per eligible lead, jittered
   by `credflow.jobs.cron_dispatch_jitter_seconds`.

2. **Job uniqueness and interaction identity** — `ProcessLeadFollowUpJob`
   (`app/Jobs/ProcessLeadFollowUpJob.php:33-58`) declares `ShouldBeUniqueUntilProcessing`,
   `uniqueId() = "followup_{lead_id}"` (`:55-58`) and `uniqueFor = 600` (`:48`), with
   `tries = 3`, `backoff = [60, 300]`, `timeout = 120`, `maxExceptions = 2` (`:37-43`).
   `handle()` (`app/Jobs/ProcessLeadFollowUpJob.php:60-372`) mints the per-turn correlation id at
   `app/Jobs/ProcessLeadFollowUpJob.php:71` via `AgentInteractionEventService::newInteractionId()`.
   **This one UUID is the join key for the whole path** — note it is minted per *job attempt*, so a
   retry produces a second id for the same follow-up.

3. **Three eligibility gates, all evaluated once** —
   (a) `followup_status === 'active'` (`app/Jobs/ProcessLeadFollowUpJob.php:74-90`);
   (b) `FollowUpWindowService::evaluate()` (`app/Jobs/ProcessLeadFollowUpJob.php:92-124`,
   `app/Services/FollowUpWindowService.php:128`), which on a terminal reason flips
   `followup_status` to `inactive` or `paused` at `:104-108`;
   (c) the recent-inbound race guard on `last_inbound_at`
   (`app/Jobs/ProcessLeadFollowUpJob.php:126-153`). Each failure records a `followup_skipped` event
   and returns. **Nothing re-reads any of these after this point.**

4. **Instance resolution before any LLM spend** — `resolveWhatsappInstance()`
   (`app/Jobs/ProcessLeadFollowUpJob.php:374-384`) is called at
   `app/Jobs/ProcessLeadFollowUpJob.php:158`; a lead with no usable instance is deactivated and
   skipped at `:160-178` rather than burning a model call. Then `followup_started` is recorded at
   `app/Jobs/ProcessLeadFollowUpJob.php:189-199` and the interaction context is set at `:201-207`
   — the context is what later lets `AuditLogMiddleware` see `<interaction_id>` (§ 3).

5. **Per-attempt send claim (F7)** — `Cache::add("followup_send:{<lead_id>}:{attempt}", 1, 10 min)`
   at `app/Jobs/ProcessLeadFollowUpJob.php:212-231`. A retry that fires after the message was queued
   but before `followup_count` committed finds the claim and records
   `followup_skipped` / `duplicate_send` instead of double-sending. Audit finding **F7**, fixed.

6. **The model turn — resolved and prompted directly** —
   `app/Jobs/ProcessLeadFollowUpJob.php:234-257`: `app(AgentFactory::class)->makeFollowUp($this->lead)`
   at `:234` (`app/Ai/AgentFactory.php:65-80`), optional
   `ConversationContextSynchronizer::syncPending()` at `:243`, then
   `$agent->continue($this->lead->conversation_id, $this->lead)->prompt($instructionPrompt)` at
   `app/Jobs/ProcessLeadFollowUpJob.php:254` or `$agent->forUser($this->lead)->prompt(...)` at `:256`.
   **`AgentService::process()` is never called.** This is [F19](#f19).

7. **Send and bookkeeping in one transaction** —
   `app/Jobs/ProcessLeadFollowUpJob.php:271-296`: `WhatsappOutboxService::queueSplitTextForLead()`
   (`app/Services/WhatsappOutboxService.php:123-152`) plus
   `FollowupMessage::create(status: 'sent')` (`app/Jobs/ProcessLeadFollowUpJob.php:282-290`) plus
   `followup_count` increment (`:292`) commit together, so no message can leave without its attempt
   being recorded. `outbound_queued` is recorded at `:314-325`; the lead is deactivated when
   `max_attempts_within_window` is reached at `:327-337`. An empty or sentinel reply takes the
   `no_reply` branch at `:340-367` instead, writing a `FollowupMessage(status: 'no_reply')` without
   incrementing the counter.

8. **Real send boundary** — the outbox rows are picked up by
   `ProcessWhatsappOutboxMessageJob::handle()`
   (`app/Jobs/ProcessWhatsappOutboxMessageJob.php:51-185`), which is shared verbatim with Path 1:
   `provider_attempted_at` guard at `:68-72`, `markProviderAttempted()` immediately before the POST
   at `:88`, `MetaAmbiguousSendException` → `finalizeInDoubt()` at `:145-149` without rethrowing.
   Terminal failure evidence for the follow-up job itself is written by `failed()` at
   `app/Jobs/ProcessLeadFollowUpJob.php:386-428`, which records a `FollowupMessage(status: 'failed')`
   and a `followup_failed` event.

---

## 3. Golden Trace

One successful turn: the scheduler finds an eligible lead, the three gates pass, the follow-up agent
produces a one-part message, it is queued and accepted by Meta.

**Join key.** The `<interaction_id>` UUID minted at `app/Jobs/ProcessLeadFollowUpJob.php:71` and
threaded into every `agent_interaction_events` row, into `AgentInteractionContext`
(`:201-207`), into `WhatsappOutboxService::queueSplitTextForLead(interactionId: …)` (`:279`), and —
via the context — into `AuditLogMiddleware`'s Langfuse trace id
(`app/Ai/Middleware/AuditLogMiddleware.php:85-102`). **It does not reach `ai_runs`, because no
`ai_runs` row is created.**

**The split evidence picture — the subtlest fact in this phase.** A follow-up turn is *partly*
observable. `AuditLogMiddleware` is attached at the **agent** level, not behind `AgentService`:
`BaseCustomerServiceAgent::middleware()` registers it at
`app/Ai/Agents/BaseCustomerServiceAgent.php:137-144`, and `CredFlowFollowUpAgent` overrides
`middleware()` at `app/Ai/Agents/CredFlowFollowUpAgent.php:136-142` re-declaring
`ToolCallGuardMiddleware` + `AuditLogMiddleware` (dropping only `TokenBudgetMiddleware`);
`GenericFollowUpAgent` does the same at `app/Ai/Agents/GenericFollowUpAgent.php:120-126`. So Langfuse,
`LogAiUsageJob` and the `model_called` interaction event all fire on a follow-up turn. What does
**not** fire is anything owned by `AgentService`: `AiRunRecorder::start()` is called from exactly one
place in the codebase, `app/Services/AgentService.php:112-117`, and
`AiRunRecorder::recordModelCall()` silently returns when no row exists for the run id
(`app/Services/AiRunRecorder.php:40-42`), as does `finish()` (`:73-76`). The middleware therefore
*asks* for the model call to be recorded (`app/Ai/Middleware/AuditLogMiddleware.php:49-55`) and the
recorder no-ops.

| Evidence table | Rows for one successful turn | Notes |
|---|---|---|
| `agent_interaction_events` | **Several rows**, one trail keyed by `<interaction_id>` | `followup_started` (`app/Jobs/ProcessLeadFollowUpJob.php:189-199`), `context_synced` when timeline rows were pending (`:245-251`), `model_called` per LLM call from `AuditLogMiddleware` (`app/Ai/Middleware/AuditLogMiddleware.php:57-70`, gated on the interaction id being set at `:48` — which this path does set at `ProcessLeadFollowUpJob.php:201-207`), `outbound_queued` from `WhatsappOutboxService` per part plus the job's own summary row at `:314-325`, and `outbound_sent` per part from `ProcessWhatsappOutboxMessageJob.php:133-144`. On the skip/failure branches: `followup_skipped` (`:81-87`, `:110-121`, `:140-150`, `:169-175`, `:220-226`), `agent_no_reply` (`:360-366`), `followup_failed` (`:412-421`). Every row carries `tenant_id` as a string. **No `fact_check_passed` and no `fact_check_failed` row ever appears on this path** — see [F19](#f19). |
| `ai_runs` | **No row.** | The ledger half of [F19](#f19). `AiRunRecorder::start()` is only ever called from `AgentService::process()` (`app/Services/AgentService.php:112-117`), which this path never calls; `recordModelCall()` then no-ops for want of a row (`app/Services/AiRunRecorder.php:40-42`). **Consequence:** follow-up turns are invisible to Laboratory's `aiRunSummary()` (`app/Services/LaboratoryMetricsService.php:163`), `architectureComparison()` (`:210`) and the `AiUsage` views. Per-turn duration, per-turn cost, token counts, model name, prompt hash and outcome are all unrecoverable for follow-up traffic — even though a Langfuse trace and a `LogAiUsageJob` daily aggregate exist elsewhere. Pinned by a `doesntExist()` assertion in `FollowUpSchedulerCharacterizationTest`. |
| `whatsapp_outbox_messages` | **One row per split part** — one row in this trace | Created inside the transaction at `app/Jobs/ProcessLeadFollowUpJob.php:271-281` via `WhatsappOutboxService::queueSplitTextForLead()` (`app/Services/WhatsappOutboxService.php:123-152`), `source: 'followup'`. Each row carries `idempotency_key`, `interaction_id`, `tenant_id` (string), `status`, `provider_attempted_at`, `provider_message_id`. **The send-boundary evidence is intact on this path** — it is the same mechanism Path 1 uses, and it is the reason field 7 in § 7 is `EXISTS` here while the model-side fields are not. |
| `campaign_messages` | **No row** | Written only by Path 4. A follow-up on a campaign-originated lead reads campaign attribution for prompt context (`buildCampaignFollowUpContext()`), but writes nothing back. |
| `followup_messages` | **One row**, `status = 'sent'` | `app/Jobs/ProcessLeadFollowUpJob.php:282-290`, inside the same transaction as the outbox rows, carrying `attempt`, `tone`, `sent_at` and `tenant_id`. The `no_reply` (`:350-358`) and `failed` (`:401-409`) branches write the same table with a different status, which is what gives Path 3 the tenant-attributable failure metric SC1 relies on. Note the row is stamped `sent` at **queue** time, not at provider-confirmation time — `status = 'sent'` here means "handed to the outbox", and the authoritative delivery state lives on `whatsapp_outbox_messages`. |
| `voice_campaign_calls` | **No row** | Written only by Path 5. |

**Supporting rows outside the six tables:** `conversation_timeline_messages` gains one `outbound`
row per queued part, written at queue time with status `queued`
(`app/Services/WhatsappOutboxService.php:91-99`) and broadcast at
`app/Jobs/ProcessLeadFollowUpJob.php:300-304`. The lead's `followup_count` and
`last_interaction_at` are updated in the same transaction (`:292-293`).

---

## 4. Failure Map

<a id="f19"></a>

| ID | Failure mode | Trigger | Current behaviour | Evidence produced | Labeled finding |
|---|---|---|---|---|---|
| P3-F01 | **Follow-up messages bypass `AgentService` entirely, so the fact-check guardrail never runs and no `ai_runs` row is written** | Every follow-up turn, unconditionally | `ProcessLeadFollowUpJob` resolves its agent with `app(AgentFactory::class)->makeFollowUp($this->lead)` at `app/Jobs/ProcessLeadFollowUpJob.php:234` and calls `->prompt()` directly at `app/Jobs/ProcessLeadFollowUpJob.php:254` / `:256` (block `app/Jobs/ProcessLeadFollowUpJob.php:234-257`). `AgentService::process()` is never called — the only `AgentService` reference in the file is the `NO_REPLY_SENTINEL` constant at `:265`. **Consequence 1 (guardrail):** `AgentService::applyFactCheckGuardrail()` is `private` (`app/Services/AgentService.php:322`) and reachable from exactly one call site, `app/Services/AgentService.php:165`, inside `process()`. It is therefore unreachable from this path, so `FactCheckService::validateAgentResponse()` never runs on a follow-up message — even though `CredFlowFollowUpAgent` registers `ConsultarCreditoInssTool` (`app/Ai/Agents/CredFlowFollowUpAgent.php:9`, `:121`) and can surface financial figures in the message it sends. This is the exact risk class F1/F6 were written to close, on a path they never covered. **Consequence 2 (ledger):** no `ai_runs` row exists for the turn (§ 3). **Severity: same risk class as F1/F6** — an unverified financial figure reaching a customer, on a message the customer did not ask for. **Disposition: documented, not fixed in Phase 61 — assigned to Phase 62**, per orchestrator decision **OD-2**. Why: routing follow-up through `AgentService::process()` or adding the missing `AiRun` row would change runtime behaviour, which a characterization phase must not do; Phase 61's obligation is that this gap carries a stable, labeled identifier so it can never be silently inherited as baseline. | `agent_interaction_events` shows `followup_started` → `model_called` → `outbound_queued` with **no** `fact_check_passed` / `fact_check_failed` row between them, and `ai_runs` has nothing at all. The absence is the evidence; it is pinned by an automated `doesntExist()` assertion rather than asserted in prose. | **F19** (new; documented-not-fixed, Phase 62) |
| P3-F02 | **The three eligibility gates are evaluated once, before the model call, and never re-checked** | A customer message arriving after the gates pass | `app/Jobs/ProcessLeadFollowUpJob.php:74-90`, `:92-124` and `:126-153` all run before `:234`. Between the last gate and the transaction commit at `:271-296` lies the entire model turn — plus any tool call it makes — with no re-read of `followup_status`, the window, or `last_inbound_at`. A customer who replies during that interval receives an unsolicited follow-up written as if they had said nothing. The window extends past commit, too: the outbox row is only *queued* at `:272`, and `ProcessWhatsappOutboxMessageJob` sends it later still, re-checking nothing. | None. The `followup_started` event has no counterpart recording what the state was when the message actually left. `last_inbound_at` is overwritten by the inbound path, so even a forensic reconstruction cannot recover the ordering. | — (new; same gap class as Path 1's P1-F02, longer window) |
| P3-F03 | **`ShouldBeUniqueUntilProcessing` guards the wrong thing** | A genuine new inbound racing an in-flight follow-up | `uniqueId() = "followup_{lead_id}"` with `uniqueFor = 600` (`app/Jobs/ProcessLeadFollowUpJob.php:48`, `:55-58`) prevents a **second follow-up job** for the same lead. It says nothing about a customer message, and unlike Path 1 there is **no `WithoutOverlapping`** on the model call, so a follow-up turn and an inbound turn for the same lead can run genuinely concurrently on different queues (`followups` and `messages`). Recorded because the uniqueness attribute reads, at a glance, like a concurrency guarantee it does not provide. | Two independent `<interaction_id>` trails for one lead with overlapping timestamps — and only one of them (the inbound) has an `ai_runs` row to timestamp against, so the overlap is not even reliably measurable. | — (new) |
| P3-F04 | Per-attempt send claim prevents the follow-up double-send window | A retry firing after the message was queued but before `followup_count` committed | `Cache::add("followup_send:{<lead_id>}:{attempt}", 1, 10 min)` at `app/Jobs/ProcessLeadFollowUpJob.php:212-231` fails closed on the second attempt and records `followup_skipped` / `duplicate_send`. Combined with the single transaction at `:271-296`, a message cannot go out without its attempt being recorded. Fixed and regression-tested (`tests/Feature/FollowUpEngineTest.php`). | `followup_skipped` event with `reason: duplicate_send`; no second `followup_messages` row and no second outbox row. | **F7** (fixed) |
| P3-F05 | **A retry mints a new `<interaction_id>`, so one follow-up can occupy two unlinked trails** | Any transient failure inside `handle()` before the transaction | The id is minted per invocation at `app/Jobs/ProcessLeadFollowUpJob.php:71`, and `tries = 3` with `backoff = [60, 300]` (`:37-39`). Nothing links attempt 2's trail to attempt 1's; only the shared `<lead_id>` and the `attempt` payload field connect them, and `failed()` (`:386-428`) mints yet another id at `:413`. Restart count in the D-28 sense is therefore not derivable. | Multiple `followup_started` events with the same `attempt` value under different interaction ids. | — (new; low severity, but it is why § 7 field 5 is a `GAP` rather than "read `attempts`") |
| P3-F06 | Terminal failure is tenant-attributable | Retries exhausted or `maxExceptions` hit | `failed()` (`app/Jobs/ProcessLeadFollowUpJob.php:386-428`) writes `FollowupMessage(status: 'failed')` with `tenant_id` at `:401-409` and a `followup_failed` interaction event at `:412-421`, wrapped in its own try/catch so bookkeeping errors cannot mask the original failure (`:422-427`). The `failed` row also acts as a backoff floor for `nextDueAt()`. This is the pattern Plan 61-01 mirrored onto the two inbound/outbox jobs. | `followup_messages.status = 'failed'` (tenant-scoped, the basis of SC1's metric) plus `followup_failed`. | — (healthy; keep) |
| P3-F07 | **`FollowupMessage` stores the outbound message text verbatim** | Every successful follow-up turn | `app/Jobs/ProcessLeadFollowUpJob.php:282-290` persists `'message_text' => $text`, and `Log::info` at `:306-312` keeps an 80-character preview. Combined with F19's missing guardrail, an unverified financial figure is both sent *and* durably stored in cleartext. Recorded for D-29 completeness — this dossier does not reproduce any such value, and the characterization test uses only synthetic text. | The row itself is the exposure. | **F13**-adjacent (financial data at rest; F13 remains open) |
| P3-F08 | **No `TokenBudgetMiddleware` on the follow-up agents** | A follow-up turn with a large conversation history | `CredFlowFollowUpAgent::middleware()` (`app/Ai/Agents/CredFlowFollowUpAgent.php:136-142`) and `GenericFollowUpAgent::middleware()` (`app/Ai/Agents/GenericFollowUpAgent.php:120-126`) both override the base list (`app/Ai/Agents/BaseCustomerServiceAgent.php:137-144`) and drop `TokenBudgetMiddleware`, keeping only `ToolCallGuardMiddleware` and `AuditLogMiddleware`. `maxConversationMessages()` is capped at 20 (`CredFlowFollowUpAgent.php:143-146`), which bounds the history but is not a token budget. Cost containment on this path relies on that cap alone — and, per F19, the spend is not recorded in `ai_runs` either, so it cannot be measured after the fact. | None. Token counts reach Langfuse and the `LogAiUsageJob` daily aggregate, but not a per-turn row. | — (new; low severity, recorded so the middleware difference is not mistaken for a copy of the base list) |

---

## 5. Complementary-Message Collision Scenarios

All eight D-31 collision points, restated for this path. "Complementary message" here means a genuine
inbound customer message arriving while Tenaz is preparing or sending an unsolicited follow-up.

| # | Collision point | Applicable? | Current outcome |
|---|---|---|---|
| 1 | **Arrival during collection** | **Not applicable** | This path has no collection window: it is not responding to a customer message, so there is nothing to collect. Its nearest analogue is the recent-inbound guard at `app/Jobs/ProcessLeadFollowUpJob.php:126-153`, which is a one-shot precondition (default: skip if an inbound landed in the last 30 minutes), not a window. A message arriving *before* the gate correctly suppresses the follow-up; one arriving after does not (row #2). |
| 2 | **Arrival during model work** | **Applicable — the longest unchecked window of any path** | The gates pass at `app/Jobs/ProcessLeadFollowUpJob.php:153`; the model is prompted at `:254`/`:256`; the transaction commits at `:271-296`; the outbox job sends later still. There is **no `WithoutOverlapping`-equivalent guarding the model call**, unlike Path 1 — `ShouldBeUniqueUntilProcessing` only blocks a second *follow-up* job (P3-F03) — so the customer's reply is processed concurrently on the `messages` queue while the follow-up turn is mid-flight. Both messages are then sent. The customer sees an unsolicited "still there?" arrive right after they answered. Nothing detects it, and nothing records that it happened. |
| 3 | **Arrival during internal work** | **Applicable** | Same shape as #2, extended by tool work. `CredFlowFollowUpAgent` carries `ConsultarCreditoInssTool` (`app/Ai/Agents/CredFlowFollowUpAgent.php:121`), whose external lookup can add seconds; `ToolCallGuardMiddleware` bounds the loop count but re-reads no conversation state. `ConversationContextSynchronizer::syncPending()` at `app/Jobs/ProcessLeadFollowUpJob.php:243` *does* mirror timeline rows into agent memory — but only once, before the prompt, so it narrows the window rather than closing it. |
| 4 | **Arrival during external action** | **Applicable, partially covered** | Two external actions exist. The WhatsApp send is protected against repetition by the shared `provider_attempted_at` / `in_doubt` machinery (`app/Jobs/ProcessWhatsappOutboxMessageJob.php:68-72`, `:88`, `:145-149`) — D-21/D-22 satisfied, exactly as on Path 1. The credit-lookup tool call has a circuit breaker but no supersession awareness, and — uniquely to this path — its output is **not fact-checked** before it reaches the customer ([F19](#f19)). Repetition is covered; relevance and verification are not. |
| 5 | **Arrival during response queueing** | **Applicable** | The queueing window is narrower here than on Path 1 because `queueSplitTextForLead()` and the bookkeeping run inside one transaction (`app/Jobs/ProcessLeadFollowUpJob.php:271-296`) — an inbound arriving mid-loop cannot interleave a partial bookkeeping state. What the transaction does **not** do is re-read conversation state: it is atomic, not current. A message arriving during it changes nothing. |
| 6 | **Arrival during partial send** | **Applicable** | A multi-part follow-up produces N independently-scheduled outbox rows (`app/Services/WhatsappOutboxService.php:123-152`, `delaySeconds: $index * 2`). Part 1 sends, the customer replies, parts 2..N still send on schedule: there is no group identity, no cancellation path and no status a pending row could move to (Path 1's P1-F03, shared verbatim). D-23 is unimplemented here for the same reason and in the same code. |
| 7 | **Arrival during retry** | **Applicable** | Two retry layers. The job retries with `backoff = [60, 300]` (`app/Jobs/ProcessLeadFollowUpJob.php:39`) and re-runs `handle()` from the top — which means the eligibility gates **are** re-evaluated on a retry, the one place currency is accidentally rechecked, though a new `<interaction_id>` is minted (P3-F05) and the F7 claim key may short-circuit the send. The outbox job retries independently within a six-hour window (`app/Jobs/ProcessWhatsappOutboxMessageJob.php:44-49`) and re-sends its own frozen payload with no recency check at all: a follow-up composed hours earlier can still be delivered. |
| 8 | **Arrival during crash recovery** | **Applicable** | A crashed turn cannot deadlock the lead: the uniqueness lock self-releases when processing starts (`ShouldBeUniqueUntilProcessing`) and expires at `uniqueFor = 600` regardless. After retries are exhausted, `failed()` (`app/Jobs/ProcessLeadFollowUpJob.php:386-428`) records `FollowupMessage(status: 'failed')` and `followup_failed` — genuinely better evidence than most paths had before Phase 61. What is still missing is the stop point: nothing records how far the crashed turn got, whether the model was called, whether a tool executed, or whether cost was incurred — and with no `ai_runs` row ([F19](#f19)) there is no partial ledger to inspect either. |

---

## 6. Latest-State Autonomy Comparison

| Decision | Verdict | Justification |
|---|---|---|
| **D-06** — a newer relevant message makes any unsent response obsolete immediately | `absent` | The recent-inbound guard (`app/Jobs/ProcessLeadFollowUpJob.php:126-153`) is a precondition, not an obsolescence rule: it decides whether to *start*, never whether to *stop*. After it passes, no code re-evaluates relevance before the message is queued at `:272` or sent. |
| **D-07** — the obsolete execution stops at the next safe point and never regains permission to answer | `absent` | No stop concept and no permission concept. The only returns are the three pre-model gates and the F7 duplicate-send claim, all evaluated before `:234`. Once the model is prompted the turn always completes and always queues. |
| **D-08** — re-evaluate from the latest complete state; only the current execution answers | `absent` | `ConversationContextSynchronizer::syncPending()` at `app/Jobs/ProcessLeadFollowUpJob.php:243` is the closest thing in the codebase to "read the latest complete state", and it runs **once, before** the prompt. There is no re-evaluation and no notion of a current execution. |
| **D-15** — one current execution authority per conversation, tied to the exact state | `absent` | Nothing mints, stores or checks an authority. `ShouldBeUniqueUntilProcessing` is keyed on `lead_id` and released when processing *starts* — it is a dispatch-deduplication key, not an authority, and it is blind to inbound messages (P3-F03). |
| **D-16** — authority is temporary and renewable; a new relevant message revokes it, a crash expires it | `absent` | No authority exists to revoke. The crash half is accidentally satisfied — the uniqueness lock expires at `uniqueFor = 600` (`app/Jobs/ProcessLeadFollowUpJob.php:48`) so the lead cannot stay blocked — but that is lock hygiene, not authority expiry: nothing is invalidated, only unblocked. |
| **D-17** — authority checked throughout the cycle **and again at the real send boundary** | `absent` | Zero checks throughout the cycle, and the send boundary (`app/Jobs/ProcessWhatsappOutboxMessageJob.php:51-185`) loads one row by primary key and never queries the lead's newest inbound or any sibling row. Shared code with Path 1, and equally unimplemented here. |
| **D-18** — an outdated request must not return an old answer as if current | `absent` | D-18 is written for the direct API path; its principle — supersession made explicit in the trace and the contract — has no implementation here either. The outbox row and its `outbound_sent` event carry no staleness marker, and the `followup_messages` row records `status = 'sent'` at queue time (§ 3) with no field able to say "sent, but no longer apt". |
| **D-23** — cancel every not-yet-sent part as soon as the response becomes obsolete | `absent` | `queueSplitTextForLead()` (`app/Services/WhatsappOutboxService.php:123-152`) creates independent rows with no group identity and no cancellation path — the same code and the same gap as Path 1's P1-F03. |
| **D-24** — an already-sent part stays canonical history; the next execution sees it and continues naturally | `partial` | The preservation half works and is slightly stronger than Path 1's: sent parts persist as `conversation_timeline_messages` rows and `syncPending()` (`app/Jobs/ProcessLeadFollowUpJob.php:243`) explicitly mirrors un-synced timeline rows into agent memory *before* resuming, so a follow-up does see operator turns and inbound received while AI was paused. The gap is the same upstream one: a timeline row is written for every queued part at queue time (`app/Services/WhatsappOutboxService.php:91-99`), so history can contain a part that was never sent or that landed after it stopped making sense, and nothing marks either case. |
| **D-25** — serial processing alone is not evidence against stale or out-of-order replies | `absent` | This path does not even have serial processing to over-trust: the follow-up turn and an inbound turn for the same lead run on different queues with no shared lock (P3-F03). D-25's warning binds Phase 62 here in its strongest form — neither the uniqueness key nor the single transaction may be counted toward it, since neither proves ownership at the send boundary. |

---

## 7. Evidence Available Today

Per-path verdicts. These differ from the repository-wide table in `61-RESEARCH.md` § Evidence Field
Mapping, which is the starting reference, not the answer for this path. The pattern worth noticing is
the **mirror image of Path 2**: Path 3's delivery-side evidence is intact and its model-side evidence
is missing, because the outbox is shared but `ai_runs` is not.

| # | Evidence field | Verdict | What exists on Path 3 |
|---|---|---|---|
| 1 | Collection window | `GAP` | No collection window exists on this path (§ 5 #1), so nothing is recorded. The recent-inbound precondition leaves only a `followup_skipped` / `recent_inbound` event when it fires, and nothing at all when it passes. |
| 2 | Execution start | `PARTIAL` | The `followup_started` event (`app/Jobs/ProcessLeadFollowUpJob.php:189-199`) marks the turn's start with `attempt` and `followup_count`. What is missing is the structured counterpart Paths 1 and 2 have: `ai_runs.started_at` does not exist here ([F19](#f19)), so start time is only readable from the event trail, not from the ledger Laboratory queries. |
| 3 | Supersession | `GAP` | No `execution_superseded` event type exists in the codebase. Pinned as a negative assertion by the characterization test. |
| 4 | Stop point | `GAP` | An execution never stops for relevance. The `followup_skipped` events record *why work never started* — a different fact — and a crashed turn records only that it failed, never how far it got (§ 5 #8). |
| 5 | Restart count | `GAP` | Job `attempts` counts retries of the same input, not restarts from newer state, and a retry mints a fresh `<interaction_id>` (P3-F05), so the attempts are not even linked into one logical follow-up. Recording them as restarts would be a category error. |
| 6 | Preserved result references | `GAP` | No preservation or reuse concept. `syncPending()` mirrors conversation history into agent memory, which is context, not a referenced prior result with provenance. Tool results are not carried across turns. |
| 7 | External-action outcome | `EXISTS` | The strongest field on this path, and identical to Path 1's because it is the same code: `whatsapp_outbox_messages.status` (`queued`/`sending`/`sent`/`failed`/`in_doubt`) plus `provider_attempted_at`, with `outbound_sent` / `outbound_failed` / `outbound_in_doubt` events. Confirmed, proven-not-performed and uncertain are genuinely distinguished. Note the separate `followup_messages.status` field is *not* this evidence — it is stamped `sent` at queue time (§ 3). |
| 8 | Obsolete response blocked | `GAP` | Nothing blocks a response as obsolete, so there is nothing to record. No `response_blocked_stale` event type exists. |
| 9 | Execution that sent | `PARTIAL` | `whatsapp_outbox_messages.interaction_id` joins the send to the turn that produced it, so "which execution sent this" is answerable after the fact — the same correlation Path 1 has. What is missing is the adjudication: nothing ever decided this execution was entitled to send. Correlation without authority. |
| 10 | Elapsed time | `GAP` | **Differs from Paths 1 and 2, where this field `EXISTS`.** `ai_runs.duration_ms` is the only per-turn duration in the system and there is no `ai_runs` row here ([F19](#f19)). `AuditLogMiddleware` computes a `duration_ms` and ships it to Langfuse and the `model_called` event payload (`app/Ai/Middleware/AuditLogMiddleware.php:29`, `:68`), so a duration exists in the event trail and in an external system — but not in the queryable ledger every Laboratory view reads. |
| 11 | Cost | `GAP` | **Differs from Paths 1 and 2, where this field `EXISTS`.** `estimated_cost_usd` is computed by `AiRunRecorder::recordModelCall()`, which no-ops without a row (`app/Services/AiRunRecorder.php:40-42`). Token counts do reach the `LogAiUsageJob` daily aggregate and Langfuse, so follow-up spend is not invisible in total — but it cannot be attributed to a turn, a lead, or a conversation cycle, and it is excluded from `aiRunSummary()` and `architectureComparison()`. |

---

## 8. Characterization Test Reference

**File:** `tests/Feature/FollowUpSchedulerCharacterizationTest.php`

**Command:**

```
php artisan test --compact --filter=FollowUpSchedulerCharacterization
```

The test is a Kent-Beck characterization oracle: it asserts the **current, undesirable** behaviour of
unmodified production code as a receipt for Phase 62, not a specification. It pins four facts — the
happy-path evidence shape (a `whatsapp_outbox_messages` row and a `followup_messages` row, both
tenant-attributed as strings); **[F19](#f19)'s ledger half, that the same turn produces no `ai_runs`
row at all**; that the interaction-event trail *is* written even though the ledger is not, which is
the split picture § 3 describes; and the late-inbound collision, where a customer message persisted
after the eligibility gates pass does not stop the follow-up from being queued and produces no
supersession evidence.

When Phase 62 routes follow-up through the runtime — adding the `AiRun` row and the fact-check
guardrail — this file is **expected to fail**, starting with the `doesntExist()` assertion against
`AiRun`. It must then be rewritten deliberately as part of that change, never quietly adjusted to
describe the new behaviour, which would erase the before/after this phase exists to create.

**Fixture-accuracy note.** The model call is stubbed at the gateway seam with
`CredFlowFollowUpAgent::fake([...])`, the idiom already established in
`tests/Feature/FollowUpEngineTest.php`, so the **real** job, the real eligibility gates, the real
`AgentFactory::makeFollowUp()` resolution, the real transaction and the real
`WhatsappOutboxService` all execute — only the provider call is replaced. No production file was
modified to make the test possible, which is itself part of the receipt: the gap is reachable
without any seam being introduced for it.
