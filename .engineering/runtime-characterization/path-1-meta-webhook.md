# Path 1 — Meta Cloud Inbound Webhook

Requirement: RUNT-01
Success criteria: SC3, SC5
Schema version: 1
Characterized: 2026-08-05
Characterization test: MetaWebhookCharacterization

> D-29 binds this document. No raw phone number, message body, CPF, financial value, credential or
> tool payload appears here. Identifiers are written as placeholders (`<lead_id>`, `<tenant_id>`,
> `<interaction_id>`, `<wamid>`) and behaviour is described by classification, length and status.

---

## 1. Entry & Trigger

**Trigger.** An unauthenticated `POST` from Meta Cloud to the public webhook route, handled by
`app/Http/Controllers/MetaWebhookController.php:55` (`handle()`). Meta is the only sender the route
expects; nothing else in the product initiates this path. A separate `GET` on the same route
(`MetaWebhookController.php:35`, `verify()`) serves Meta's subscription challenge and never reaches
message handling.

**Authentication.** HMAC-SHA256 over the raw request body, verified by
`verifyGlobalSignature()` (`MetaWebhookController.php:359-386`) against `services.meta.app_secret`.
It **fails closed**: a missing secret returns `false` and the request is rejected with `401` in every
environment except `local` and `testing`, where a warning is logged and the check is skipped so the
suite can drive the controller without a real secret. There is no per-tenant signature — one app
secret covers every tenant's traffic.

**Tenant resolution.** The webhook carries no tenant identity. `handleChange()` resolves the
`WhatsappInstance` from `value.metadata.phone_number_id`, falling back to `entry.id` (the WABA id),
via `resolveInstance()` (`MetaWebhookController.php:158-173`). Both lookups use
`withoutGlobalScope('tenant')` (`:161`, `:167`) — this is the sanctioned cross-tenant webhook-ingest
lookup, not a scope suppression: the instance row is the *only* thing that can say which tenant this
message belongs to, and the scope is inert in this unauthenticated request anyway. An unresolvable
instance is logged (`meta.webhook_unknown_instance`) and dropped with `204`, silently. Once the
instance is known, `AgentContextResolver::resolveFromInstanceName()` (`:189`) yields
`tenant_id` + `agent_id`, and every downstream job carries `tenantId` as an explicit constructor
property, compared as a string.

**Synchronous vs queued.** The HTTP request does almost nothing: it verifies, resolves, dedupes,
mints an `interaction_id`, pushes the message text into a Redis buffer and returns `204`. **All
conversational work is queued.** Three queues are involved in sequence — `messages` (aggregation and
inbound processing), optionally `media` (deferred media download), and `outbox` (the real send). No
model call, no CRM write and no WhatsApp send happens inside the HTTP request.

**Replay handling at the door.** `handleIncomingMessage()` (`MetaWebhookController.php:178-201`)
opens with a `Cache::add("wamid:{<wamid>}", 1, addDay())` guard at `:180-187`: a redelivered inbound
with a `wamid` already seen in the last 24 hours is logged and dropped before any dispatch. Delivery
statuses have their own equivalent guard at `:221-228`. This is audit finding **F10**, fixed.

**Branching.** `handleChange()` (`:84-156`) routes by `change.field` before it ever looks at
messages: template status/quality/category updates go to `TemplateStatusUpdateService` (`:106-115`),
WABA account-health fields to `MetaAccountHealthWebhookService` (`:121-125`), coexistence fields to
`ProcessMetaCoexistenceWebhookJob` (`:127-131`), delivery `statuses` to
`ProcessCampaignDeliveryEventJob` (`:133-136`), and only then `messages` (`:138-155`). A media
message diverts to `handleMedia()` (`:244-290`) and `DownloadIncomingMediaJob`; a text message goes
to `handleText()` (`:292-357`).

**The debounce fork.** In `handleText()`, `DebounceService::isQuickCommand()` (`:301`) short-circuits
a fixed list of eleven short greetings/confirmations
(`app/Services/DebounceService.php:14-25`) straight to `ProcessIncomingWhatsAppMessageJob` with **no
collection window at all** (`:344-356`). Every other text goes through
`DebounceService::push()` (`:302`) and only the first message of a window schedules
`AggregateDebouncedMessageJob` with `->delay(now()->addSeconds(config('credflow.debounce_seconds', 3)))`
(`:303-313`).

---

## 2. Ordered Call Chain

1. **Signature gate** — `MetaWebhookController::handle()` (`app/Http/Controllers/MetaWebhookController.php:55`)
   calls `verifyGlobalSignature()` (`app/Http/Controllers/MetaWebhookController.php:359-386`).
   Fail → `401`, nothing is queued.

2. **Instance and tenant resolution** — `handleChange()`
   (`app/Http/Controllers/MetaWebhookController.php:84-156`) resolves the `WhatsappInstance` through
   `resolveInstance()` (`app/Http/Controllers/MetaWebhookController.php:158-173`) using
   `withoutGlobalScope('tenant')` at `app/Http/Controllers/MetaWebhookController.php:161` and
   `:167`, then dispatches on `change.field`.

3. **Replay guard and interaction identity** — `handleIncomingMessage()`
   (`app/Http/Controllers/MetaWebhookController.php:178-201`) drops a duplicate `<wamid>` at
   `app/Http/Controllers/MetaWebhookController.php:180-187`, resolves tenant/agent at `:189-191`,
   and mints the per-turn correlation id at
   `app/Http/Controllers/MetaWebhookController.php:192` via
   `AgentInteractionEventService::newInteractionId()`. **This one UUID is the join key for the whole
   path.**

4. **Collection window** — `handleText()`
   (`app/Http/Controllers/MetaWebhookController.php:292-357`) either bypasses collection for a quick
   command (`app/Http/Controllers/MetaWebhookController.php:301`, dispatching
   `ProcessIncomingWhatsAppMessageJob` directly at `:344-356`) or buffers via
   `DebounceService::push()` (`app/Services/DebounceService.php:32-54`). `push()` `RPUSH`es the text
   onto `debounce:{phone}` with a 10-second key TTL
   (`app/Services/DebounceService.php:36-40`) and claims a `SETNX` lock at
   `app/Services/DebounceService.php:45-51`. **Only the first message of the window returns `true`**
   (`app/Services/DebounceService.php:53`), and only that message schedules the delayed drain at
   `app/Http/Controllers/MetaWebhookController.php:303-313`. Later messages append and return
   `false` — they do not extend the delay.

5. **Aggregation** — `AggregateDebouncedMessageJob::handle()`
   (`app/Jobs/AggregateDebouncedMessageJob.php:42-88`) drains the buffer
   (`app/Services/DebounceService.php:60-77`, chronological join on `\n`), records the
   `webhook_received` evidence event at `app/Jobs/AggregateDebouncedMessageJob.php:58-73`, and
   dispatches `ProcessIncomingWhatsAppMessageJob` with the aggregated text at
   `app/Jobs/AggregateDebouncedMessageJob.php:75-87`. This job carries **no overlap guard**.

6. **Serialized inbound processing** — `ProcessIncomingWhatsAppMessageJob::middleware()`
   (`app/Jobs/ProcessIncomingWhatsAppMessageJob.php:72-79`) applies
   `WithoutOverlapping("incoming_msg_{tenantId}_{phone}")` with `releaseAfter(15)` and
   `expireAfter(120)`. `handle()` (`app/Jobs/ProcessIncomingWhatsAppMessageJob.php:89-281`) then
   persists CRM state through `IncomingConversationPersister::persist()` at
   `app/Jobs/ProcessIncomingWhatsAppMessageJob.php:117-128`, releases on lock contention at `:130-135`,
   returns early on a duplicate provider id at `:142-146`, broadcasts and records
   `broadcast_sent` at `:148-158`, and gates automation at `:160-225`
   (no agent → `automation_skipped_no_agent`; `shouldAutoRespond()` false → `agent_skipped`;
   inactive agent → `agent_skipped`).

7. **Model turn, then queueing — with nothing in between** —
   `app/Jobs/ProcessIncomingWhatsAppMessageJob.php:229` calls
   `AgentService::process($lead, $aggregatedMessage, $mediaContext, $interactionId)`
   (`app/Services/AgentService.php:44`). On return, `:230` syncs the automation stage and `:233-259`
   resolves the instance and calls
   `WhatsappOutboxService::queueSplitTextForLead()` at
   `app/Jobs/ProcessIncomingWhatsAppMessageJob.php:251-259`.
   `app/Services/WhatsappOutboxService.php:123-152` splits the answer on a blank line and queues
   **one independently-delayed row per part** (`delaySeconds: $index * 2` at
   `app/Services/WhatsappOutboxService.php:147`), each through `queueTextForLead()`
   (`app/Services/WhatsappOutboxService.php:79-118`) → `queue()`
   (`app/Services/WhatsappOutboxService.php:19-77`), keyed by the deterministic idempotency key built
   at `app/Services/WhatsappOutboxService.php:189-203`. `outbound_queued` is recorded at
   `app/Jobs/ProcessIncomingWhatsAppMessageJob.php:267-277`.
   **There is no check of any kind between `:229` and `:251`.**

8. **Real send boundary** — `ProcessWhatsappOutboxMessageJob::handle()`
   (`app/Jobs/ProcessWhatsappOutboxMessageJob.php:51-185`) skips already-`sent`/`in_doubt` rows at
   `:55-57`, re-releases a not-yet-due row at `:59-63`, refuses to re-POST after an unconfirmed
   attempt at `app/Jobs/ProcessWhatsappOutboxMessageJob.php:68-72`, stamps
   `markProviderAttempted()` immediately before the POST at
   `app/Jobs/ProcessWhatsappOutboxMessageJob.php:88`, and then either marks the row `sent` at
   `:122-144`, or — on a 2xx with no provider message id — throws `MetaAmbiguousSendException`
   (`app/Jobs/ProcessWhatsappOutboxMessageJob.php:117-120`) which is caught at
   `app/Jobs/ProcessWhatsappOutboxMessageJob.php:145-149` and routed to `finalizeInDoubt()`
   (`app/Jobs/ProcessWhatsappOutboxMessageJob.php:193-215`) **without rethrowing**, so no retry can
   turn an ambiguous send into a duplicate. Any other throwable clears the attempt marker only when
   the failure proves nothing left the client (`ConnectionException`, `:150-157`), marks the row
   `failed` and rethrows. Terminal, tenant-attributable failure evidence is written by `failed()` at
   `app/Jobs/ProcessWhatsappOutboxMessageJob.php:286-327`; the inbound job has the equivalent at
   `app/Jobs/ProcessIncomingWhatsAppMessageJob.php:312-349`.

---

## 3. Golden Trace

One successful turn: a single non-quick text message arrives, the collection window closes, the agent
answers with a two-part response, both parts are accepted by Meta.

**Join key.** The `interaction_id` UUID minted at
`app/Http/Controllers/MetaWebhookController.php:192` and threaded unchanged into
`AggregateDebouncedMessageJob` → `ProcessIncomingWhatsAppMessageJob` → `AgentService::process()` →
`AiRunRecorder::start(runId: $interactionId)` → `WhatsappOutboxService::queue(interactionId: ...)` →
`LangfuseService` trace id. Every row below is joined on it; the outbox rows additionally carry
`interaction_id` as a column, so `whatsapp_outbox_messages.interaction_id = ai_runs.run_id =
agent_interaction_events.interaction_id` is the single correlation.

| Evidence table | Rows for one successful turn | Notes |
|---|---|---|
| `agent_interaction_events` | **Several rows**, one trail keyed by `<interaction_id>` | `webhook_received` (`app/Jobs/AggregateDebouncedMessageJob.php:58-73`; or `MetaWebhookController.php:327-342` on the quick-command branch), `inbound_received` + `conversation_persisted` (`app/Services/IncomingConversationPersister.php:203-216`, `:331-341`), `broadcast_sent` (`ProcessIncomingWhatsAppMessageJob.php:150-156`), `agent_started` and the `AgentService` lifecycle events, `outbound_queued` once per part from `WhatsappOutboxService.php:50-61` plus one summary `outbound_queued` from `ProcessIncomingWhatsAppMessageJob.php:267-277`, and `outbound_sent` per part from `ProcessWhatsappOutboxMessageJob.php:133-144`. Every row carries `tenant_id` as a string. |
| `ai_runs` | **One row**, `run_id = <interaction_id>` | Path 1 goes through `AgentService::process()`, which is the only caller of `AiRunRecorder::start()` (`app/Services/AgentService.php:112-117`). Carries `started_at`, `duration_ms`, `estimated_cost_usd`, `architecture_version`, `tenant_id` as a string. This is the distinguishing strength of Path 1 relative to the follow-up and Playground paths. |
| `whatsapp_outbox_messages` | **One row per split part** — two rows in this trace | Created by `WhatsappOutboxService::queueSplitTextForLead()` (`app/Services/WhatsappOutboxService.php:123-152`); part *n* is stamped `scheduled_at = now + 2n` seconds. Each row carries `idempotency_key`, `interaction_id`, `tenant_id` (string), `status`, `provider_attempted_at`, `provider_message_id`. |
| `campaign_messages` | **No row** | Path 1 is conversational; the campaign tables are written only by Path 4. An inbound that *replies to* a campaign is detected by `CampaignReplyDetector` inside the persister and stamps attribution on the lead/session, but creates no `campaign_messages` row. |
| `followup_messages` | **No row** | Written only by Path 3. Note the inverse interaction: the persister sets `followup_status` to `inactive` when an inbound lands on a lead with an active follow-up (`app/Services/IncomingConversationPersister.php:260-266`) — Path 1 *suppresses* future follow-up rows, it does not create one. |
| `voice_campaign_calls` | **No row** | Written only by Path 5. |

**Supporting rows outside the six tables** (named because a Phase 62 reader will look for them):
`conversation_timeline_messages` gains one `inbound` row (`app/Services/IncomingConversationPersister.php:278-289`)
and one `outbound` row **per queued part**, written at queue time with status `queued`
(`app/Services/WhatsappOutboxService.php:91-99`) — i.e. the timeline shows a part before it is sent
and regardless of whether it is ever sent. A `conversation_sessions` (atendimento) row is opened or
reused at `app/Services/IncomingConversationPersister.php:258`.

---

## 4. Failure Map

| ID | Failure mode | Trigger | Current behaviour | Evidence produced | Labeled finding |
|---|---|---|---|---|---|
| P1-F01 | **Collection window is fixed, not sliding — D-09 and D-10 are not implemented at all** | Any burst of complementary messages spanning more than the configured window | `DebounceService::push()` (`app/Services/DebounceService.php:32-54`) schedules the drain once, from the **first** message, via a `SETNX` lock. Later messages `RPUSH` onto the list and return `false` — they never extend or reset the delay. The drain fires at exactly `debounce_seconds` (default 3, `config/credflow.php:11`) after the first message, splitting a longer burst across two independent turns. There is no ten-second hard cap because there is no sliding window for a cap to bound. | None. `DebounceService` emits no event; the only trace is the aggregated `message_length` on `webhook_received`. Collection start, collection end and message count are unrecoverable. | — |
| P1-F02 | **No ownership check between the model returning and the answer being queued** | Any turn whose model call outlives the arrival of a newer customer message | `app/Jobs/ProcessIncomingWhatsAppMessageJob.php:229` returns the answer and `:251-259` queues it. Nothing in between reads `last_inbound_at`, the timeline, the debounce buffer, or any authority record. The answer is queued unconditionally. This is the phase's headline gap (D-06/D-07/D-08/D-15/D-17). | Only the positive trail: `agent_response_ready` + `outbound_queued`. No `execution_superseded`, no `response_blocked_stale` — those event types do not exist in the codebase. | — (new; Phase 62 owns the fix per D-33) |
| P1-F03 | **A split response's not-yet-sent parts cannot be cancelled once queued (D-23)** | Any multi-part answer whose later parts are still pending when the response becomes obsolete | `queueSplitTextForLead()` (`app/Services/WhatsappOutboxService.php:123-152`) creates N independent rows with staggered `scheduled_at`. Nothing owns them as a group: there is no batch id, no `cancel()`, no status transition that stops a pending row, and `ProcessWhatsappOutboxMessageJob` only skips a row that is already `sent`/`in_doubt` (`app/Jobs/ProcessWhatsappOutboxMessageJob.php:55-57`) or not yet due (`:59-63`). Every queued part will be sent. | `whatsapp_outbox_messages.status` stays `queued` until the row is sent — which reads identically to "waiting to be sent for good reason". No field distinguishes "still valid" from "obsolete but unstoppable". | — |
| P1-F04 | **The send boundary sends its own row regardless of anything newer (D-17)** | A fresher, more complete answer for the same lead exists when a stale row becomes due | `ProcessWhatsappOutboxMessageJob::handle()` (`app/Jobs/ProcessWhatsappOutboxMessageJob.php:51-185`) loads exactly one row by primary key and never queries siblings, the lead's newest inbound, or any authority. D-17's "checked again at the real send boundary" has no implementation. | `outbound_sent` per row. A stale send and a current send are indistinguishable in the evidence. | — |
| P1-F05 | **Ambiguous provider outcome is handled correctly — a strength, not a gap** | A transport failure or a 2xx with no provider message id | `markProviderAttempted()` is stamped immediately before the POST (`app/Jobs/ProcessWhatsappOutboxMessageJob.php:88`); a re-execution that finds the marker refuses to re-POST and finalizes `in_doubt` (`:68-72`); `MetaAmbiguousSendException` is caught and routed to `finalizeInDoubt()` **without rethrowing** (`:145-149`, `:193-215`); the marker is cleared only when `ConnectionException` proves nothing left the client (`:150-157`). This is D-21/D-22 already satisfied for this path's external effect. **Phase 62 must generalize this mechanism, not replace it** — Paths 4 and 5 each lack or re-implement it. | `whatsapp_outbox_messages.status = in_doubt` + `provider_attempted_at`, plus an `outbound_in_doubt` event (`:198-208`) and terminal `outbound_permanently_failed` from `failed()` (`:286-327`). | — (audit "Healthy (keep)") |
| P1-F06 | Inbound replay at the door is guarded | Meta redelivers the same `<wamid>` | `Cache::add("wamid:{<wamid>}", 1, addDay())` drops the redelivery before dispatch (`app/Http/Controllers/MetaWebhookController.php:180-187`); the persister adds a second, DB-level guard on `provider_message_id` (`app/Services/IncomingConversationPersister.php:97-121`) plus a unique-index race resolution (`:290-319`). Fixed and regression-tested. | `meta.webhook_replay_skipped` log; `whatsapp_persister.duplicate_provider_message` log; no duplicate rows. | **F10** (fixed) |
| P1-F07 | **The debounce buffer key is not tenant-scoped** | The same customer phone messages two different tenants' instances inside one window | `DebounceService::push()`/`drain()` key on `debounce:{phone}` alone (`app/Services/DebounceService.php:34`, `:62`), while every downstream guard — including `WithoutOverlapping("incoming_msg_{tenantId}_{phone}")` (`app/Jobs/ProcessIncomingWhatsAppMessageJob.php:75`) — is tenant-scoped. Two tenants' inbound text for one phone can therefore share a buffer and be drained into one aggregated message attributed to whichever tenant claimed the `SETNX` lock. | None — the aggregation is invisible in the trace; the `webhook_received` event records only the merged `message_length`. | — (new; recorded here, not fixed in this phase) |
| P1-F08 | **Lock contention can consume message B's whole retry budget** | Turn A holds the overlap lock longer than the released job's remaining attempts cover | `WithoutOverlapping` releases the contending job with `releaseAfter(15)` (`app/Jobs/ProcessIncomingWhatsAppMessageJob.php:76`) while the job declares `tries = 3` (`:32`). Each release consumes an attempt, so a turn A that holds the lock beyond roughly two release cycles can exhaust message B's attempts before it ever runs — the customer's newer message is then never processed at all, while turn A's stale answer is still sent. Derived from the configured values; **worth confirming against a live worker before Phase 62 designs around it.** | `inbound_processing_permanently_failed` from `failed()` (`app/Jobs/ProcessIncomingWhatsAppMessageJob.php:312-349`) plus the generic `failed_jobs` row. Tenant-attributable since 61-01. | — (new; derived, flagged for confirmation) |
| P1-F09 | Signature verification is global, not per tenant | A compromised or rotated app secret | One `services.meta.app_secret` authenticates every tenant's traffic (`app/Http/Controllers/MetaWebhookController.php:361`). Fail-closed outside `local`/`testing` (`:363-379`). Accepted as-is; this plan does not modify the gate. | `meta.webhook_unauthorized` / `meta.webhook_signature_misconfigured` logs. | — (accepted, T-61-14) |

---

## 5. Complementary-Message Collision Scenarios

All eight D-31 collision points, restated for this path.

| # | Collision point | Applicable? | Current outcome |
|---|---|---|---|
| 1 | **Arrival during collection** | **Applicable** | Message B lands inside the open window: `push()` appends it to `debounce:{phone}` and returns `false`, so it is aggregated into turn A's input and **no supersession is needed** — this is the one collision the path already handles well. But the window is fixed from message A (P1-F01): a B that lands after the window closed opens a *fresh* window and becomes a second, independent turn. The boundary is a wall clock, not silence. |
| 2 | **Arrival during model work** | **Applicable — this is the phase's central finding** | Message A starts Job A with a long model turn. Message B arrives; because `AggregateDebouncedMessageJob` carries **no** overlap guard, it drains B's window and records B's `webhook_received` event and dispatches Job B while turn A is still inside `AgentService::process()`. Job B then blocks on `WithoutOverlapping("incoming_msg_{tenant}_{phone}")` until Job A releases it. Job A returns from `:229`, queues its now-stale answer at `:251-259` with **no check at all**, and completes. Job B acquires the lock and queues its own answer. The customer receives two agent messages, the first written with no knowledge of message B. **`WithoutOverlapping` serializes; it does not invalidate.** Pinned by `MetaWebhookCharacterizationTest`. |
| 3 | **Arrival during internal work** | **Applicable** | Same shape as #2 and the same absence of a check. Internal work on this path means tool calls and guardrails inside `AgentService::process()`; the `ToolCallGuard`/`FactCheck`/`TokenBudget` middleware chain can extend the turn well past the collection window, widening the unchecked interval. Nothing re-reads conversation state after a tool returns. |
| 4 | **Arrival during external action** | **Applicable, partially covered** | The only external action on this path is the WhatsApp send itself, and it *is* protected against repetition by the `provider_attempted_at` / `in_doubt` machinery (P1-F05). What is not covered is *relevance*: a confirmed send of a stale part is preserved forever as correct. D-21/D-22 are satisfied; D-06/D-08 are not. Credit-lookup tool calls invoked inside the model turn are external effects with their own circuit breaker but no supersession awareness. |
| 5 | **Arrival during response queueing** | **Applicable** | The window between the first `queueTextForLead()` and the last is real: `queueSplitTextForLead()` loops (`app/Services/WhatsappOutboxService.php:137-151`) and each iteration writes a timeline row and an outbox row. A message arriving mid-loop changes nothing — the loop has no cancellation point and no state re-read. |
| 6 | **Arrival during partial send** | **Applicable — the sharpest D-23/D-24 gap** | Part 1 is `sent`, parts 2..N are `queued` with `scheduled_at` staggered two seconds apart. Message B arrives. Nothing cancels parts 2..N (P1-F03): each will be picked up and sent on schedule, interleaved with turn B's own parts. The customer sees fragments of two different answers braided together. Worse for D-24: **every part already has a timeline row written at queue time**, so a part that is later sent out of context is indistinguishable in history from one that was appropriate. |
| 7 | **Arrival during retry** | **Applicable** | `ProcessIncomingWhatsAppMessageJob` retries with backoff `[10, 30, 60]` inside a 30-minute `retryUntil` window (`app/Jobs/ProcessIncomingWhatsAppMessageJob.php:39-49`); `ProcessWhatsappOutboxMessageJob` retries with `[10, 60, 180]` inside a six-hour window (`app/Jobs/ProcessWhatsappOutboxMessageJob.php:28`, `:44-49`). A retry re-runs the *original* input: the inbound job replays the aggregated text captured at dispatch, and the outbox job re-sends its own frozen payload. Neither re-reads the conversation. A six-hour-old outbox row can still be sent as if current — bounded only by that window, not by relevance. |
| 8 | **Arrival during crash recovery** | **Applicable** | A crashed turn leaves no authority to revoke, because no authority exists (D-15/D-16 absent), so the conversation cannot deadlock — the `WithoutOverlapping` lock self-expires after 120 seconds and the next inbound proceeds. That is the accidental upside. The downside: after retries are exhausted the *only* record is `inbound_processing_permanently_failed` / `outbound_permanently_failed` (`app/Jobs/ProcessIncomingWhatsAppMessageJob.php:312-349`, `app/Jobs/ProcessWhatsappOutboxMessageJob.php:286-327`). There is no stop-point evidence, no restart count, and no way to tell whether the crashed turn had already produced partial customer-visible effects. |

---

## 6. Latest-State Autonomy Comparison

| Decision | Verdict | Justification |
|---|---|---|
| **D-06** — a newer relevant message makes any unsent response obsolete immediately | `absent` | No code anywhere on this path evaluates "is this response still relevant". `app/Jobs/ProcessIncomingWhatsAppMessageJob.php:229-259` queues whatever the model returned. |
| **D-07** — the obsolete execution stops at the next safe point and never regains permission to answer | `absent` | There is no stop concept and no permission concept. An execution that started runs to completion and always queues its answer; the only early returns are the automation gates at `:160-225`, all evaluated *before* the model call. |
| **D-08** — re-evaluate from the latest complete state; only the current execution answers | `absent` | Turn A and turn B both answer. The path has no notion of "current execution". |
| **D-15** — one current execution authority per conversation, tied to the exact state | `absent` | Nothing mints, stores or checks an authority. `WithoutOverlapping` holds a *mutex on the job*, which is not the same object: it is keyed on tenant+phone, not on conversation state, and it is released the moment the job ends — including after the answer is queued. |
| **D-16** — authority is temporary and renewable; a new message revokes it, a crash expires it | `absent` | No authority exists to revoke. The crash half is accidentally satisfied (the overlap lock self-expires at 120s, `app/Jobs/ProcessIncomingWhatsAppMessageJob.php:77`) but that is lock hygiene, not authority expiry — nothing is invalidated, only unblocked. |
| **D-17** — authority checked throughout the cycle **and again at the real send boundary** | `absent` | Not merely "one check before queueing is insufficient" — there is **zero**. `ProcessWhatsappOutboxMessageJob::handle()` loads its row by primary key and never looks at anything else (P1-F04). |
| **D-18** — an outdated request must not return an old answer as if current | `absent` | D-18 is written for the direct API path. Its principle — supersession made explicit in the trace and in the contract — has no implementation here either: the outbox row and its `outbound_sent` event carry no staleness marker, so a stale answer is reported to operators and dashboards exactly like a current one. |
| **D-23** — cancel every not-yet-sent part as soon as the response becomes obsolete | `absent` | `queueSplitTextForLead()` (`app/Services/WhatsappOutboxService.php:123-152`) creates independent rows with no group identity and no cancellation path (P1-F03). |
| **D-24** — an already-sent part stays canonical history; the next execution sees it and continues naturally | `partial` | The preservation half **is** implemented: sent parts persist as `conversation_timeline_messages` rows and `ConversationContextSynchronizer::syncPending()` mirrors the customer/human side of the timeline into agent memory before the next `prompt()` (`app/Services/ConversationContextSynchronizer.php:35-53`), so the next turn does see the history. The gap is upstream: a timeline row is written for **every queued part at queue time** (`app/Services/WhatsappOutboxService.php:91-99`), so history can contain a part that was never sent, or that was sent after it stopped making sense — and nothing marks either case. "Continues without repeating or contradicting" is left entirely to the model. |
| **D-25** — serial processing alone is not evidence against stale or out-of-order replies | `absent` | **Serial processing is present on this path and is explicitly NOT accepted as evidence.** `WithoutOverlapping("incoming_msg_{tenantId}_{phone}")` (`app/Jobs/ProcessIncomingWhatsAppMessageJob.php:72-79`) guarantees turns for one tenant+phone do not interleave — and the characterization test proves that guarantee is irrelevant to the invariant: both turns still queue, both answers still cross the send boundary, and the send boundary (`app/Jobs/ProcessWhatsappOutboxMessageJob.php:51-185`) never proves ownership. Ordering is not currency. Phase 62 must not count the overlap lock toward D-25. |

---

## 7. Evidence Available Today

Per-path verdicts. These differ from the repository-wide table in `61-RESEARCH.md` § Evidence Field
Mapping, which is the starting reference, not the answer for this path.

| # | Evidence field | Verdict | What exists on Path 1 |
|---|---|---|---|
| 1 | Collection window | `GAP` | `DebounceService` emits nothing (`app/Services/DebounceService.php:32-77`). The aggregated `message_length` on `webhook_received` is the only residue; window start, window end, and the number of messages merged are all unrecoverable. |
| 2 | Execution start | `EXISTS` | `ai_runs.started_at` (`AgentService` is the only caller of `AiRunRecorder::start()`, `app/Services/AgentService.php:112-117`) plus the `agent_started` event. Path 1 is one of the two paths where this is complete. |
| 3 | Supersession | `GAP` | No `execution_superseded` event type exists in the codebase. Pinned as a negative assertion by the characterization test. |
| 4 | Stop point | `GAP` | An execution never stops early for relevance. The pre-model gates (`agent_skipped`, `automation_skipped_no_agent`) record *why work never started*, which is a different fact. |
| 5 | Restart count | `GAP` | D-11/D-12's restart concept does not exist. `attempts` on the job and on the outbox row count *retries of the same input*, not restarts from newer state — recording them as restarts would be a category error. |
| 6 | Preserved result references | `GAP` | No preservation or reuse concept. Every turn re-runs the model from scratch; tool results are not carried across turns with provenance. |
| 7 | External-action outcome | `EXISTS` | The strongest field on this path: `whatsapp_outbox_messages.status` (`queued`/`sending`/`sent`/`failed`/`in_doubt`) plus `provider_attempted_at`, with `outbound_sent` / `outbound_failed` / `outbound_in_doubt` events and terminal `outbound_permanently_failed`. Confirmed, proven-not-performed and uncertain are genuinely distinguished (P1-F05). |
| 8 | Obsolete response blocked | `GAP` | Nothing blocks a response as obsolete, so there is nothing to record. No `response_blocked_stale` event type exists. |
| 9 | Execution that sent | `PARTIAL` | `whatsapp_outbox_messages.interaction_id` joins the send to the turn that produced it, so "which execution sent this" is answerable after the fact. What is missing is the adjudication: nothing ever decided that this execution was the one *entitled* to send. Correlation without authority. |
| 10 | Elapsed time | `EXISTS` | `ai_runs.duration_ms` (`AiRunRecorder::finish()`). Note it measures the model turn, not door-to-customer latency: the collection delay, the queue waits and the per-part `scheduled_at` stagger are outside it. Time-to-current-response in the D-30 sense is therefore not directly readable. |
| 11 | Cost | `EXISTS` | `ai_runs.estimated_cost_usd`, per turn. Cost per *conversation cycle* (D-26/D-30) is not aggregated anywhere, and a cycle split across two turns by P1-F01 bills as two. |

---

## 8. Characterization Test Reference

**File:** `tests/Feature/MetaWebhookCharacterizationTest.php`

**Command:**

```
php artisan test --compact --filter=MetaWebhookCharacterization
```

The test is a Kent-Beck characterization oracle: it asserts the **current, undesirable** behaviour of
unmodified production code as a receipt for Phase 62, not a specification. It pins four facts —
the happy-path entry contract (one aggregation job, one interaction id); the collision receipt (turn
A's answer is queued even though message B is already on record, and two outbox rows exist for the
lead); the absence of any supersession evidence (`execution_superseded` and `response_blocked_stale`
both `doesntExist()`); and the uncancellable split (one row per part, all still in a pre-send status
after the collision).

When Phase 62 introduces current-execution ownership this file is **expected to fail**. It must then
be rewritten deliberately as part of that change — never quietly adjusted to describe the new
behaviour, which would erase the before/after this phase exists to create.

**Fixture-accuracy note.** The test drives the two turns as two sequential `->handle()` calls, the
established repository idiom for a race. That ordering is not an approximation: `WithoutOverlapping`
makes it exactly what production does. What the fixture models synthetically is message B's *arrival*
during turn A's model call — it records B's `webhook_received` event and advances the lead's
`last_inbound_at`, standing in for `AggregateDebouncedMessageJob` (which carries no overlap guard and
genuinely runs mid-turn) and for the newest-known conversation state a latest-state check would
consult. It deliberately does **not** insert message B's inbound timeline row mid-turn, because the
overlap lock provably prevents that interleaving within a single attempt, and faking it would both
misrepresent production and cause message B's own persistence to dedupe itself away.
