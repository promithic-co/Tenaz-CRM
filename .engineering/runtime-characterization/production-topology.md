# Production Topology — Live Observation

Requirement: RUNT-00
Success criteria: SC2
Schema version: 1
Characterized: 2026-08-06
Characterization test: none — no test can settle this; the evidence is a live command transcript

**Observation window:** 2026-08-06, ~18:45–19:00 local (America/Sao_Paulo). Precision is to the
nearest quarter hour: the operator captured wall-clock informally, and the plan requires that be
stated rather than implied.

**Host:** `37.27.35.241` (`promithicserver`). Commands were run by the operator from a root shell on
the VPS, not over `ssh`.

**Resolved container:** `tenaz_tenaz.1.knnrahprvmvvpf7c6o5070sgz` (image `tenaz:latest`). A second
replica, `tenaz_tenaz.2.m0x3h7mnemdf1fltt0bysv7jd`, runs the same image. Swarm task ids change on
every redeploy, so any later session must re-resolve with `docker ps` rather than reuse these.

**Every command was read-only. This phase made no production write of any kind** — no database write,
no job dispatch, no message sent, no configuration change, no service restart (D-32). Steps 6 and 7
are bounded `count()` reads.

**D-29:** no customer identifier, phone number, message content, CPF, credential or key value appears
in this document. Counts, process states, program names, pids, uptimes and timestamps only. The
`TENAZ_AGENT_FAILOVER_*` values transcribed below are non-secret configuration — a boolean, a provider
name and a model name.

---

## Queue Topology

`docker exec tenaz_tenaz.1.knnrahprvmvvpf7c6o5070sgz supervisorctl status`, verbatim:

```
nginx                                                              RUNNING   pid 13, uptime 4:19:03
php-fpm                                                            RUNNING   pid 14, uptime 4:19:03
queue-auto-tags                                                    RUNNING   pid 4373, uptime 0:18:39
queue-campaign-delivery-events:queue-campaign-delivery-events_00   RUNNING   pid 4357, uptime 0:18:42
queue-campaign-delivery-events:queue-campaign-delivery-events_01   RUNNING   pid 4345, uptime 0:18:43
queue-campaigns:queue-campaigns_00                                 RUNNING   pid 4346, uptime 0:18:43
queue-campaigns:queue-campaigns_01                                 RUNNING   pid 4374, uptime 0:18:39
queue-default                                                      RUNNING   pid 4385, uptime 0:18:37
queue-followups                                                    RUNNING   pid 4361, uptime 0:18:40
queue-media                                                        RUNNING   pid 4369, uptime 0:18:39
queue-messages:queue-messages_00                                   RUNNING   pid 4362, uptime 0:18:40
queue-messages:queue-messages_01                                   RUNNING   pid 4347, uptime 0:18:43
queue-outbox                                                       RUNNING   pid 4381, uptime 0:18:38
reverb                                                             RUNNING   pid 28, uptime 4:19:03
scheduler                                                          RUNNING   pid 29, uptime 4:19:03
```

Fifteen entries for twelve declared programs. The difference is entirely accounted for: three programs
declare `numprocs=2`, which supervisor renders as a process group `name:name_00` / `name:name_01`.

### Declared versus running

| Program | Declared in `supervisord.conf` | Running per transcript | Match |
|---|---|---|---|
| `php-fpm` | yes (`:21`) | RUNNING, pid 13 | ✓ |
| `nginx` | yes (`:30`) | RUNNING, pid 14 | ✓ |
| `scheduler` | yes (`:39`, `artisan schedule:work`) | RUNNING, pid 29 | ✓ |
| `queue-campaigns` | yes (`:48`, `numprocs=2`) | RUNNING ×2, pids 4346 / 4374 | ✓ |
| `queue-messages` | yes (`:59`, `numprocs=2`) | RUNNING ×2, pids 4362 / 4347 | ✓ |
| `queue-campaign-delivery-events` | yes (`:70`, `numprocs=2`) | RUNNING ×2, pids 4357 / 4345 | ✓ |
| `queue-followups` | yes (`:81`) | RUNNING, pid 4361 | ✓ |
| `queue-outbox` | yes (`:90`) | RUNNING, pid 4381 | ✓ |
| `queue-media` | yes (`:99`) | RUNNING, pid 4369 | ✓ |
| `queue-default` | yes (`:108`) | RUNNING, pid 4385 | ✓ |
| `queue-auto-tags` | yes (`:117`) | RUNNING, pid 4373 | ✓ |
| `reverb` | yes (`:126`) | RUNNING, pid 28 | ✓ |
| **`horizon`** | **declared nowhere** | **not running** | ✓ (consistent) |

Zero declared-but-dead programs. Zero running-but-undeclared programs.

The queue workers show ~18 minutes of uptime against ~4h19m for `nginx`, `php-fpm`, `reverb` and
`scheduler`. That is the expected signature of `--max-time=3600` on every `queue:work` command:
workers exit on schedule and supervisor restarts them. It is not evidence of crashes.

### Horizon

`ps aux | grep -i horizon | grep -v grep; ls -d vendor/laravel/horizon 2>/dev/null || echo NO_HORIZON_VENDOR`

```
vendor/laravel/horizon
```

The `ps` portion produced **zero lines**. The single line of output is the `ls` result. Therefore:

- **No Horizon process is running** in the container.
- **The vendor package is present** — `laravel/horizon: ^5.45` sits under `require` in
  `composer.json:18`, not `require-dev`, so it ships in the production image by construction.

### Verdict — success criterion 2: **MET**

Production runs **Supervisor-managed `queue:work`, not Horizon.** Eight dedicated `queue:work`
programs (eleven processes, counting the three `numprocs=2` groups) consume the `campaigns`,
`messages`, `campaign-delivery-events`, `followups`, `outbox`, `media`, `default` and `auto-tags`
queues. This is now settled by a live process listing rather than by reading configuration.

All three repo-side claims are **confirmed**:

- `config/horizon.php:220-224` states the `production` block is not in effect and that production runs
  a plain `queue:work` per queue via `docker/supervisord.conf`. Accurate.
- `Dockerfile:80` launches only `supervisord`. Accurate.
- `composer.json:18` has `laravel/horizon` under `require`, so the package is in the image. Accurate —
  and it is precisely this that made the topology question ambiguous from the repo alone.

### Supersession of the STATE.md blocker

`.planning/STATE.md:139` records: *"Horizon not installed in Docker container: `HorizonServiceProvider`
referenced but `laravel/horizon` package missing from container's vendor."*

**That note is half wrong, and this evidence supersedes it.**

- **Wrong half:** "package missing from container's vendor". `ls -d vendor/laravel/horizon` succeeded
  *inside the running container*. The package is installed.
- **Right half, for the wrong reason:** the operative conclusion — Horizon plays no part in production
  queue processing — holds. But it holds because supervisord never starts it, not because it is
  absent.

The distinction matters for Phase 62. A missing package would mean adopting Horizon requires a
dependency change and a rebuild; the actual state means it requires only a supervisord change. Anyone
planning runtime work off the old note would have mis-scoped that.

---

## Swarm Replicas

`docker service ps tenaz_tenaz`, verbatim:

```
ID             NAME                IMAGE          NODE              DESIRED STATE   CURRENT STATE          ERROR     PORTS
knnrahprvmvv   tenaz_tenaz.1       tenaz:latest   promithicserver   Running         Running 4 hours ago
rj4m9ot6vkyl    \_ tenaz_tenaz.1   tenaz:latest   promithicserver   Shutdown        Shutdown 4 hours ago
zr6mrn91a0pv    \_ tenaz_tenaz.1   tenaz:latest   promithicserver   Shutdown        Shutdown 5 hours ago
vp0ftzuqer50    \_ tenaz_tenaz.1   tenaz:latest   promithicserver   Shutdown        Shutdown 6 hours ago
m0x3h7mnemdf   tenaz_tenaz.2       tenaz:latest   promithicserver   Running         Running 4 hours ago
jpmzne4y3znv    \_ tenaz_tenaz.2   tenaz:latest   promithicserver   Shutdown        Shutdown 4 hours ago
c4ab9nja1arf    \_ tenaz_tenaz.2   tenaz:latest   promithicserver   Shutdown        Shutdown 5 hours ago
ziou1r6fdjrx    \_ tenaz_tenaz.2   tenaz:latest   promithicserver   Shutdown        Shutdown 6 hours ago
```

Both replicas healthy, up 4 hours, each with three prior `Shutdown` generations and an empty `ERROR`
column — routine redeploys, not failures. Both sit on the same node (`promithicserver`).

**Implication for reading trace gaps.** Two active replicas means an inbound webhook and a later
request touching the same lead can be served by different containers. No in-process or per-container
state is shared between them: only Postgres, Redis and the queues are common ground. When a trace in
paths 1–6 appears to lose continuity, replica routing is a candidate explanation that must be excluded
before concluding the code dropped it.

Both replicas run the full supervisord program set — confirmed for `scheduler` and `reverb` on replica
2 (`pid 28` / `pid 27`, uptime 4:27:33). Each replica therefore runs its own `schedule:work` loop.
**This is handled, not a gap:** `routes/console.php:19-33` documents exactly this topology, and all
thirteen schedules carry `->onOneServer()`, with the frequent ones adding `->withoutOverlapping()` and
an explicit expiry. The lock is taken in the shared cache store, which is what makes it hold across
containers.

**Open, unresolved:** `reverb` runs inside both app replicas (declared at `docker/supervisord.conf:126`,
so intentional) *and* as a separate Swarm service, `aria_reverb.1.prl2hw9mzu51n03hkewwmuciy`, on an
untagged image id (`2ffa9da008d4`). Which instance browser clients actually connect to is not
determined by this observation, and there is no `config/reverb.php` in the repository to settle it from
code. Recorded as an open question, not as a finding. It does not bear on criterion 2.

---

## Supporting Observations

### Agent failover flag

```
TENAZ_AGENT_FAILOVER_ENABLED=true
TENAZ_AGENT_FAILOVER_PROVIDER=groq
TENAZ_AGENT_FAILOVER_MODEL=llama-3.3-70b-versatile
```

Failover is **live in production**, not dormant. This closes `61-RESEARCH.md` Open Question 1. Phase 62
must treat a provider swap to Groq as a real runtime path when reasoning about model-call evidence,
cost attribution and the gateway contract — a trace whose model differs from the configured primary is
expected behaviour, not an anomaly.

### `failed_jobs` volume

```
7539
```

Two consequences.

The pre-61-01 Laboratory metric matched failures with `payload LIKE '%ProcessLeadFollowUpJob%'` against
this table. With 7 539 rows present, that match had real rows to find — the old metric was not merely
returning zero for want of data.

More importantly, `failed_jobs` carries **no `tenant_id` column**. All 7 539 rows are
tenant-unattributable by construction. This is exactly the gap plan 61-01 closed by repointing
`LaboratoryMetricsService::followupStats()`'s `failed_today` onto the tenant-scoped `FollowupMessage`
model. The production count is the scale evidence for why a global counter was insufficient.

### Path traffic in the last 30 days

| Path | Table | 30-day count |
|---|---|---|
| Path 4 — campaign dispatch | `campaign_messages` | **1540** |
| Path 5 — IVR/URA handoff | `voice_campaign_calls` | **0** |

**Path 4 has real production traffic.** A genuine production golden trace is obtainable for campaign
dispatch whenever Phase 62 or 63 wants one.

**Path 5 has none.** No IVR/URA call has been recorded in thirty days. The CI-driven
`IvrHandoffCharacterizationTest` delivered by plan 61-06 is therefore the **only** evidence that path
will have, and `path-5-ivr-ura-handoff.md` rests on code reading plus that test rather than on observed
production behaviour. **Phase 62 must not plan to observe live IVR traffic** — it is not there to
observe. Any compatibility or shadow qualification for path 5 has to be synthetic.

This also bears on `61-VALIDATION.md`, which listed paths 4 and 5 as manual-only on the assumption that
Meta template approval and Twilio call completion were required. Plan 61-06 established both are
reachable in CI from fixtures. For path 5 that is not merely convenient — it is the only option.

---

## Host Context — not findings against this codebase

Recorded so a later reader does not mistake host inventory for application state.

- `evoapi_evolution_v2.1...` (`evoapicloud/evolution-api:v2.3.7`) is still running on the host, although
  Evolution was removed from this codebase in `dea4650`. It is infrastructure left over from a retired
  integration, not a live code path in this repository. It is **not** a reason to reintroduce any
  `Evolution*` class, page or `WhatsAppProvider` case.
- Unrelated stacks sharing the host: `wa-validator` (three containers), `tenaz_landing`, `home`,
  `postgres_postgres`.
