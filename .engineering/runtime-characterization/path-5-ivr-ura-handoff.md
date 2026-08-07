# Path 5 — IVR/URA WhatsApp Handoff

Requirement: RUNT-01
Success criteria: SC3
Schema version: 1
Characterized: 2026-08-05
Characterization test: IvrHandoffCharacterization

> D-29 binds this document. No raw phone number, message body, CPF, financial figure, credential
> or tool payload appears here. Identifiers are written as placeholders (`<call_id>`, `<lead_id>`,
> `<tenant_id>`, `<voice_instance_id>`, `<ura_api_key_id>`, `<interaction_id>`) and behaviour is
> described by classification, status and column name.

> **This dossier corrects a stale research claim, and the correction is the finding.**
> `61-RESEARCH.md` states that a grep across all three jobs on this path confirmed **no**
> `AgentInteractionEventService` calls anywhere. That is false for two of the three:
> `SendInboundLeadWhatsAppJob` and `SendUraTemplateJob` have written interaction events since the
> repository's first commit (`d5ecdb2`, 2026-06-12); only `SendPostCallWhatsAppJob` has none. The
> three jobs were written from one template and have **since diverged** — in their evidence, in
> their tenant checks, and, most seriously, in their retry semantics, where two copies fail in
> **opposite directions**. Per D-01 that is exactly why three copies must not be characterized as
> one mechanism. See [P5-F01](#p5-f01) and [P5-F02](#p5-f02); both halves are measured by
> `IvrHandoffCharacterizationTest`, not argued.

---

## 1. Entry & Trigger

**Three jobs, three triggers, one shape.** This path is not one mechanism. It is **three
independently-maintained copies** of a common structure, each reached from a different external
event:

| Job | Trigger | Constructor identity |
|---|---|---|
| `SendPostCallWhatsAppJob` (`app/Jobs/SendPostCallWhatsAppJob.php`) | A Twilio IVR call completes with an interested outcome | `int $voiceCampaignCallId` (`app/Jobs/SendPostCallWhatsAppJob.php:34-38`) |
| `SendInboundLeadWhatsAppJob` (`app/Jobs/SendInboundLeadWhatsAppJob.php`) | An inbound URA call identifies a lead | `int $voiceInstanceId, string $phone, ?string $name` (`app/Jobs/SendInboundLeadWhatsAppJob.php:34-40`) |
| `SendUraTemplateJob` (`app/Jobs/SendUraTemplateJob.php`) | An external URA system calls the trigger API with a `UraApiKey` | `int $uraApiKeyId, string $phone, ?string $name, array $variables` (`app/Jobs/SendUraTemplateJob.php:37-44`) |

All three declare identical queue posture — `tries = 3`, `timeout = 60`, `backoff() = [10, 30, 60]`,
queue `messages` (`app/Jobs/SendPostCallWhatsAppJob.php:25-38`,
`app/Jobs/SendInboundLeadWhatsAppJob.php:25-40`, `app/Jobs/SendUraTemplateJob.php:24-43`) — and all
three follow the same five-step shape: resolve the WhatsApp instance, take a lead-creation lock,
resolve and gate an `APPROVED` Meta template, take a bespoke cache send-claim, POST directly through
`WhatsAppService`. **Per D-01, three copies are not "a shared mechanism characterized once."** They
share no code: not a base class, not a trait, not a service. Everything below that differs between
them is a divergence that has already happened, not a hypothetical one.

**Authentication.** None at the job layer, and none is possible: all three run in queue context.
`SendUraTemplateJob` is the only one whose upstream trigger is an authenticated external request
(`UraApiKey`), and it is also the only one that re-verifies that authority inside the job (below).

**Tenant resolution — and the one copy that guards it.** Tenant identity comes from the loaded
`VoiceInstance` or `UraApiKey`. `BelongsToTenant`'s global scope is inert in queue context, so the
lead-creation `firstOrCreate` filters explicitly on `tenant_id`
(`app/Jobs/SendPostCallWhatsAppJob.php:61-64`, `app/Jobs/SendInboundLeadWhatsAppJob.php:68-71`,
`app/Jobs/SendUraTemplateJob.php:114-117`) and the lock key is tenant-scoped
(`app/Jobs/SendPostCallWhatsAppJob.php:53`, `app/Jobs/SendInboundLeadWhatsAppJob.php:59`,
`app/Jobs/SendUraTemplateJob.php:105`). **Only `SendUraTemplateJob` additionally cross-checks that
the agent, the template and the WhatsApp instance all belong to the API key's tenant**
(`app/Jobs/SendUraTemplateJob.php:66-93`), comparing as strings and failing closed with a warning.
The other two copies have no equivalent gate — they trust the relationship graph. Recorded because a
reader who characterizes one copy will assume the guard exists in all three.

**Synchronous vs queued.** Queued, single hop. There is **no outbox hop**: the provider call happens
inside this job. That is the structural difference from Paths 1, 3 and 4, and it is the root of most
of § 4.

**No model call.** No agent is constructed and `AgentService` is never called. As on Path 4, the
conversational latest-state decisions do not directly apply — but unlike Path 4, the external-effect
decisions D-21/D-22 are **not** implemented here either ([P5-F02](#p5-f02)).

---

## 2. Ordered Call Chain

`SendPostCallWhatsAppJob` is the representative chain; every step notes where the other two copies
diverge.

1. **Resolve the call and the WhatsApp instance** — `handle()`
   (`app/Jobs/SendPostCallWhatsAppJob.php:39-128`) eager-loads
   `voiceCampaign.voiceInstance.whatsappInstance` and `…postCallMetaTemplate` at
   `app/Jobs/SendPostCallWhatsAppJob.php:42`. A missing instance logs
   `ivr.no_whatsapp_instance` and returns (`:45-49`).
   **Divergence:** `SendInboundLeadWhatsAppJob` mints an `<interaction_id>` *before* this step
   (`app/Jobs/SendInboundLeadWhatsAppJob.php:44-45`) and includes it in the warning
   (`:49-52`); `SendUraTemplateJob` does the same (`app/Jobs/SendUraTemplateJob.php:48-49`,
   `:95-98`) and runs its three tenant-mismatch gates first (`:65-92`). The post-call copy mints
   nothing.

2. **Idempotent lead creation under a distributed lock** —
   `Cache::lock("lead_create_{<tenant_id>}_{<phone>}", 8)->block(5, …)` wrapping
   `Lead::firstOrCreate` (`app/Jobs/SendPostCallWhatsAppJob.php:53-65`). The phone is normalised
   only by `ltrim($call->phone, '+')` (`:51`) — this path does **not** use
   `PhoneNumberValidator`, unlike Path 4. The created lead is stamped `modo = 'receptivo'` and
   carries the legacy column `evolution_instance` (`:59`), a **dead column name** surviving from
   the retired provider; Evolution itself is fully removed (D-03) and nothing on this path
   references an `Evolution*` class.
   **Divergence:** identical in all three, except the other two also set `nome`
   (`app/Jobs/SendInboundLeadWhatsAppJob.php:66`, `app/Jobs/SendUraTemplateJob.php:112`).

3. **Contact sync** — `ContactSyncService::syncFromLead($lead, Contact::SOURCE_URA)`
   (`app/Jobs/SendPostCallWhatsAppJob.php:66`), then `$lead->refresh()` (`:68`). Identical in all
   three (`app/Jobs/SendInboundLeadWhatsAppJob.php:73-74`, `app/Jobs/SendUraTemplateJob.php:119-120`).
   **Divergence:** the two URA copies record `ura_inbound_received` immediately after
   (`app/Jobs/SendInboundLeadWhatsAppJob.php:76-86`, `app/Jobs/SendUraTemplateJob.php:122-133`).
   The post-call copy records nothing.

4. **A configured message is loaded and then never used** —
   `app/Jobs/SendPostCallWhatsAppJob.php:70-72` resolves
   `$call->voiceCampaign->post_call_message ?? $voiceInstance->post_call_message ?? '<default>'`
   into a local that **is never referenced again**. The same dead local exists at
   `app/Jobs/SendInboundLeadWhatsAppJob.php:89-90`. What actually goes out is the template.
   See [P5-F06](#p5-f06).

5. **Template resolution and the `APPROVED` gate** —
   `app/Jobs/SendPostCallWhatsAppJob.php:73-79`. A missing or non-`APPROVED` template logs
   `ivr.meta_template_unavailable` and returns, **leaving the lead created** — CRM persistence is
   deliberately not blocked by the send failing.
   **Divergence:** the other two also record an `outbound_failed` interaction event on this branch
   (`app/Jobs/SendInboundLeadWhatsAppJob.php:99-106`, `app/Jobs/SendUraTemplateJob.php:141-148`),
   and `SendUraTemplateJob` gates on `$template->isApproved()` rather than a literal string
   comparison (`app/Jobs/SendUraTemplateJob.php:135`).

6. **The bespoke per-send claim** —
   `Cache::add("postcall_send:{<call_id>}", 1, now()->addMinutes(10))`
   (`app/Jobs/SendPostCallWhatsAppJob.php:83-91`). A losing caller logs
   `ivr.whatsapp_send_already_claimed` and returns. The inline comment says it mirrors
   `ProcessLeadFollowUpJob`'s F7 claim — it is a **copy of that pattern, not a call into it**.
   **Divergence — three different key shapes, therefore three different idempotency scopes:**
   per call id here; `ura_inbound_send:{<voice_instance_id>}:{<phone>}`
   (`app/Jobs/SendInboundLeadWhatsAppJob.php:113`); and
   `ura_template_send:{<ura_api_key_id>}:{<phone>}:{md5(variables)}`
   (`app/Jobs/SendUraTemplateJob.php:157`), where varying the variables produces a different key
   and therefore permits an immediate second send to the same phone.

7. **The direct provider POST — no outbox** —
   `WhatsAppService::sendTemplateViaInstance($whatsappInstance, $phone, $templateName, $language)`
   (`app/Jobs/SendPostCallWhatsAppJob.php:94-99`). `WhatsappOutboxService` is never called and no
   `whatsapp_outbox_messages` row exists for this path.
   **Divergence:** `SendUraTemplateJob` passes a fifth argument, the built Meta components
   (`app/Jobs/SendUraTemplateJob.php:168-174`, built at `:154`, `:217-232`).

8. **The retry-safety decision — where the copies contradict each other** —
   `app/Jobs/SendPostCallWhatsAppJob.php:101-110` wraps the POST in `try/catch (Throwable)` and
   **unconditionally** `Cache::forget($sendClaimKey)` at
   `app/Jobs/SendPostCallWhatsAppJob.php:106` before rethrowing.
   **Divergence:** neither `SendInboundLeadWhatsAppJob` nor `SendUraTemplateJob` has a `try/catch`
   at all (`app/Jobs/SendInboundLeadWhatsAppJob.php:124-129`,
   `app/Jobs/SendUraTemplateJob.php:168-174`), so their claims survive the full 10-minute TTL.
   See [P5-F02](#p5-f02) — this single divergence inverts the failure mode.

9. **Timeline mirror, best effort** — `TemplateTimelineRecorder::record()`
   (`app/Jobs/SendPostCallWhatsAppJob.php:113-118`) writes one `outbound` row into
   `conversation_timeline_messages`, idempotent on `provider_message_id`, never throwing into its
   caller (`app/Services/WhatsApp/TemplateTimelineRecorder.php:43-90`).
   **Divergence:** the other two pass `interactionId: $interactionId`
   (`app/Jobs/SendInboundLeadWhatsAppJob.php:138`, `app/Jobs/SendUraTemplateJob.php:184`), so their
   timeline row is correlatable; the post-call copy's row carries `interaction_id = NULL`, asserted
   by the characterization test.

10. **Success logging and downstream fan-out** —
    `Log::info('ivr.whatsapp_sent', …)` (`app/Jobs/SendPostCallWhatsAppJob.php:120-124`) then
    `DashboardMetricsService::dispatchUpdate()` (`:127`).
    **Divergence:** the other two log `ura.inbound_whatsapp_sent` / `ura.trigger.sent`
    (`app/Jobs/SendInboundLeadWhatsAppJob.php:141-146`, `app/Jobs/SendUraTemplateJob.php:187-193`)
    and record an `outbound_sent` interaction event
    (`app/Jobs/SendInboundLeadWhatsAppJob.php:148-159`, `app/Jobs/SendUraTemplateJob.php:195-207`);
    neither dispatches the dashboard recompute.

11. **Terminal failure — log only, on all three** — `failed()`
    (`app/Jobs/SendPostCallWhatsAppJob.php:129-135`,
    `app/Jobs/SendInboundLeadWhatsAppJob.php:162-169`,
    `app/Jobs/SendUraTemplateJob.php:243-250`) writes a single `Log::error` and nothing else. No
    row, no event, no status. This is the pattern Plan 61-01 deliberately replaced on the inbound
    and outbox jobs; **none of the three copies here received that treatment**. See
    [P5-F04](#p5-f04).

---

## 3. Golden Trace

One successful execution on the representative job: a Twilio IVR call completes with an interested
outcome, the lead is created, the `APPROVED` post-call template is accepted by Meta.

**Join key — there is none on the representative job.** `SendPostCallWhatsAppJob` never mints an
`<interaction_id>`, so nothing on the successful path can be joined to anything else. The only
durable correlators are `<call_id>` (in the log lines) and `<lead_id>`. The other two copies **do**
mint one (`app/Jobs/SendInboundLeadWhatsAppJob.php:44-45`,
`app/Jobs/SendUraTemplateJob.php:48-49`) and thread it into their events and their timeline row —
so a trace of this path is only correlatable for two of its three entry points.

**This section is the most consequential on this path.** State it plainly: **a golden trace for
`SendPostCallWhatsAppJob` cannot be assembled from the evidence tables at all.** It has to be
reconstructed from structured log lines — `ivr.whatsapp_sent`, `ivr.no_whatsapp_instance`,
`ivr.meta_template_unavailable`, `ivr.whatsapp_send_already_claimed`, `ivr.whatsapp_failed` — plus
the `voice_campaign_calls` and `leads` rows and one `conversation_timeline_messages` row that
carries no interaction id. Logs are not a queryable evidence table, are not retained under the
`laboratory.retention` policy that governs `agent_interaction_events`, and cannot be joined to a
tenant without parsing. **This constrains what any trace of this job can show today**, and it is
why field 7 in § 7 is a `GAP` here while it is `EXISTS` on every other path.

| Evidence table | Rows for one successful execution | Notes |
|---|---|---|
| `agent_interaction_events` | **`SendPostCallWhatsAppJob`: no row. `SendInboundLeadWhatsAppJob` and `SendUraTemplateJob`: two rows each.** | The divergence is [P5-F01](#p5-f01). The post-call copy contains no reference to `AgentInteractionEventService` anywhere — not on success, not on either skip branch, not in `failed()` — and its absence is pinned by a `doesntExist()` assertion. The two URA copies write `ura_inbound_received` (`app/Jobs/SendInboundLeadWhatsAppJob.php:76-86`, `app/Jobs/SendUraTemplateJob.php:122-133`) and `outbound_sent` (`:149-160`, `:196-208`), plus `outbound_failed` on the unavailable-template branch (`:100-107`, `:142-149`). Those rows carry `tenant_id` as a string **and** `lead_id`, because they go through `recordForLead()` — so they are strictly better correlated than Path 4's campaign events, which carry no lead. Measured: the characterization test asserts the post-call job's total absence and the URA copy's exact vocabulary in the same run. |
| `ai_runs` | **No row, on all three** | No agent is constructed and `AgentService::process()` — the only caller of `AiRunRecorder::start()` (`app/Services/AgentService.php:112-117`) — is never reached. As on Path 4 this is not a bypass of an expected runtime: there is no model turn to record. Pinned by a `doesntExist()` assertion. |
| `whatsapp_outbox_messages` | **No row, on all three** | [P5-F03](#p5-f03). The POST goes straight through `WhatsAppService` (`app/Jobs/SendPostCallWhatsAppJob.php:94-99`). There is no durable row to carry `status`, `provider_attempted_at`, `idempotency_key` or `in_doubt`, so the entire send-boundary mechanism Paths 1 and 3 rely on, and the parallel one Path 4 implements, has **no counterpart here**. Pinned by a `doesntExist()` assertion. |
| `campaign_messages` | **No row** | Written only by Path 4. A `VoiceCampaign` is a *voice* campaign and is unrelated to the WhatsApp `Campaign` model. |
| `followup_messages` | **No row** | Written only by Path 3. A lead created here can later become follow-up-eligible, but this path writes nothing to that table. |
| `voice_campaign_calls` | **No new row; the existing row is read, not written** | `SendPostCallWhatsAppJob` loads the call at `app/Jobs/SendPostCallWhatsAppJob.php:42` and never updates it — not on success, not on failure. The row's `status` was set by the upstream IVR outcome handler, so it records that the *call* was interesting, never that the *handoff* happened. The other two copies do not touch this table at all. |

**Supporting rows outside the six tables:** one `conversation_timeline_messages` row per confirmed
send (`app/Services/WhatsApp/TemplateTimelineRecorder.php:43-90`), carrying `interaction_id = NULL`
on the post-call copy and a real id on the other two; and the `Lead` row itself, plus the `Contact`
row synced by `ContactSyncService::syncFromLead()`.

---

## 4. Failure Map

<a id="p5-f01"></a>
<a id="p5-f02"></a>
<a id="p5-f03"></a>
<a id="p5-f04"></a>
<a id="p5-f05"></a>
<a id="p5-f06"></a>

| ID | Failure mode | Trigger | Current behaviour | Evidence produced | Labeled finding |
|---|---|---|---|---|---|
| P5-F02 | **D-21 and D-22 are not implemented on this path at all — and the three copies disagree about which way to fail** | Any exception from the provider call | There is no ambiguity state anywhere on this path: no `in_doubt` status, no `provider_attempted_at` stamp, no reconciliation, no probe. The only lever is a 10-minute cache claim, and the copies use it in **contradictory** ways. `SendPostCallWhatsAppJob` catches every `Throwable` and `Cache::forget()`s the claim **unconditionally** before rethrowing (`app/Jobs/SendPostCallWhatsAppJob.php:101-110`, the forget at `:107`), so **every failure is treated as safe-to-retry**: a timeout *after* Meta accepted the template is recorded as "proven not performed" and the retry **re-sends** — a duplicate the customer sees and nothing records. `SendInboundLeadWhatsAppJob` and `SendUraTemplateJob` have **no `try/catch` at all** (`app/Jobs/SendInboundLeadWhatsAppJob.php:124-129`, `app/Jobs/SendUraTemplateJob.php:168-174`), so their claims survive the full TTL and every retry inside it — `backoff() = [10, 30, 60]`, so all of them — short-circuits at the claim gate: a connection refused that provably sent nothing results in the message being **silently dropped**. **This is a genuinely less mature gap than Paths 1 and 4, not merely an undocumented one.** Path 1 has `in_doubt` + `provider_attempted_at` + `MetaAmbiguousSendException` (`app/Jobs/ProcessWhatsappOutboxMessageJob.php:68-72`, `:88`, `:145-149`); Path 4 has an atomic token lease with expiry-driven `in_doubt` reconciliation (`app/Models/CampaignMessage.php:198-332`). This path has **neither**, and cannot acquire one without a durable row to put it on. | Only `Log::error('ivr.whatsapp_failed' \| 'ura.inbound_whatsapp_failed' \| 'ura.trigger.failed')` from `failed()`. Nothing distinguishes a duplicate send from a first send, or a dropped message from a delivered one. Both halves are measured by `IvrHandoffCharacterizationTest`: the post-call retry is asserted to reach the provider a second time, and the URA copy's retry is asserted **not** to. | — (new; documented-not-fixed, Phase 62. Threat T-61-22) |
| P5-F01 | **The three copies have diverged in their evidence: one produces none at all** | Every execution of `SendPostCallWhatsAppJob` | `SendPostCallWhatsAppJob` contains no reference to `AgentInteractionEventService` on any branch, so a customer-visible message leaves the platform with **zero interaction-correlated evidence** and no `<interaction_id>` is ever minted for it. `SendInboundLeadWhatsAppJob` (`:43-44`, `:77-87`, `:100-107`, `:149-160`) and `SendUraTemplateJob` (`:47-48`, `:123-134`, `:142-149`, `:196-208`) do mint one and do record events, through `recordForLead()` so the rows carry both `tenant_id` and `lead_id`. **`61-RESEARCH.md`'s claim that a grep across all three files confirmed no such calls is stale and is corrected here.** Per D-01 the correct unit of characterization is the copy, not the path. **Disposition: documented, not fixed in Phase 61** — wiring events into the post-call copy changes runtime behaviour. | For the post-call copy: log lines only, plus a `conversation_timeline_messages` row whose `interaction_id` is `NULL`. Both the absence and its inverse are pinned in one test run. | — (new; documented-not-fixed, Phase 62. Threat T-61-21) |
| P5-F03 | **The outbox is bypassed entirely, so there is no durable send record to reason about** | Every execution, all three copies | `WhatsAppService::sendTemplateViaInstance()` is called directly (`app/Jobs/SendPostCallWhatsAppJob.php:94-99`, `app/Jobs/SendInboundLeadWhatsAppJob.php:124-129`, `app/Jobs/SendUraTemplateJob.php:168-174`). No `whatsapp_outbox_messages` row is ever created, so there is no `idempotency_key`, no `status`, no `provider_attempted_at`, no `attempts` counter and nowhere for `in_doubt` to live — which is *why* P5-F02 cannot be fixed by a small change. The provider message id returned by the call is passed to the timeline recorder and then discarded; **it is not persisted in any queryable column**, so a delivery-status webhook echoing that id has nothing on this path to resolve against. | The absence itself, pinned by a `doesntExist()` assertion. | — (new) |
| P5-F04 | **`failed()` is log-only on all three, so terminal failure is not tenant-attributable** | Retries exhausted on any of the three jobs | `app/Jobs/SendPostCallWhatsAppJob.php:129-135`, `app/Jobs/SendInboundLeadWhatsAppJob.php:162-169` and `app/Jobs/SendUraTemplateJob.php:243-250` each write one `Log::error` and return. No row, no interaction event, no status change. Contrast Plan 61-01, which added exactly this evidence to `ProcessIncomingWhatsAppMessageJob` (`app/Jobs/ProcessIncomingWhatsAppMessageJob.php:312-349`) and `ProcessWhatsappOutboxMessageJob` (`:286-327`), mirroring `ProcessLeadFollowUpJob::failed()` (`app/Jobs/ProcessLeadFollowUpJob.php:386-428`). These three were not in that plan's scope and still carry the old pattern, so a terminally failed IVR handoff is visible only in the tenant-less global `failed_jobs` table. | `failed_jobs` (no `tenant_id` column) plus an unstructured error log. Not queryable per tenant. | — (new; same class as SC1's original gap, on jobs SC1 did not cover) |
| P5-F05 | **Three different claim-key shapes mean three different idempotency scopes, one of them trivially bypassable** | A repeat trigger for the same recipient | `postcall_send:{<call_id>}` (`app/Jobs/SendPostCallWhatsAppJob.php:83`) is per call — a second IVR call to the same person legitimately re-sends. `ura_inbound_send:{<voice_instance_id>}:{<phone>}` (`app/Jobs/SendInboundLeadWhatsAppJob.php:113`) is per instance+phone — a second URA call within ten minutes is suppressed. `ura_template_send:{<ura_api_key_id>}:{<phone>}:{md5(variables)}` (`app/Jobs/SendUraTemplateJob.php:157`) folds the **variables** into the key, so an external caller that changes any variable gets a fresh key and an immediate second template to the same phone inside the window. None of the three is wrong on its own terms; the point is that "the IVR path's idempotency window" is not a single, statable property. | None. A suppressed send logs `*_already_claimed`; a permitted one is indistinguishable from a first send. | — (new) |
| P5-F06 | **The operator-configured `post_call_message` is loaded and then silently ignored** | Every execution of the two copies that read it | `app/Jobs/SendPostCallWhatsAppJob.php:70-72` resolves `voiceCampaign.post_call_message ?? voiceInstance.post_call_message ?? '<default>'` into a local variable that is never referenced again; `app/Jobs/SendInboundLeadWhatsAppJob.php:89-90` does the same. What actually reaches the customer is the `APPROVED` Meta template, whose body the operator edits elsewhere. Almost certainly a leftover from the pre-Meta free-text era, but the surface is still exposed: an operator who edits the post-call message sees no effect and receives no warning. `SendUraTemplateJob` no longer carries the dead local, and its unused private `interpolateVariables()` (`app/Jobs/SendUraTemplateJob.php:233-241`) is the matching residue in that copy. | None — the configured text simply never appears anywhere. | — (new; low severity, recorded so it is not mistaken for a live feature) |
| P5-F07 | **No phone validation before the provider call** | A malformed number reaching any of the three jobs | Normalisation is `ltrim($phone, '+')` and nothing else (`app/Jobs/SendPostCallWhatsAppJob.php:52`, `app/Jobs/SendInboundLeadWhatsAppJob.php:58`, `app/Jobs/SendUraTemplateJob.php:104`). `PhoneNumberValidator::normalize()` — which Path 4 runs precisely to avoid burning Meta reputation on errors 131026/131027 (`app/Jobs/SendCampaignMessageJob.php:354-377`) — is not used here. A malformed IVR-supplied number is POSTed to Meta, fails, and consumes all three retries. | The provider exception, then the log-only `failed()`. Nothing marks the number as invalid, so the same number retries on the next call. | — (new) |
| P5-F08 | **`voice_campaign_calls` is never updated by the handoff** | Every execution of `SendPostCallWhatsAppJob` | The call row is loaded (`app/Jobs/SendPostCallWhatsAppJob.php:42`) and never written. Its `status` reflects the *call's* outcome, set upstream, so nothing on the row distinguishes "handed off to WhatsApp successfully" from "handoff dropped because the template was unapproved" from "handoff never attempted". Combined with P5-F01, the entity that a production trace would most naturally be correlated by (`<call_id>`) carries no handoff state at all. | None. | — (new) |

---

## 5. Complementary-Message Collision Scenarios

All eight D-31 collision points, restated for this path. "Complementary message" here means a
genuine inbound customer message arriving while an IVR/URA handoff template is being prepared or
sent to that same recipient. This path composes nothing, so several points are structurally
inapplicable and are marked as such rather than omitted.

| # | Collision point | Applicable? | Current outcome |
|---|---|---|---|
| 1 | **Arrival during collection** | **Not applicable** | No collection window exists: this path is not responding to a customer message. An inbound arriving at this moment is handled entirely by Path 1, which will create or reuse the same `Lead` — note both paths race on `Lead::firstOrCreate` for the same phone, and this path's `Cache::lock("lead_create_{<tenant_id>}_{<phone>}")` (`app/Jobs/SendPostCallWhatsAppJob.php:53`) is not a lock Path 1 takes, so the two serialise only by the database's unique index. |
| 2 | **Arrival during model work** | **Not applicable — no model call on this path** | No agent is constructed and `AgentService` is never called. There is no reasoning interval for a message to arrive during. |
| 3 | **Arrival during internal work** | **Not applicable — no model call on this path** | The only internal work is relationship loading, a lead lock and template resolution, all deterministic. No tool is invoked and no interpretation is produced that a newer message could invalidate. |
| 4 | **Arrival during external action** | **Applicable — and the least covered instance in the inventory** | The external action is the direct template POST. **Repetition is not covered** (P5-F02): the post-call copy re-sends on any failure, the URA copies drop on any failure, and neither can tell which happened. **Relevance is not covered either**: nothing reads the lead's conversation state, so a handoff template can land in the middle of a live agent conversation started seconds earlier by Path 1. Both halves of D-21/D-22 are missing, where Paths 1 and 4 satisfy at least the repetition half. |
| 5 | **Arrival during response queueing** | **Not applicable** | There is no queueing step. The POST is issued inline in the job; there is no interval between "decide to send" and "send" for a message to arrive in, and correspondingly no cancellation point. |
| 6 | **Arrival during partial send** | **Not applicable** | A template is one message with one POST; there are no parts. D-23 has no instance here, and unlike Path 4 there is not even a bulk analogue — a single handoff cannot be partially cancelled. |
| 7 | **Arrival during retry** | **Applicable** | `tries = 3` with `backoff() = [10, 30, 60]` on all three copies. A retry re-runs `handle()` from the top, so the instance gate, the lead lock and the template gate **are** re-evaluated — but the claim gate then decides everything, and it decides differently per copy (P5-F02). Nothing re-reads conversation state. A customer who replied to the IVR call by WhatsApp in the ninety seconds a retry cycle spans receives the unsolicited handoff template anyway, and on the post-call copy possibly twice. |
| 8 | **Arrival during crash recovery** | **Applicable** | No deadlock is possible: the lead lock expires after 8 seconds (`app/Jobs/SendPostCallWhatsAppJob.php:53`) and the send claim after 10 minutes (`:85`). That is the accidental upside. The downside is total: after retries are exhausted, `failed()` writes one log line and nothing else (P5-F04), so there is no record of whether the crashed execution had already POSTed, whether the customer received the template, or which tenant was affected. A crashed handoff is, from the evidence tables, indistinguishable from one that never started. |

---

## 6. Latest-State Autonomy Comparison

The ten CONTEXT.md decisions against current behaviour on this path. Several concern a
conversational response this path never produces; the verdict is still stated with the vocabulary
`implemented` / `partial` / `absent`, and the justification explains why.

| Decision | Verdict | Justification |
|---|---|---|
| **D-06** — a newer relevant message makes any unsent response obsolete immediately | `absent` | No code on this path reads conversation state at any point. The gates that exist — instance present, template `APPROVED`, claim free — all answer "may this send proceed", never "is this send still apt". A customer already mid-conversation with the agent still receives the handoff template. |
| **D-07** — the obsolete execution stops at the next safe point and never regains permission to answer | `absent` | There is no obsolescence concept and therefore nothing to stop. Worse than Paths 1 and 3 in one specific way: those at least have a queue hop between deciding and sending, giving a future implementation a place to put the check. Here the POST is inline, so a "next safe point" would have to be introduced before it. |
| **D-08** — re-evaluate from the latest complete state; only the current execution answers | `absent` | Nothing is re-evaluated. The claim key establishes at-most-one-sender within a 10-minute window (with the caveats of P5-F05) but says nothing about which conversation state justified the send. |
| **D-15** — one current execution authority per conversation, tied to the exact current state | `absent` | The cache claim is the nearest artefact and it is not an authority: it is keyed on a call id or a phone, not on conversation state; it is not renewable; and its expiry does not resolve the effect into any state — it simply permits the next attempt. Compare Path 4's attempt token, which at least resolves to `in_doubt` on expiry. |
| **D-16** — authority is temporary and renewable; a new relevant message revokes it, a crash expires it | `absent` | The claim is temporary (10 min) but not renewable, and its expiry is the *problem*, not the mechanism: on the URA copies a crash means the claim outlives every retry and the message is lost (P5-F02). Nothing is revoked by a new message, because nothing observes messages. |
| **D-17** — authority checked throughout the cycle **and again at the real send boundary** | `absent` | The claim is taken immediately before the POST (`app/Jobs/SendPostCallWhatsAppJob.php:83-91`), which is the right *placement*, but `Cache::add` answers "has anyone else claimed this key", not "am I entitled to send now". And unlike Path 4's `claimProviderAttempt()`, losing the claim writes nothing durable — the caller simply returns. |
| **D-18** — an outdated request must not return an old answer as if current | `absent` | D-18 is written for the direct API path. Its principle — supersession made explicit in the trace — has no implementation here, and on this path there is barely a trace to make it explicit in (§ 3). |
| **D-23** — cancel every not-yet-sent part as soon as the response becomes obsolete | `absent` | There are no parts and no cancellation path of any kind. Unlike Path 4, there is not even a bulk pause that parks pending work: once the job is on the `messages` queue, nothing can stop it short of the template gate failing. |
| **D-24** — an already-sent part stays canonical history; the next execution sees it and continues naturally | `partial` | The preservation half works and is exactly why `TemplateTimelineRecorder` exists: its class docblock (`app/Services/WhatsApp/TemplateTimelineRecorder.php:13-26`) records that a URA-opened conversation previously showed the customer's reply with nothing above it. The mirror is idempotent on `provider_message_id` (`:52-54`) and never throws (`:83-90`). The gap: because it never throws, a mirror failure silently produces a conversation with a reply and no visible trigger, and nothing marks that case — the same shape as Path 4's P4-F04 caveat, with less evidence to detect it. |
| **D-25** — serial processing alone is not evidence against stale or out-of-order replies | `absent` | This path has no serial processing to over-trust: three different jobs on the shared `messages` queue can target the same lead concurrently with Path 1's inbound processing, and only Path 1 holds `WithoutOverlapping`. D-25 binds Phase 62 here in its strongest form — the cache claim must not be counted toward it, since it proves neither ownership nor currency at the send boundary. |

---

## 7. Evidence Available Today

Per-path verdicts. These differ from the repository-wide table in `61-RESEARCH.md` § Evidence Field
Mapping, which is the starting reference, not the answer for this path. **This path has the weakest
evidence of the six**, and it is the only path where the strongest field on every other path —
external-action outcome — is a `GAP`.

| # | Evidence field | Verdict | What exists on Path 5 |
|---|---|---|---|
| 1 | Collection window | `GAP` | No collection window exists (§ 5 #1), so nothing is recorded. |
| 2 | Execution start | `PARTIAL` | Split by copy. `SendInboundLeadWhatsAppJob` and `SendUraTemplateJob` record `ura_inbound_received` (`:77-87`, `:123-134`) with a real `<interaction_id>` and `lead_id`, which is a genuine start marker. `SendPostCallWhatsAppJob` records nothing at all — its start exists only as an `ivr.*` log line. No `ai_runs.started_at` on any copy, because there is no run. Verdict is `PARTIAL` for the path because it is `EXISTS`-shaped on two copies and absent on the third. |
| 3 | Supersession | `GAP` | No `execution_superseded` event type exists in the codebase. |
| 4 | Stop point | `GAP` | An execution never stops for relevance. The early returns (no instance, template unavailable, claim taken) record why a send never proceeded, which is a different fact — and on the post-call copy they record it only to the log. A crashed execution records nothing about how far it got (P5-F04). |
| 5 | Restart count | `GAP` | Job `attempts()` counts retries of the same input, not restarts from newer state, and it is not persisted anywhere. On the URA copies the claim actively hides retries: attempts 2 and 3 return at the claim gate having done nothing, and leave no trace that they ran. |
| 6 | Preserved result references | `GAP` | No preservation or reuse concept. |
| 7 | External-action outcome | `GAP` | **Differs from every other path in the inventory, where this field is `EXISTS`.** There is no durable row for the send (P5-F03): no status, no `provider_attempted_at`, no `in_doubt`. The provider message id is returned by `sendTemplateViaInstance()` and passed to the timeline recorder, then discarded — it survives only inside `conversation_timeline_messages.provider_message_id`, which is a display artefact, not a delivery ledger, and which is absent entirely if the mirror failed. Confirmed, proven-not-performed and uncertain are **not distinguished at all** (P5-F02). |
| 8 | Obsolete response blocked | `GAP` | Nothing blocks a send as obsolete, so there is nothing to record. |
| 9 | Execution that sent | `GAP` | **Differs from Paths 1, 3 and 4, where this field is `PARTIAL`.** On the post-call copy there is no execution identifier at all — no `<interaction_id>` is minted, so the question is not merely unadjudicated, it is unanswerable. On the two URA copies an `<interaction_id>` exists on the `outbound_sent` event and on the timeline row, which makes the field `PARTIAL` for those copies; the path-level verdict is `GAP` because the representative copy has nothing. |
| 10 | Elapsed time | `GAP` | No `ai_runs.duration_ms` (no run) and no start/end timestamps on any row this path writes. The interval between the IVR outcome and the template reaching the customer is not measurable from the evidence. |
| 11 | Cost | `GAP` | No LLM spend exists, so `estimated_cost_usd` is correctly absent — but the Meta conversation cost a template send initiates is not recorded either, exactly as on Path 4. A conversation opened by an IVR handoff has neither half of its cost captured. |

---

## 8. Characterization Test Reference

**File:** `tests/Feature/IvrHandoffCharacterizationTest.php`

**Command:**

```
php artisan test --compact --filter=IvrHandoffCharacterization
```

The test is a Kent-Beck characterization oracle: it asserts the **current, undesirable** behaviour
of unmodified production code as a receipt for Phase 62, not a specification. It pins four facts —
the happy-path contract (the lead is created or reused, `WhatsAppService` is called exactly once,
the only durable artefact is one `conversation_timeline_messages` row carrying a `NULL`
`interaction_id`); **[P5-F01](#p5-f01) and [P5-F03](#p5-f03), that the representative job writes no
`agent_interaction_events` row, no `ai_runs` row and no `whatsapp_outbox_messages` row at all**;
**[P5-F02](#p5-f02)'s duplicate-send exposure, by asserting that a thrown send releases the claim
and the retry genuinely reaches the provider a second time**; and **[P5-F02](#p5-f02)'s inversion,
by asserting that `SendInboundLeadWhatsAppJob` keeps its claim on the identical failure so its
retry sends nothing — while that same copy does write the interaction events the post-call copy
lacks**.

That last case is the one to read first. It is the measured proof that the three copies are **not**
one mechanism: the same exception produces a duplicate on one copy and a silent drop on another, and
the same "evidence gap" is present on one and absent on another. D-01's rule — analyse a mechanism
once only when it is truly shared internally — is not a stylistic preference on this path; a
single-mechanism characterization would have recorded two false statements.

When Phase 62 gives this path a durable send record and an ambiguity state, this file is **expected
to fail** — starting with the `WhatsappOutboxMessage::doesntExist()` assertion and the
claim-released assertion. It must then be rewritten deliberately as part of that change, never
quietly adjusted to describe the new behaviour, which would erase the before/after this phase exists
to create.

**Fixture-accuracy note.** `WhatsAppService` is mocked at the container seam, the idiom already
established in `tests/Feature/SendPostCallWhatsAppJobTest.php`, so the real jobs, the real
distributed lead lock, the real template gate, the real cache claim and the real
`TemplateTimelineRecorder` all execute — only the provider call is replaced. **No real Twilio call
completion is required**, which matters because `61-VALIDATION.md` lists this path's golden trace
under "Manual-Only Verifications" on the assumption that a completed call could not be faked. The
`VoiceCampaignCall` row is the job's only input, and it is a plain fixture. The remaining
production-only facts for this path are real traffic volume and real provider latency, not
reachability.

**Fixture-accuracy caveat.** A provider timeout that Meta nonetheless accepted cannot be reproduced
in a test — the point of P5-F02 is precisely that this job cannot tell the two apart. What the
fixture pins is the *decision* the code makes when it cannot tell: release the claim and re-send
(post-call), or keep it and drop (URA). The customer-visible duplicate is the consequence of the
first decision, not something the test observes directly.
