# Path 4 — Campaign Dispatch and Reply Bridge

Requirement: RUNT-01
Success criteria: SC3
Schema version: 1
Characterized: 2026-08-05
Characterization test: CampaignDispatchCharacterization

> D-29 binds this document. No raw phone number, message body, CPF, financial figure, credential
> or tool payload appears here. Identifiers are written as placeholders (`<campaign_id>`,
> `<campaign_message_id>`, `<entry_id>`, `<tenant_id>`, `<interaction_id>`, `<wamid>`) and
> behaviour is described by classification, status and column name.

> **This dossier's headline fact is a distinction, not a defect.** `CampaignMessage` carries its
> **own, independently-implemented** provider-attempt lease and `in_doubt` state. It is
> structurally parallel to `WhatsappOutboxMessage`'s and shares **no code** with it — different
> model, different columns, different method signatures, different concurrency guarantees.
> Per D-01 these are **two mechanisms**, not one mechanism characterized once. Phase 62 must not
> assume the outbox's semantics generalize here. See [P4-F01](#p4-f01) and the side-by-side table
> in § 4.

---

## 1. Entry & Trigger

**Trigger.** An operator action, not a customer and not a schedule. `CampaignService::start()`
(`app/Services/CampaignService.php:39-58`) validates the send configuration under a row lock, flips
the campaign to `sending`, and dispatches `DispatchCampaignJob` (`:58`). `CampaignService::resume()`
(`:96-119`) re-enters the same job for a paused campaign, and the campaign monitor revives a
budget-stopped campaign on a later day through the same entry point. There is no customer message
anywhere in this trigger.

**Authentication.** None at the job layer, and none is possible: both jobs run in console/queue
context. Authorization happened earlier, in the HTTP request that called `CampaignService::start()`.

**Tenant resolution.** From the loaded `Campaign` alone. `BelongsToTenant`'s global scope is inert in
queue context, and this path handles that **differently from every other path in the inventory**: it
does not filter by `tenant_id` at all, because it does not need to. `DispatchCampaignJob`'s class
comment states the reasoning verbatim (`app/Jobs/DispatchCampaignJob.php:18-20`) — every
`CampaignMessage` query is scoped by the globally-unique `campaign_id` FK
(`app/Jobs/DispatchCampaignJob.php:82`, `:171-174`), so no cross-tenant row is reachable. Tenant
identity reaches evidence only through `$campaign->tenant_id`, stamped as a string by
`AgentInteractionEventService::record()` (`app/Services/AgentInteractionEventService.php:39`).
**`campaign_messages` has no `tenant_id` column at all** — asserted directly by the characterization
test.

**Synchronous vs queued.** Fully queued, two hops on one queue. `DispatchCampaignJob` (queue
`campaigns`, `tries = 1`, `timeout = 3600`, `app/Jobs/DispatchCampaignJob.php:25-33`) fans out; one
`SendCampaignMessageJob` per recipient (queue `campaigns`, `maxExceptions = 3`, `timeout = 30`,
`backoff = [30, 120]`, `app/Jobs/SendCampaignMessageJob.php:46-67`) performs the real send. The
delayed dispatch staggers each message by `index * delay_between_ms`
(`app/Jobs/DispatchCampaignJob.php:204-207`), so a large campaign's tail can pop hours after the
fan-out.

**No model call, and therefore no conversational latest-state concept.** This path sends a
**pre-approved Meta template**, resolving positional parameters from a mapping
(`app/Jobs/SendCampaignMessageJob.php:867-919`). `AgentService` is never called, `AgentFactory` is
never called, no agent is constructed and no LLM is invoked. Recorded as a **characterization fact,
not a defect**: D-06/D-07/D-08 are written about "a response not yet sent" produced by reasoning over
conversation state, and this path produces no such response. What *does* apply here is the
external-effect half of the principle set — D-21/D-22 — and it is genuinely implemented (§ 4).

**Retry budget is time-based, not attempt-based.** `retryUntil()`
(`app/Jobs/SendCampaignMessageJob.php:83-94`) returns `scheduledFor + send_retry_window_seconds`
(default 6 h), anchored to the message's **own send slot** rather than dispatch time, so a far-tail
staggered message is not failed before it ever runs. While that window is open the worker ignores
`attempts()` entirely, which is what lets the two throttles release a job indefinitely without
failing it; genuine errors stay bounded by `maxExceptions = 3`. The reasoning is recorded inline at
`app/Jobs/SendCampaignMessageJob.php:48-53` and `:69-82`.

### 1.1 The reply bridge — not a separate ingress

An inbound reply to a campaign-originated conversation **re-enters through Path 1**, the Meta Cloud
inbound webhook. It is not a separate ingress, has no separate trigger, and needs **no separate
golden trace** — see [`path-1-meta-webhook.md`](path-1-meta-webhook.md) for the trace it produces.
Stated explicitly so a future reader does not conclude the bridge was overlooked.

What the bridge actually does, in Path 1's code:

1. `IncomingConversationPersister` calls `CampaignReplyDetector::detect()`
   (`app/Services/IncomingConversationPersister.php:235`,
   `app/Services/CampaignReplyDetector.php:38-67`). It resolves the campaign that reached this
   phone/tenant, restricted to `LIVE_STATUSES = ['sending', 'paused']`
   (`app/Services/CampaignReplyDetector.php:21`), and stamps `leads.campaign_id`
   (`app/Services/CampaignReplyDetector.php:56-65`). **It writes no `campaign_messages` row** —
   attribution lands on the lead, not on the campaign evidence table.
2. `CampaignConversationTimelineWriter::backfillForLead()`
   (`app/Services/CampaignConversationTimelineWriter.php:111`) is called once per lead per hour,
   guarded by `Cache::add("campaign_backfill:{<lead_id>}", …)`
   (`app/Services/IncomingConversationPersister.php:338-341`), and backfills the campaign templates
   that preceded the reply into `conversation_timeline_messages`. This exists precisely **because**
   campaigns bypass the outbox and the timeline for scale — the writer's own class docblock says so
   (`app/Services/CampaignConversationTimelineWriter.php:15-30`).

Consequence worth carrying into Phase 62: the conversation a customer sees as one continuous thread
is, in the evidence, **two disconnected halves** — a `campaign_messages` row with its own
`<interaction_id>` on this path, and an `agent_interaction_events` trail under a different
`<interaction_id>` on Path 1. Nothing joins them except `leads.campaign_id` and a best-effort
timeline backfill.

---

## 2. Ordered Call Chain

### 2.1 Fan-out chain

1. **Freshness and state gate** — `DispatchCampaignJob::handle()`
   (`app/Jobs/DispatchCampaignJob.php:35-253`) re-reads the campaign at
   `app/Jobs/DispatchCampaignJob.php:39` and returns unless `isSending()`
   (`app/Jobs/DispatchCampaignJob.php:41-49`). A revive/resume run re-enters here.

2. **Configuration validation, fail-closed** — `CampaignService::validatedSendConfig()`
   (`app/Jobs/DispatchCampaignJob.php:52`, `app/Services/CampaignService.php:179-199`). A violation
   pauses the campaign and blocks the whole fan-out
   (`app/Jobs/DispatchCampaignJob.php:53-63`).

3. **Correlation identity for the fan-out only** —
   `app/Jobs/DispatchCampaignJob.php:38` mints one `<interaction_id>` for the dispatch itself and
   records `campaign_dispatch_started` at `app/Jobs/DispatchCampaignJob.php:67-75`. **This id does
   not reach the sends**: each recipient gets a *fresh* id at
   `app/Jobs/DispatchCampaignJob.php:206`. A campaign of N recipients therefore produces N + 1
   independent correlation ids, with no parent/child link between them.

4. **Smart-list snapshot on first dispatch only** —
   `app/Jobs/DispatchCampaignJob.php:82-121`. `SmartListResolverService::materialize()` runs only
   when no `CampaignMessage` row exists yet, so a resume on a later day cannot silently swap the
   frozen audience. `total_recipients` is corrected to the materialized count at
   `app/Jobs/DispatchCampaignJob.php:92`, and a zero-result list auto-completes the campaign at
   `:102-113`.

5. **Daily budget bound** — `CampaignService::remainingDailyBudget()`
   (`app/Jobs/DispatchCampaignJob.php:129`); a zero budget defers the whole run to the monitor
   revive (`:131-137`).

6. **Streaming fan-out with consent pre-suppression** —
   `app/Jobs/DispatchCampaignJob.php:143-220`. `chunkById(500)` streams entries; each chunk
   re-reads the campaign and aborts on a mid-dispatch pause
   (`app/Jobs/DispatchCampaignJob.php:148-155`); opted-out entries are dropped **before** any row
   or job is created (`app/Jobs/DispatchCampaignJob.php:162-164`, `:182-184`); already-existing
   rows are re-enqueued only when they are orphaned `pending` with no provider attempt
   (`app/Jobs/DispatchCampaignJob.php:171-199`). Each survivor gets a `CampaignMessage` row via
   `firstOrCreate` (`:195-198`) and one delayed `SendCampaignMessageJob`
   (`app/Jobs/DispatchCampaignJob.php:204-207`).

7. **Terminal fan-out evidence** — `campaign_dispatch_queued` at
   `app/Jobs/DispatchCampaignJob.php:243-252`; on a crash, `failed()`
   (`app/Jobs/DispatchCampaignJob.php:262-280`) records `campaign_dispatch_failed` and calls
   `attemptResume()` (`:285-321`), which re-dispatches the idempotent remainder under a
   per-campaign budget held in `Cache`.

### 2.2 Send chain

8. **Pre-existing attempt reconciliation, before the state gate** —
   `app/Jobs/SendCampaignMessageJob.php:107-124`. A row that is still `pending`/`queued` **and**
   already carries `provider_attempted_at` is resolved first: an **active** lease defers
   (`app/Jobs/SendCampaignMessageJob.php:115-119`, `:646-659`), an **expired** lease becomes
   terminal `in_doubt` (`app/Jobs/SendCampaignMessageJob.php:121-123`,
   `app/Models/CampaignMessage.php:267-298`). Deliberately ordered *before* the campaign-state gate
   so a pause cannot strand a claim forever. Neither branch calls Meta.

9. **Campaign-state gate with re-enqueueable parking** —
   `app/Jobs/SendCampaignMessageJob.php:126-144`. A message whose delayed job fires during a pause
   is parked back to `pending` (`:134`) rather than stranded `queued`, because the resume
   dispatcher skips `queued` rows.

10. **Status gate and durable backoff** — `app/Jobs/SendCampaignMessageJob.php:146-148` and
    `:150-154`. `provider_retry_not_before` is a **row-level** backoff deadline that survives a
    worker restart.

11. **Daily-limit safety net** — `app/Jobs/SendCampaignMessageJob.php:160-170`; parks the row
    `pending` for the next day's budget.

12. **Consent re-check at send time (LGPD)** — `app/Jobs/SendCampaignMessageJob.php:188-211`.
    Either the entry snapshot **or** the canonical `Contact` opting out suppresses the send, right
    before the side effect, and `markSkipped()` (`app/Models/CampaignMessage.php:181-189`) is
    terminal but deliberately **not** `failed`. Recorded as a strength; see [P4-F02](#p4-f02).

13. **Parameter resolution and queue evidence** —
    `app/Jobs/SendCampaignMessageJob.php:214-220` resolves template params and flips the row to
    `queued`; `outbound_queued` is recorded at `app/Jobs/SendCampaignMessageJob.php:222-234`.

14. **Send configuration, cached by entity id** —
    `app/Jobs/SendCampaignMessageJob.php:236-259` and `:758-786`. The instance and template are
    cached per entity id and busted by model observers, so a revoked template takes effect
    immediately; `template->status !== 'APPROVED'` fails the row at
    `app/Jobs/SendCampaignMessageJob.php:261-265`.

15. **Two release-and-requeue throttles** — per-tenant fairness at
    `app/Jobs/SendCampaignMessageJob.php:276-309` and per-instance rate at `:315-352`. Both park the
    row `pending` and `release()` the job rather than failing it; both **fail open** on a cache or
    Redis outage (`:284-290`, `:325-333`). See [P4-F03](#p4-f03).

16. **Destination validation before the provider** —
    `app/Jobs/SendCampaignMessageJob.php:358-377`. `PhoneNumberValidator::normalize()` rejects a
    malformed number without retry, so it never reaches Meta and carries no account signal.

17. **The atomic lease, claimed immediately before the POST** —
    `app/Jobs/SendCampaignMessageJob.php:379-405`. A UUID attempt token and a lease expiry
    (`max(timeout + 15, provider_attempt_lease_seconds)`) are passed to
    `CampaignMessage::claimProviderAttempt()` (`app/Models/CampaignMessage.php:198-243`), a
    conditional `UPDATE` that exactly one caller can win. The loser returns **without sending**.

18. **The provider POST** — `app/Jobs/SendCampaignMessageJob.php:407-413`
    (`sendTemplate(..., opaqueId: (string) $message->id)` — the opaque id is what later lets a
    delivery webhook resolve an ambiguous row). A 2xx with no message id throws
    `MetaAmbiguousSendException` at `app/Jobs/SendCampaignMessageJob.php:415-418`.

19. **Ownership-guarded terminal transitions** — every outcome is a compare-and-swap on the attempt
    token, so a late duplicate can never overwrite the winner:
    `markSentIfOwned()` (`app/Jobs/SendCampaignMessageJob.php:424-426`,
    `app/Models/CampaignMessage.php:334-362`),
    `markInDoubtFromProviderIfOwned()` (`:544`, `app/Models/CampaignMessage.php:411-431`),
    `markFailedFromProviderIfOwned()` / `markFailedIfOwned()` (`:514-517`, `:568`,
    `app/Models/CampaignMessage.php:364-409`), and
    `releaseProviderAttemptForRetry()` for a proven-not-sent outcome (`:460`, `:600`,
    `app/Models/CampaignMessage.php:433-468`). `outbound_sent` is recorded at
    `app/Jobs/SendCampaignMessageJob.php:446-457`.

20. **Timeline mirror, best effort** —
    `CampaignConversationTimelineWriter::mirrorSentTemplate()`
    (`app/Jobs/SendCampaignMessageJob.php:428-435`,
    `app/Services/CampaignConversationTimelineWriter.php:47`) writes the sent template into
    `conversation_timeline_messages`, creating the `Lead` when the recipient has none. It never
    throws into its caller.

21. **Terminal failure evidence** — `failed()` (`app/Jobs/SendCampaignMessageJob.php:788-829`)
    is idempotent against the in-flight catch (`:799-801`), parks a retry-window expiry that
    happened during a pause (`:806-812`), and — when an attempt is outstanding — either schedules a
    lease-expiry probe (`:816`, `:831-854`) or fails closed to `in_doubt` (`:817`, `:823`).

22. **Delivery receipts re-enter through Path 1** — Meta `statuses` webhooks route to
    `ProcessCampaignDeliveryEventJob` (`app/Http/Controllers/MetaWebhookController.php:133-136`,
    `app/Jobs/ProcessCampaignDeliveryEventJob.php:39`), which advances the row to `delivered`
    (`:251`, `:258`) or `read` (`:261`). These states exist **only** on `campaign_messages`.

---

## 3. Golden Trace

One successful execution: an operator starts a campaign with three opted-in recipients and one
opted-out recipient; the fan-out enqueues three sends; one of those sends is accepted by Meta with a
provider message id.

**Join keys — plural, and that is the finding.** The fan-out mints one `<interaction_id>`
(`app/Jobs/DispatchCampaignJob.php:38`) and each recipient mints another
(`app/Jobs/DispatchCampaignJob.php:206`). There is **no single correlation id for a campaign turn**
the way there is for a conversational turn on Paths 1 and 3. The durable joins are
`campaign_messages.campaign_id`, `campaign_messages.contact_list_entry_id`, and — after a send —
`campaign_messages.provider_message_id`. Measured by `CampaignDispatchCharacterizationTest`, which
asserts three distinct interaction ids for three recipients.

| Evidence table | Rows for one successful execution | Notes |
|---|---|---|
| `agent_interaction_events` | **Two rows for the fan-out, two rows per send** | Fan-out: `campaign_dispatch_started` (`app/Jobs/DispatchCampaignJob.php:67-75`) and `campaign_dispatch_queued` (`:243-252`), plus `smart_list_materialized` (`:94-100`) on a dynamic list's first dispatch. Send: `outbound_queued` (`app/Jobs/SendCampaignMessageJob.php:222-234`) and `outbound_sent` (`:442-453`). Failure/throttle branches add `outbound_skipped_optout` (`:197-208`), `outbound_throttled` (`:470-481`), `outbound_failed` (`:362-374`, `:495-507`, `:524-537`, `:573-586`), `outbound_in_doubt` (`:548-560`) and `outbound_retrying` (`:613-625`). **Every row carries `tenant_id` as a string and `lead_id` as `NULL`**, because `record()` is called without a lead (`app/Services/AgentInteractionEventService.php:28-47`) — so a campaign trail cannot be joined to a conversation. The characterization test asserts the complete successful-send vocabulary is exactly `outbound_queued`, `outbound_sent`. |
| `ai_runs` | **No row** | No model call exists on this path (§ 1). `AiRunRecorder::start()` is called from exactly one place in the codebase, `app/Services/AgentService.php:112-117`, which this path never reaches. Unlike Path 3's [F19](path-3-followup-scheduler.md#f19) this is **not a bypass of an expected runtime** — there is no turn to record. Pinned by a `doesntExist()` assertion so Phase 62 cannot inherit the conversational evidence shape by assumption. |
| `whatsapp_outbox_messages` | **No row** | [P4-F01](#p4-f01), and the single most important line in this dossier. The campaign path never touches `WhatsappOutboxService`; `CampaignMessage` reimplements the whole send-boundary mechanism on its own model. Pinned by a `doesntExist()` assertion. |
| `campaign_messages` | **One row per recipient** — three in this trace, one of them `sent` | Created by `firstOrCreate` during fan-out (`app/Jobs/DispatchCampaignJob.php:195-198`), advanced to `queued` at send time (`app/Jobs/SendCampaignMessageJob.php:220`) and to `sent` by `markSentIfOwned()` (`app/Models/CampaignMessage.php:334-362`). Carries `provider_message_id`, `provider_attempted_at`, `provider_attempt_token`, `provider_attempt_lease_expires_at`, `provider_retry_not_before`, `template_params_resolved`, `sent_at`/`delivered_at`/`read_at`/`failed_at` and the full provider-error quintuple. **Carries no `tenant_id`** — asserted directly by the characterization test via `Schema::hasColumn()`. |
| `followup_messages` | **No row** | Written only by Path 3. A campaign-originated lead can later receive a follow-up, which reads campaign attribution for prompt context but writes nothing back here. |
| `voice_campaign_calls` | **No row** | Written only by Path 5. |

**Supporting rows outside the six tables:** `conversation_timeline_messages` gains one `outbound`
row per confirmed send via `CampaignConversationTimelineWriter::mirrorSentTemplate()`
(`app/Services/CampaignConversationTimelineWriter.php:47`), and a `Lead` row is created for a
recipient who had none — deliberately, so an unanswered send is visible at all
(`app/Services/CampaignConversationTimelineWriter.php:22-27`,
`app/Services/CampaignConversationTimelineWriter.php:168`). Campaign counters (`total_sent`,
`total_failed`, `total_skipped`) are **derived** from `campaign_messages` rather than stored, so a
send writes no counter `UPDATE` to the campaigns row.

---

## 4. Failure Map

<a id="p4-f01"></a>
<a id="p4-f02"></a>
<a id="p4-f03"></a>
<a id="p4-f04"></a>

| ID | Failure mode | Trigger | Current behaviour | Evidence produced | Labeled finding |
|---|---|---|---|---|---|
| P4-F01 | **`CampaignMessage` implements its own provider-attempt lease and `in_doubt` state — a second, independent implementation of the outbox's mechanism, sharing no code with it** | Every campaign send, unconditionally | The campaign path never calls `WhatsappOutboxService` and writes no `whatsapp_outbox_messages` row. `CampaignMessage` (`app/Models/CampaignMessage.php`) carries a parallel mechanism whose concrete differences from `WhatsappOutboxMessage` (`app/Models/WhatsappOutboxMessage.php`) are tabulated below. Both reach a defensible D-21/D-22 outcome; they reach it by different code with different guarantees, so **Phase 62 must migrate them separately**. Per D-01 and Open Question 3 in `61-RESEARCH.md`, these are not "the same mechanism analysed once". **Disposition: documented, not unified in Phase 61** — merging them would change runtime behaviour, which a characterization phase must not do. | `campaign_messages` rows exist; `whatsapp_outbox_messages` is empty. Both halves are measured, not argued: `CampaignDispatchCharacterizationTest` asserts `WhatsappOutboxMessage::doesntExist()` and asserts the divergent column sets directly with `Schema::hasColumn()`. | — (new; documented-not-fixed, Phase 62) |
| P4-F02 | Consent is re-checked at the real send boundary, not only at dispatch — **a strength Phase 62 must preserve** | A recipient who opts out while their staggered or parked job waits | `app/Jobs/SendCampaignMessageJob.php:188-211` re-reads the entry snapshot **and** the canonical `Contact` immediately before the side effect and calls `markSkipped()` (`app/Models/CampaignMessage.php:181-189`). `skipped` is terminal but explicitly not `failed`, so a mass opt-out cannot trip the failure-rate machinery. The fan-out additionally pre-suppresses opt-outs known at dispatch (`app/Jobs/DispatchCampaignJob.php:162-164`, `:182-184`). **This is the only place in the entire inventory where a decision made before a wait is re-evaluated after it** — Phase 62's latest-state check should be recognised as a generalisation of exactly this shape. | `campaign_messages.status = skipped` with `error_code = OPTED_OUT` and `provider_attempted_at` still `NULL`, plus an `outbound_skipped_optout` event (`:197-208`). Pinned positively by the characterization test. | — (healthy; keep. Threat T-61-25) |
| P4-F03 | Both throttles release-and-requeue instead of failing — **a strength Phase 62 must preserve** | A tenant over its per-minute fairness budget, or an instance over its per-minute rate cap | `app/Jobs/SendCampaignMessageJob.php:276-309` (per-tenant, cache-backed) and `:315-352` (per-instance, Redis-backed) both park the row `pending` and `release()` the job to the queue tail. Neither counts as a failure, because `retryUntil()` (`:83-94`) makes the worker ignore `attempts()` inside the window and `maxExceptions` counts only thrown exceptions (`:48-53`). Both **fail open** on a cache/Redis outage (`:284-290`, `:325-333`) so a degraded cache cannot stall every send — a deliberate availability-over-precision trade recorded here because Phase 62 must not silently invert it. | `outbound_throttled` event on the Meta-429 path (`:470-481`); the fairness/rate releases log but emit no interaction event, so throttle frequency is not queryable from the evidence tables. | — (healthy; keep) |
| P4-F04 | **No model call, therefore no `ai_runs` row and no conversational evidence at all** | Every campaign send, unconditionally | Template-only sending of a pre-approved Meta template; `AgentService`, `AgentFactory`, `FactCheckService` and `AiRunRecorder` are all absent from both jobs. Recorded so that Phase 62 does not read "no `ai_runs` row" here as the same finding it is on Path 3: there, a model call happens and is unrecorded ([F19](path-3-followup-scheduler.md#f19)); here, no model call happens. **The consequence is still real:** cost per completed conversation cycle (D-26/D-30) cannot be computed for a campaign-originated conversation, because the template send that opened it has no cost row and no shared correlation id with the conversation that follows. | `ai_runs` empty; the complete event vocabulary of a successful send is exactly `outbound_queued`, `outbound_sent`. Asserted exhaustively rather than by containment, so the day a `model_called` or `fact_check_*` row can appear on this path, the test fails. | — (new) |
| P4-F05 | **The fan-out mints one correlation id per recipient with no parent link to the dispatch** | Every campaign of more than one recipient | `app/Jobs/DispatchCampaignJob.php:38` mints the dispatch id; `:206` mints a fresh id per send. Nothing stores the dispatch id on the `campaign_message` row or in the send's event payload, so a campaign of N recipients produces N + 1 unlinked trails. Reconstructing "which dispatch produced this send" requires joining on `campaign_id` and timestamps, not on the correlation id the rest of the system uses. | N + 1 independent `<interaction_id>` values in `agent_interaction_events`, all carrying `campaign_id` in their payload but no shared id. Pinned by the characterization test's distinct-id assertion. | — (new; low severity, recorded because it is why § 7 field 9 is `PARTIAL` here for a different reason than on Paths 1 and 3) |
| P4-F06 | **Campaign evidence carries no `tenant_id` column and no `lead_id`** | Every campaign send | `campaign_messages` has no tenant column (verified against every migration touching the table) — isolation rests entirely on the globally-unique `campaign_id` FK, which the class comment at `app/Jobs/DispatchCampaignJob.php:18-20` states as the deliberate design. `AgentInteractionEventService::record()` is called without a `leadId` on this path (`app/Jobs/SendCampaignMessageJob.php:222-234`, `:442-453`), so every campaign event has `lead_id = NULL`. Consequence: a tenant-scoped query over campaign delivery outcomes must join `campaigns`, and a lead's interaction timeline never shows the campaign template that started the conversation. | The absence itself; asserted with `Schema::hasColumn('campaign_messages', 'tenant_id')` and a `lead_id` null assertion in the characterization test. | — (new) |
| P4-F07 | **`DispatchCampaignJob` runs with `tries = 1`, so a mid-fan-out crash leaves a partially-enqueued campaign** | Worker crash or timeout during a large fan-out | `app/Jobs/DispatchCampaignJob.php:25` sets `tries = 1` and `:27` a one-hour timeout. `failed()` (`:262-280`) records `campaign_dispatch_failed` and calls `attemptResume()` (`:285-321`), which re-dispatches the idempotent remainder — but only when `campaigns.dispatch_max_redispatch > 0`, and bounded by a per-campaign `Cache` counter with a six-hour TTL. With the budget at its default of `0` the auto-resume is **disabled** and the campaign sits in `sending` with part of the list never enqueued until the monitor revive or an operator acts. | `campaign_dispatch_failed` interaction event (`:270-277`), an error log, and — when the budget is exhausted — an explicit `manual resume required` log at `:303-306`. No row records how far the fan-out got. | — (new; mitigated by design, recorded because the mitigation is config-gated off by default) |
| P4-F08 | **A queued campaign send is never re-evaluated for relevance, only for consent and budget** | A conversation that has moved on between dispatch and the send slot | Between `DispatchCampaignJob` enqueueing a message and `SendCampaignMessageJob` sending it, up to `send_retry_window_seconds` (6 h) plus the stagger can elapse. The gates that *are* re-read are campaign state (`:126-144`), daily limit (`:160-170`), consent (`:188-211`) and template approval (`:261-265`). Nothing reads the lead's conversation state: a recipient who is mid-conversation with the agent, or who has already been handed to a human operator, still receives the bulk template. This is the campaign-shaped instance of the D-06/D-17 gap. | None. A relevant and an irrelevant campaign send are indistinguishable in `campaign_messages` and in the `outbound_sent` event. | — (new; Phase 62 owns the fix per D-33) |

### 4.1 The two leases, side by side

The concrete method-level differences P4-F01 refers to. Left column is the campaign
implementation, right is the outbox implementation characterized in
[`path-1-meta-webhook.md`](path-1-meta-webhook.md) § 4 (P1-F05).

| Concern | `CampaignMessage` | `WhatsappOutboxMessage` |
|---|---|---|
| **Claim primitive** | `claimProviderAttempt(?string $attemptToken, ?DateTimeInterface $leaseExpiresAt): bool` (`app/Models/CampaignMessage.php:198-243`) — a conditional `UPDATE` guarded on `status IN (pending,queued) AND provider_attempted_at IS NULL AND provider_attempt_token IS NULL`, returning whether **this** caller won. Atomic under concurrent workers. | `markProviderAttempted(): void` (`app/Models/WhatsappOutboxMessage.php:68-71`) — an unconditional `update(['provider_attempted_at' => now()])`. No return value, no compare-and-swap; concurrency is prevented upstream by a read-then-return guard in the job (`app/Jobs/ProcessWhatsappOutboxMessageJob.php:68-72`), not by the model. |
| **Ownership token** | `provider_attempt_token` (UUID minted at `app/Jobs/SendCampaignMessageJob.php:379`). **Every** terminal transition is a CAS on it: `markSentIfOwned()`, `markFailedIfOwned()`, `markFailedFromProviderIfOwned()`, `markInDoubtFromProviderIfOwned()`, `releaseProviderAttemptForRetry()` (`app/Models/CampaignMessage.php:334-468`). A losing duplicate cannot overwrite the winner. | **No token column exists.** `markSent()`, `markFailed()`, `markInDoubt()` (`app/Models/WhatsappOutboxMessage.php:88-112`) are unconditional updates — last writer wins. |
| **Lease expiry** | `provider_attempt_lease_expires_at` + `hasActiveProviderAttemptLease()` (`app/Models/CampaignMessage.php:260-265`). An abandoned claim self-resolves to `in_doubt` via `markAbandonedProviderAttemptInDoubt()` (`:267-298`), and an unschedulable probe fails closed via `markUnreconciledProviderAttemptInDoubt()` (`:305-332`). | **No lease concept.** `provider_attempted_at` is permanent until `clearProviderAttempt()` (`:78-81`) is called on a proven-not-sent path. A crashed worker's marker blocks the row forever rather than expiring into `in_doubt` on its own. |
| **Durable backoff** | `provider_retry_not_before` — a row-level deadline set by `releaseProviderAttemptForRetry()` (`:433-468`) and honoured on re-execution (`app/Jobs/SendCampaignMessageJob.php:150-154`). Survives a worker restart. | None. Backoff lives only in the queue payload (`backoff = [10, 60, 180]`); `scheduled_at` is a dispatch-time schedule, not a retry deadline. |
| **Declared status machine** | `STATUS_ORDER` + `statusOrder()` + `canTransitionTo()` (`app/Models/CampaignMessage.php:71-95`) over `pending, queued, in_doubt, sent, delivered, read, failed, skipped`. | No status machine is declared anywhere. Statuses (`queued, sending, sent, failed, in_doubt`) exist only as string literals inside the `mark*` methods. |
| **States the other lacks** | `delivered`, `read` (advanced by the Meta delivery webhook, `app/Jobs/ProcessCampaignDeliveryEventJob.php:251-261`) and `skipped` (LGPD consent, `:181-189`). | `sending` (`app/Models/WhatsappOutboxMessage.php:55-61`), used to record an in-flight attempt count. |
| **Tenant attribution** | No `tenant_id` column; isolation via the `campaign_id` FK. | `tenant_id` is fillable and stamped on every row (`app/Models/WhatsappOutboxMessage.php:14`). |
| **Idempotency key** | None. Uniqueness is the `(campaign_id, contact_list_entry_id)` pair used by `firstOrCreate` (`app/Jobs/DispatchCampaignJob.php:195-198`). | `idempotency_key`, deterministically derived (`app/Services/WhatsappOutboxService.php:189-203`). |

Both mechanisms satisfy D-21/D-22 for their own effect. Neither can be substituted for the other
without a data migration and a behaviour change.

---

## 5. Complementary-Message Collision Scenarios

All eight D-31 collision points, restated for this path. "Complementary message" here means a
genuine inbound customer message arriving while a campaign template is queued or being sent to that
same recipient. Note that on this path the arriving message does **not** race a response the system
is composing — there is nothing to compose — so several points are structurally inapplicable and are
marked as such rather than omitted.

| # | Collision point | Applicable? | Current outcome |
|---|---|---|---|
| 1 | **Arrival during collection** | **Not applicable** | This path has no collection window: it is not responding to a customer message. Its nearest analogue is the fan-out's opt-out pre-suppression (`app/Jobs/DispatchCampaignJob.php:162-164`), which is a one-shot eligibility decision, not a window. An inbound arriving at this moment is handled entirely by Path 1. |
| 2 | **Arrival during model work** | **Not applicable — no model call on this path** | Neither `DispatchCampaignJob` nor `SendCampaignMessageJob` constructs an agent or calls `AgentService`; the message body is a pre-approved Meta template with positional parameters (`app/Jobs/SendCampaignMessageJob.php:867-919`). There is no reasoning interval for a message to arrive during. |
| 3 | **Arrival during internal work** | **Not applicable — no model call on this path** | The only "internal work" is parameter resolution and configuration lookup, both deterministic and sub-millisecond. No tool is invoked, no external interpretation is produced, and nothing could be invalidated by a newer message. |
| 4 | **Arrival during external action** | **Applicable, partially covered** | The external action is the template POST. **Repetition is genuinely covered**: the atomic claim (`app/Models/CampaignMessage.php:198-243`) plus the ownership-guarded terminals (`:334-468`) plus the lease-expiry reconciliation (`:267-332`) implement D-21/D-22 on this path's own terms. **Relevance is not covered**: nothing consults the lead's conversation state before the POST (P4-F08), so a bulk template can land in the middle of a live agent conversation or an active human handoff. The one relevance-like check that does exist is consent (P4-F02), which is narrower than currency. |
| 5 | **Arrival during response queueing** | **Not applicable** | There is no response to queue. The `queued` transition (`app/Jobs/SendCampaignMessageJob.php:220`) is a status flip on an already-existing row, not the construction of an answer, and it is immediately followed by the send in the same job invocation. |
| 6 | **Arrival during partial send** | **Not applicable** | A campaign message is never split into parts — it is one template with one POST. D-23's cancellation problem, which is the sharpest gap on Paths 1 and 3, has no instance here. The nearest analogue is a *campaign-wide* partial state (some recipients sent, some queued), and that **is** cancellable: pausing the campaign parks every not-yet-sent row back to `pending` (`app/Jobs/SendCampaignMessageJob.php:126-144`) instead of sending it. Recorded because it is the one place in the inventory where a bulk cancellation actually works. |
| 7 | **Arrival during retry** | **Applicable** | Retries are bounded by `retryUntil()` (`app/Jobs/SendCampaignMessageJob.php:83-94`, default 6 h from the message's own slot) and by `maxExceptions = 3`. A retry re-runs `handle()` from the top, so campaign state, daily limit, **consent** and template approval are all re-evaluated — genuinely better than Path 1's outbox retry, which re-sends its frozen payload with no re-check at all. What is still not re-evaluated is the recipient's conversation state. A template composed and queued hours earlier can still be delivered. |
| 8 | **Arrival during crash recovery** | **Applicable** | Three distinct recovery mechanisms, all present. A crashed fan-out is resumed by `attemptResume()` (`app/Jobs/DispatchCampaignJob.php:285-321`), config-gated (P4-F07). A crashed send with an outstanding claim is resolved by the lease: an expired lease becomes terminal `in_doubt` on the next execution (`app/Jobs/SendCampaignMessageJob.php:114-124`), and `failed()` schedules an explicit expiry probe (`:816`, `:831-854`) or fails closed (`:817`). The campaign cannot deadlock. What is missing is the same thing missing everywhere: no stop-point evidence records how far the crashed execution got, only that it failed. |

---

## 6. Latest-State Autonomy Comparison

The ten CONTEXT.md decisions against current behaviour on this path. Several concern a
conversational response this path never produces; the verdict is still stated with the vocabulary
`implemented` / `partial` / `absent`, and the justification explains why.

| Decision | Verdict | Justification |
|---|---|---|
| **D-06** — a newer relevant message makes any unsent response obsolete immediately | `absent` | No code on this path evaluates relevance. The gates re-read before the POST are campaign state, daily limit, consent and template approval (`app/Jobs/SendCampaignMessageJob.php:126-265`); none of them reads `last_inbound_at`, the timeline, or the lead's automation state. A queued template is sent regardless of what the customer has said since (P4-F08). |
| **D-07** — the obsolete execution stops at the next safe point and never regains permission to answer | `absent` | There is no obsolescence concept, so nothing stops. The path does have well-defined safe points — the state gate, the consent gate, the lease claim — but each answers "may this send proceed at all", never "is this send still current". |
| **D-08** — re-evaluate from the latest complete state; only the current execution answers | `absent` | Nothing is re-evaluated from conversation state and there is no notion of a current execution. The lease establishes *exclusivity* (one worker owns the POST) which is genuinely stronger than anything on Paths 1 and 3 — but exclusivity is not currency, and D-25 explicitly forbids counting it as such. |
| **D-15** — one current execution authority per conversation, tied to the exact current state | `absent` | The attempt token (`app/Models/CampaignMessage.php:198-243`) is the closest artefact in the codebase to an authority: it is minted, held with an expiry, checked at every terminal transition, and revoked on release. But it is tied to a **row**, not to a conversation, and it encodes **who is POSTing**, not **what state justified the POST**. Phase 62 should treat it as the best available implementation shape and not as an existing D-15 authority. |
| **D-16** — authority is temporary and renewable; a new relevant message revokes it, a crash expires it | `partial` | The crash half is genuinely implemented, uniquely on this path: `provider_attempt_lease_expires_at` + `hasActiveProviderAttemptLease()` (`app/Models/CampaignMessage.php:260-265`) make the claim expire on its own, and `markAbandonedProviderAttemptInDoubt()` (`:267-298`) resolves it conservatively rather than unblocking it silently. The revocation half is absent: no message revokes anything, because nothing observes messages. |
| **D-17** — authority checked throughout the cycle **and again at the real send boundary** | `partial` | A check does happen at the real send boundary — `claimProviderAttempt()` is executed immediately before the POST (`app/Jobs/SendCampaignMessageJob.php:389-405`), which is exactly the placement D-17 demands. What it checks is ownership of the *effect*, not currency of the *content*. Scored `partial` deliberately: the mechanism D-17 asks for exists here, aimed at the wrong question. |
| **D-18** — an outdated request must not return an old answer as if current | `absent` | D-18 is written for the direct API path. Its principle — supersession made explicit in the trace — has no implementation here: neither `campaign_messages` nor the `outbound_sent` event carries any field able to say "sent, but no longer apt", so a stale bulk send is reported to operators and dashboards exactly like a timely one. |
| **D-23** — cancel every not-yet-sent part as soon as the response becomes obsolete | `partial` | There are no parts to cancel (§ 5 #6), so the literal decision is inapplicable — but its *shape* is implemented at campaign granularity and works: pausing the campaign parks every not-yet-sent message back to `pending` (`app/Jobs/SendCampaignMessageJob.php:126-144`) and the fan-out will only re-enqueue orphaned `pending` rows (`app/Jobs/DispatchCampaignJob.php:186-199`). Scored `partial` rather than `absent` because a working bulk-cancellation exists; it is triggered by an operator, never by a customer message. |
| **D-24** — an already-sent part stays canonical history; the next execution sees it and continues naturally | `partial` | The preservation half works and is the reason the reply bridge exists: a confirmed send is mirrored into `conversation_timeline_messages` (`app/Services/CampaignConversationTimelineWriter.php:47`), and a reply triggers `backfillForLead()` (`:111`) so the conversation opens showing the message that started it. The gap: the mirror is best-effort and never throws (`:30`), so a mirror failure silently produces a conversation with a reply and no visible trigger — and nothing marks that a template was sent but not mirrored. |
| **D-25** — serial processing alone is not evidence against stale or out-of-order replies | `absent` | This path has no serial processing to over-trust — sends for one campaign run concurrently across workers by design. The warning binds Phase 62 here in a specific form: **the attempt-token lease must not be counted toward D-25**. It proves exactly one worker POSTed, which is a duplicate-prevention proof, not an ownership-at-the-send-boundary proof in D-25's sense. |

---

## 7. Evidence Available Today

Per-path verdicts. These differ from the repository-wide table in `61-RESEARCH.md` § Evidence Field
Mapping, which is the starting reference, not the answer for this path. The pattern to notice is the
**inverse of Path 3**: Path 3 has model-side gaps with an intact send boundary; Path 4 has the
strongest external-action evidence in the inventory and no model-side evidence to have a gap in.

| # | Evidence field | Verdict | What exists on Path 4 |
|---|---|---|---|
| 1 | Collection window | `GAP` | No collection window exists on this path (§ 5 #1), so nothing is recorded. The fan-out's opt-out pre-suppression leaves no per-recipient trace at all — a suppressed entry produces neither a `campaign_messages` row nor an event, so "how many recipients were dropped for consent at dispatch" is unrecoverable. |
| 2 | Execution start | `PARTIAL` | `campaign_dispatch_started` (`app/Jobs/DispatchCampaignJob.php:67-75`) marks the fan-out's start, and `outbound_queued` (`app/Jobs/SendCampaignMessageJob.php:222-234`) marks a send's start. What does not exist is the structured counterpart Paths 1 and 2 have: no `ai_runs.started_at`, because there is no run. Start time is readable from the event trail only. |
| 3 | Supersession | `GAP` | No `execution_superseded` event type exists in the codebase. Pinned as a negative assertion by the characterization test. |
| 4 | Stop point | `GAP` | An execution never stops for relevance. The several early returns (`paused`, `daily limit`, `opted out`, `template not approved`) record *why a send never proceeded*, which is a different fact, and a crashed fan-out records only that it failed, never how far it got (P4-F07). |
| 5 | Restart count | `GAP` | Job `attempts()` is deliberately made meaningless on this path — `retryUntil()` makes the worker ignore it so a throttle release is not counted as a try (`app/Jobs/SendCampaignMessageJob.php:48-53`). No column counts genuine re-executions. Recording `attempts()` as a restart count would be a category error here even more than elsewhere. |
| 6 | Preserved result references | `GAP` | No preservation or reuse concept. `template_params_resolved` is the closest artefact — the resolved parameters are persisted on the row (`app/Jobs/SendCampaignMessageJob.php:220`) and reused verbatim on a retry — but it is a frozen input, not a referenced prior result with provenance. |
| 7 | External-action outcome | `EXISTS` | **The strongest instance of this field anywhere in the inventory.** `campaign_messages.status` spans `pending, queued, in_doubt, sent, delivered, read, failed, skipped` with a declared progression (`app/Models/CampaignMessage.php:71-95`), alongside `provider_attempted_at`, `provider_attempt_token`, `provider_attempt_lease_expires_at`, `provider_retry_not_before`, `provider_message_id` and the full provider-error quintuple. Confirmed / proven-not-performed / uncertain are distinguished *and* an abandoned attempt self-resolves to uncertain rather than lingering. Events: `outbound_sent`, `outbound_failed`, `outbound_in_doubt`, `outbound_throttled`, `outbound_skipped_optout`, `outbound_retrying`. Delivery receipts extend it past acceptance to `delivered`/`read`, which no other path has. |
| 8 | Obsolete response blocked | `GAP` | Nothing blocks a send as obsolete, so there is nothing to record. `skipped`/`OPTED_OUT` blocks a send as **non-consented**, which is a different and narrower fact — it must not be read as an implementation of this field. |
| 9 | Execution that sent | `PARTIAL` | Answerable, but not through the correlation id the rest of the system uses. `campaign_messages.provider_message_id` plus the `outbound_sent` event's payload identify the send; the `<interaction_id>` on that event is unique to the send job and unlinked to the dispatch that created it (P4-F05). And, as everywhere, nothing ever adjudicated that this execution was *entitled* to send — the lease proves exclusivity, not entitlement. |
| 10 | Elapsed time | `GAP` | `ai_runs.duration_ms` is the only per-turn duration in the system and there is no run here. Timestamps exist (`created_at` → `sent_at`) and bound the wait, but they measure queue latency, not work. Time-to-current-response in the D-30 sense is not defined for this path. |
| 11 | Cost | `GAP` | No LLM spend exists, so `estimated_cost_usd` is correctly absent — but the **Meta conversation cost** a template send initiates is not recorded anywhere either. Cost per completed conversation cycle (D-26/D-30) is therefore not computable for a campaign-originated conversation, and the gap compounds with P4-F04: neither half of the cost is captured. |

---

## 8. Characterization Test Reference

**File:** `tests/Feature/CampaignDispatchCharacterizationTest.php`

**Command:**

```
php artisan test --compact --filter=CampaignDispatchCharacterization
```

The test is a Kent-Beck characterization oracle: it asserts the **current** behaviour of unmodified
production code as a receipt for Phase 62, not a specification. It pins five facts — the fan-out
contract (one send job per eligible recipient, opt-outs pre-suppressed, one distinct correlation id
per recipient, campaign events carrying `tenant_id` but no `lead_id`); **[P4-F01](#p4-f01), that a
completed send writes a `campaign_messages` row and no `whatsapp_outbox_messages` row at all**, with
the divergent lease column sets asserted directly against the schema so the two mechanisms cannot be
conflated by reading; the lease's idempotency in all three of its states (a confirmed send is never
re-POSTed, an active lease defers to its owner, an expired lease becomes terminal `in_doubt` without
counting as a failure); the send-time consent re-check ([P4-F02](#p4-f02)); and **[P4-F04](#p4-f04),
that the path's complete successful-send event vocabulary is exactly `outbound_queued`,
`outbound_sent`, with no `ai_runs` row** — asserted exhaustively rather than by containment, so the
day a `model_called` or `fact_check_*` row can appear on this path, the test fails.

When Phase 62 unifies the campaign lease with the outbox, or routes campaign sends through a shared
gateway, this file is **expected to fail** — starting with the `WhatsappOutboxMessage::doesntExist()`
assertion and the `Schema::hasColumn()` assertions. It must then be rewritten deliberately as part of
that change, never quietly adjusted to describe the new behaviour, which would erase the
before/after this phase exists to create.

**Fixture-accuracy note.** The provider is mocked at the `WhatsAppProviderFactory` seam, the idiom
already established in `tests/Feature/Jobs/CampaignDispatchTest.php`, so the real jobs, the real
campaign-state gates, the real consent re-check, the real throttles and the real `CampaignMessage`
lease all execute — only the Meta POST is replaced. **No real Meta template approval is required**,
which matters because `61-VALIDATION.md` lists this path's golden trace under "Manual-Only
Verifications" on the assumption that template approval could not be faked. It can: the send config
is validated against a locally-created `WhatsappTemplate` row with `status = 'APPROVED'`, so the
whole send chain is exercised in CI. The remaining production-only facts for this path are traffic
volume and real delivery-receipt timing, not reachability.

**Fixture-accuracy caveat.** Concurrency is modelled by seeding a row's lease state and re-invoking
the job, the established repository idiom (PHP/Pest is single-threaded per test). That is faithful
for the *outcome* of a lost claim, which is what `claimProviderAttempt()`'s conditional `UPDATE`
guarantees at the database level; it does not exercise two genuinely simultaneous workers. The
atomicity itself is a property of the single-statement `UPDATE`, not of the fixture.
