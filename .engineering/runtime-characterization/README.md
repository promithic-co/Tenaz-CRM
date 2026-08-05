# Runtime Characterization Evidence Package — Phase 61 (RUNT-01)

## Purpose

This directory is the Phase 61 (RUNT-01) evidence package for the v2.0 Agent Runtime & Governed
Factory milestone. It is read-only input for Phase 62 (execution contracts, ledger, and gateways)
and Phase 63 (compatibility qualification and the architecture gate) — neither phase should
re-derive facts this package already records; they cite these documents by path instead.

Every observation in this package was gathered with production treated as read-only, per D-32:
nothing recorded here activated v2 runtime behaviour, and no production write was made in order to
produce this evidence. Where a dossier correlates a real production trace, that trace is an
observation of existing behaviour, not an experiment on a customer.

D-29 is binding for every file in this directory, without exception: never record raw conversation
content, CPF, phone numbers, financial data, credentials, or tool payloads here — not even a
redacted-looking excerpt. Record only identifiers, classifications, hashes, lengths, and redacted
summaries: enough to reconstruct *behaviour*, never enough to reconstruct a customer's data.

## Dossier Schema v1

```
schema_version: 1
```

Every per-path dossier under this directory (`path-1-meta-webhook.md` through `path-6-playground.md`)
uses exactly eight top-level sections, in this exact order, with these exact headings. No dossier may
reorder, rename, merge, or omit a section — including the evidence-table enumeration in Section 3 and
the fixed rows required by Sections 5, 6, and 7, which must be restated exhaustively even when the
answer for a given row is "not applicable," "absent," or "no row."

### Front Matter

Every dossier opens with a front-matter block of exactly this shape:

```
Requirement: RUNT-01
Success criteria: <criterion number(s) from 61-VALIDATION.md, e.g. "SC3, SC5">
Schema version: 1
Characterized: <ISO 8601 date, e.g. 2026-08-06>
Characterization test: <Pest --filter value, or "none — see § 8">
```

### Required Sections, In Order

| # | Heading (verbatim) | Must contain |
|---|---|---|
| 1 | `## 1. Entry & Trigger` | The trigger, the authentication/tenant-resolution mechanism, and whether execution is synchronous in-request or queued. |
| 2 | `## 2. Ordered Call Chain` | A numbered call chain where every step carries a `path/to/File.php:line` anchor. |
| 3 | `## 3. Golden Trace` | One concrete successful execution, described as the evidence rows it produces. See § Section 3 detail below. |
| 4 | `## 4. Failure Map` | A fixed-column failure-mode table. See § Section 4 detail below. |
| 5 | `## 5. Complementary-Message Collision Scenarios` | All eight fixed D-31 collision points, each marked applicable/not applicable. See § Section 5 detail below. |
| 6 | `## 6. Latest-State Autonomy Comparison` | The ten fixed latest-state decisions against current behaviour. See § Section 6 detail below. |
| 7 | `## 7. Evidence Available Today` | The eleven fixed D-28 evidence fields, marked per-path. See § Section 7 detail below. |
| 8 | `## 8. Characterization Test Reference` | The Pest file and exact filter command that pins this path, or an explicit no-automated-test statement plus the manual artifact. See § Section 8 detail below. |

#### Section 3 detail — the six evidence tables

Section 3 (Golden Trace) describes one concrete successful execution as the evidence rows it
produces. State explicitly which of these six tables gain a row for this path, joined by
`interaction_id` where available: `agent_interaction_events`, `ai_runs`, `whatsapp_outbox_messages`,
`campaign_messages`, `followup_messages`, `voice_campaign_calls`. Absence is evidence: if a path
writes no row to one of these tables, the dossier must say so explicitly ("no row") rather than
omitting the table from the list.

#### Section 4 detail — Failure Map columns

Section 4 (Failure Map) is a table with exactly these columns, in this order:
`ID | Failure mode | Trigger | Current behaviour | Evidence produced | Labeled finding`. The
`Labeled finding` column carries an audit `F`-number (e.g. `F11`) where the 2026-06-14 agentic audit
covers the failure mode, or `—` where it does not.

#### Section 5 detail — the eight fixed collision points (D-31)

Every dossier's Section 5 restates all eight rows below, even for a path where a given point cannot
occur — mark that row `not applicable` with a one-line reason rather than omitting it. Where
`applicable`, also record the current outcome.

| # | Collision point |
|---|---|
| 1 | Arrival during collection |
| 2 | Arrival during model work |
| 3 | Arrival during internal work |
| 4 | Arrival during external action |
| 5 | Arrival during response queueing |
| 6 | Arrival during partial send |
| 7 | Arrival during retry |
| 8 | Arrival during crash recovery |

#### Section 6 detail — the ten fixed latest-state decisions

Section 6 (Latest-State Autonomy Comparison) restates these ten CONTEXT.md decisions against current
behaviour: D-06, D-07, D-08, D-15, D-16, D-17, D-18, D-23, D-24, and D-25. Each row's verdict is drawn
from exactly this vocabulary — `implemented`, `partial`, or `absent` — plus a one-line justification.
Do not invent a fourth verdict term.

#### Section 7 detail — the eleven fixed evidence fields (D-28)

Every dossier's Section 7 restates all eleven rows below, each marked `EXISTS`, `PARTIAL`, or `GAP` —
**for that path specifically**. The verdict differs per path; do not copy `61-RESEARCH.md`'s
§ Evidence Field Mapping table verbatim into a dossier, even though that table is the correct
starting reference.

| # | Evidence field |
|---|---|
| 1 | Collection window |
| 2 | Execution start |
| 3 | Supersession |
| 4 | Stop point |
| 5 | Restart count |
| 6 | Preserved result references |
| 7 | External-action outcome |
| 8 | Obsolete response blocked |
| 9 | Execution that sent |
| 10 | Elapsed time |
| 11 | Cost |

#### Section 8 detail — characterization test reference

Section 8 names the Pest file that pins this path's behaviour and gives the exact command to run it,
for example `php artisan test --compact --filter=MetaWebhookCharacterization`. Where no automated test
covers the path, state that explicitly and name the manual artifact that substitutes for it — for
example, a redacted production trace correlated by `interaction_id` or `call_id`.

## Index

Every file this package contains, its role, and the plan that produces it:

| File | Role | Plan |
|---|---|---|
| `README.md` | This schema and index — the package root every reader starts from | 61-03 |
| `path-1-meta-webhook.md` | Path 1 dossier — Meta Cloud inbound webhook | 61-04 |
| `path-2-direct-api.md` | Path 2 dossier — direct agent API `POST /api/tenaz`, with compatibility coverage for the legacy `/api/aria` alias | 61-05 |
| `path-3-followup-scheduler.md` | Path 3 dossier — follow-up scheduler | 61-05 |
| `path-4-campaign-dispatch.md` | Path 4 dossier — campaign dispatch and reply bridge | 61-06 |
| `path-5-ivr-ura-handoff.md` | Path 5 dossier — IVR/URA WhatsApp handoff | 61-06 |
| `path-6-playground.md` | Path 6 dossier — manual/test invocation through Playground | 61-06 |
| `production-topology.md` | Production queue-topology observation (SC2), strictly read-only | 61-07 |
| `GAP-PACKAGE.md` | Consolidated gap/evidence package, prose | 61-08 |
| `FINDINGS.json` | Consolidated gap/evidence package, machine-readable | 61-08 |

Six paths are indexed above because that is the full canonical inventory (D-02): Evolution is retired per D-03 and is deliberately not a path in this inventory — it must not be characterized, referenced as a trace target, or reintroduced anywhere in this package.
