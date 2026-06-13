# SECURITY.md — Human Handoff Milestone (Phases 52 / 53 / 54)

**Generated:** 2026-05-26
**ASVS Level:** 1
**block_on:** open_threats
**Auditor:** gsd-security-auditor

---

## Threat Verification

| Threat ID | Category | Disposition | Status | Evidence |
|-----------|----------|-------------|--------|----------|
| T52-1 | STRIDE/Spoofing | Mitigate | CLOSED | `ServiceTicketLifecycleService::claim()` L78-82: reloads ticket with `lockForUpdate()` inside `DB::transaction`; stale route-bound model is discarded |
| T52-2 | STRIDE/Tampering | Mitigate | CLOSED | `ServiceTicketLifecycleService::claim()` L91-95: throws `ValidationException` when `assigned_user_id !== null` AND cast id `!== $user->id` |
| T52-3 | STRIDE/Information Disclosure | Mitigate | CLOSED | `ConversasController::inboxProps()` L187-192 and `conversationProps()` L316-321: `whereHas('tenants', fn ($q) => $q->where('tenants.id', $tenantId))` scoped to actor's tenantId before building transfer_targets |
| T52-4 | STRIDE/Elevation of Privilege | Mitigate | CLOSED | `ServiceTicketPolicy::authorizeFor()` L27: tenant check first; `ServiceTicketController::index()` L33-39: restricted user query filters to `assigned_user_id`, `lead.assigned_user_id`, or unassigned escalation only |
| T52-5 | STRIDE/Tampering | Mitigate | CLOSED | `FollowUpWindowService::evaluate()` L85: `in_array($lead->operational_stage, Lead::HUMAN_HANDOFF_STAGES, true)` — blocks all three stages (human_pending, human_active, waiting_customer) with reason `human_paused` |
| T53-1 | STRIDE/Denial | Mitigate | CLOSED | `EscalarParaHumanoTool::handle()` L30-33: `ServiceTicket::query()->activeEscalation($this->lead->id)->exists()` check before calling service; returns `already_done` result |
| T53-2 | STRIDE/Tampering | Mitigate | CLOSED | `HumanHandoffTransferService::transferFromAi()` L70-88: `AgentInteractionEventService::record()` called outside `DB::transaction` block; wrapped in `try/catch(\Throwable)` that intentionally swallows — failure cannot roll back ticket/lead |
| T53-3 | STRIDE/Tampering | Mitigate | CLOSED | `ConversationAutomationService::syncAfterAgentTurn()` L119-127: `qualify_then_handoff` branch calls `app(HumanHandoffTransferService::class)->transferFromAi()` — same canonical service path as the tool |
| T54-1 | STRIDE/Information Disclosure | Mitigate | CLOSED | `ConversasController::inboxProps()` L187-192 and `conversationProps()` L316-321: both call `whereHas('tenants', fn ($q) => $q->where('tenants.id', $tenantId))` — tenant-scoped before returning transfer_targets |
| T54-2 | STRIDE/Elevation of Privilege | Mitigate | CLOSED | `ServiceTicketController::index()` L29 `forTenant($tenantId)` + L33-39 restricted user filter (`assigned_user_id = $userId OR NULL OR lead.assigned_user_id = $userId`); AI bucket L93-98 applies same restricted-user filter |
| T54-3 | STRIDE/Tampering | Mitigate | CLOSED | `ConversasController::bulkTransfer()` L470-472: explicit tenant check `(string) $targetUser->tenantId !== $tenantId` → returns 422 error; `ConversationTransferService::transferToUser()` L24-28: also validates `$targetUser->tenantId !== $lead->tenant_id` |
| T54-4 | STRIDE/Spoofing | Mitigate | CLOSED | `ServiceTicketController::claim()` L150-154: `ValidationException` caught, returns `back()->withErrors()` → Inertia redirect + flash; no frontend-only claim resolution path |

---

## Unregistered Threat Flags

The following flags were reported in SUMMARY.md `## Threat Flags` sections that do not map to a new unregistered threat (all map to registered threats above):

| Flag Source | Flag Text | Maps To |
|-------------|-----------|---------|
| 52-SUMMARY | Double-claim race: lockForUpdate() on fresh ticket + lead rows | T52-1, T52-2 |
| 52-SUMMARY | Stale model overwrite: reloading ticket inside transaction | T52-1 |
| 52-SUMMARY | Follow-up fire during handoff: HUMAN_HANDOFF_STAGES guard | T52-5 |
| 52-SUMMARY | Cross-tenant data leak in transfer_targets: whereHas('tenants') | T52-3, T54-1 |
| 53-SUMMARY | AI re-triggering handoff tool after handoff exists: activeEscalation check | T53-1 |
| 53-SUMMARY | PauseService writing human_active before service finalizes | T53-2 (informational — avoided by not calling PauseService) |
| 53-SUMMARY | qualify_then_handoff creating divergent state | T53-3 |
| 53-SUMMARY | Audit event failure rolling back handoff | T53-2 |
| 54-SUMMARY | Cross-tenant user list leak in transfer_targets | T54-1 |
| 54-SUMMARY | Restricted user seeing other-tenant open tickets | T54-2 |
| 54-SUMMARY | Double-claim overwriting frontend assignee | T54-4 |
| 54-SUMMARY | Bulk transfer with invalid target user | T54-3 |

No unregistered flags found — all flags map to registered threat IDs.

---

## Accepted Risks Log

_(none — all threats were mitigated)_

---

## Transfer Documentation

_(none — no threats were classified as transfer)_
