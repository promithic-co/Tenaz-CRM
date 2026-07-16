# Guardian Verification - agentes INSS/CLT/SIAPE

Date: 2026-06-13
Scope: agent specialization update, CLT/Promosys integration, SIAPE direct Promosys fallback, agent creation/config/list UI.

## Verdict: PASS (gaps resolved 2026-06-12)

The implemented update passed the focused functional gates for this scope. The release-blocking gaps recorded on 2026-06-13 were closed on the `chore/hygiene-deps-test-mem` branch — see "Gap Resolution" below.

## Passed Evidence

- `php artisan test --compact tests\Feature\PromosysServiceTest.php tests\Feature\ConsultarCreditoCltToolTest.php tests\Feature\ConsultarCreditoSiapeToolTest.php tests\Feature\CltAgentTest.php tests\Feature\AgentFactoryNicheTest.php tests\Feature\AgentTemplateCreationTest.php tests\Feature\AgentConfigTest.php tests\Unit\Resources\AgentResourceTest.php`
  - Result: 59 passed, 213 assertions.
- `npm.cmd run types:check`
  - Result: passed.
- `npm.cmd run build`
  - Result: passed; Wayfinder regenerated route/action types.
- `vendor\bin\pint --dirty --format agent`
  - Result: passed.
- `php -l` on the new/changed AI integration files:
  - `app\Ai\Agents\CltAgent.php`
  - `app\Ai\Tools\ConsultarCreditoCltTool.php`
  - `app\Ai\Tools\ConsultarCreditoSiapeTool.php`
  - `app\Services\PromosysService.php`
  - Result: no syntax errors.
- Secret scan over scoped files/config found only env/config references and test fixtures; no real Promosys credentials were hardcoded.

## Blocked Or Failing Gates

- `node C:\Users\SSD\.codex\skills\guardian\scripts\guardian.mjs verify --json --write-report`
  - Timed out at 120s and again at 300s.
- `php artisan test --compact`
  - Exhausted PHP memory at 128MB before completion.
- `php -d memory_limit=-1 artisan test --compact`
  - Still exhausted at 128MB, despite `php -d memory_limit=-1 -r "echo ini_get('memory_limit')"` reporting `-1`.
- `composer validate --strict --no-check-publish`
  - Failed due warning: exact version constraint `predis/predis: 3.4`.
- `composer audit`
  - Failed due 15 advisories across 10 packages, including `laravel/framework`, `guzzlehttp/psr7`, `symfony/*`, and `league/commonmark`.

## Scope Notes

- Current worktree contains unrelated changes outside this update, including documentation deletions, `.gitignore`, `composer.json`, and regenerated Wayfinder files.
- The verification above only supports the agent specialization/Promosys update. It does not approve unrelated worktree changes.

## Gap Resolution (2026-06-12)

1. **Composer CVEs** — RESOLVED. Dependency upgrade (`laravel/framework` v12.53.0 → v12.62.0 et al.) committed; `composer audit` reports no advisories.
2. **Pest memory exhaustion** — RESOLVED. Root cause: no `memory_limit` override (PHP CLI default 128MB). Added `<ini name="memory_limit" value="512M"/>` to `phpunit.xml`; full suite now runs **1431 passed, 2 skipped, 0 failed** (4892 assertions, ~251s).
3. **`composer validate --strict`** — RESOLVED. `predis/predis` exact constraint `"3.4"` → `"^3.4"`; lock resynced; `./composer.json is valid`.
4. **Guardian automation timeout** — deferred (tooling only, non-blocking). Recommend splitting Guardian verification into scoped checks.

---

## Addendum — Template Tenant Isolation (2026-07-14)

Scope: canonical URA tenant identity, explicit WhatsApp-template tenant projection, and database-enforced campaign/list/template tenant consistency.

Verdict: **VERIFIED_WITH_ACCEPTED_RISKS** for the approved tenant-isolation scope.

- Final focused Pest gate: 94 passed, 385 assertions.
- Full Pest gate on SQLite `:memory:`: 1,760 passed, 2 skipped, 6,082 assertions.
- Disposable Docker validation passed on MySQL 8.4 and PostgreSQL 17 for normal apply/rollback, URA shadow swap/reseed, cross-tenant rejection, and all four partial-DDL retry boundaries in both directions.
- Migration-test helpers now fail closed unless the environment is `testing`, the default connection is `sqlite`, and the resolved database is exactly `:memory:`.
- Pint, PHP syntax, database/security review, git-scope review, and skeptical verification passed for remediation-owned files.
- Detailed evidence and residuals are recorded in `.engineering/phases/08-template-tenant-isolation/VERIFICATION.md`.

External findings not approved or modified by this remediation:

- The concurrent untracked migration `2026_07_15_014607_add_language_to_whatsapp_templates_unique_index.php` has a potentially unsafe MySQL rollback when multilingual duplicates exist. It requires a separate approved plan.
- Older migrations block a complete from-zero migration chain on MySQL (reserved `key`) and PostgreSQL (aborted transaction after a caught leads migration error). The two new tenant-isolation migrations were validated directly on both real drivers.
- Aggregate `guardian verify --write-report` timed out in the already-dirty worktree; its orphaned process tree was terminated. Deterministic gates and independent reviews completed.
