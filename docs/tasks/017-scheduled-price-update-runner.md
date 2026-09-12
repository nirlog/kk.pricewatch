# Task 017 — Scheduled price update runner, Bitrix Agent and CLI/cron adapter

## Goal

Add the first safe automatic execution path for price monitoring while keeping the already verified `PriceUpdateService` as the only owner of collector execution and collection-state persistence.

The target dependency direction is:

```text
Bitrix Agent                     CLI / system cron
     |                                  |
     +---------------+------------------+
                     |
                     v
          ScheduledPriceUpdateRunner
                     |
          select active link IDs
          in deterministic chunks
                     |
                     v
             PriceUpdateService
                     |
          existing grouping/factory/
          collector/persistence rules
```

After this task the same scheduled runner must be callable from:

1. a Bitrix Agent adapter;
2. a module-owned CLI command suitable for system cron;
3. tests or future orchestration code without either adapter.

This task must not duplicate collector logic, competitor branching, stale-price rules or ORM persistence already owned by `PriceUpdateService`.

Target module version: `0.8.0`.

## Required context

Before implementation read:

- `AGENTS.md`
- `.agents/skills/implement-task/SKILL.md`
- `.agents/skills/code-review/SKILL.md`
- `docs/architecture/0001-collector-boundary.md`
- `docs/architecture/0002-competitor-entity.md`
- `docs/architecture/0003-product-competitor-link.md`
- `docs/tasks/014-price-update-service-mock-orchestration.md`
- `docs/tasks/015-price-update-service-regression-tests-ci-repair.md`
- `docs/tasks/016-manual-price-check-admin.md`
- `lib/Model/ProductCompetitorTable.php`
- `lib/Model/CompetitorTable.php`
- `lib/Service/PriceUpdateService.php`
- `lib/Service/PriceUpdateServiceFactory.php`
- `lib/Service/PriceUpdateBatchResult.php`
- `lib/Service/PriceUpdateOutcome.php`
- current installer/uninstaller and module autoload code
- current `install/version.php`

Use `$implement-task`.

Before using a Bitrix Agent API, DB-lock API or CLI bootstrap API, verify the exact API against the supported real Bitrix runtime/source. Do not infer API signatures from names.

Relevant Bitrix behavior to preserve:

- a registered agent must return its own invocation string or Bitrix removes it;
- use a non-periodic agent (`IS_PERIOD = N`) so missed intervals do not create a catch-up burst;
- CLI code using Bitrix API must bootstrap `prolog_before.php` with a valid `DOCUMENT_ROOT` and no normal page rendering.

## Architectural decisions

### 1. ScheduledPriceUpdateRunner is an application service above PriceUpdateService

Add a narrow service conceptually equivalent to:

```php
final class ScheduledPriceUpdateRunner
{
    public function run(int $batchSize = 100): ScheduledRunResult;
}
```

Exact naming may vary only for a concrete repository reason.

The runner owns only:

- acquiring/releasing the scheduled-run lock;
- taking a stable upper ID boundary for the run;
- selecting active `ProductCompetitorTable` link IDs in deterministic chunks;
- calling the existing `PriceUpdateService::updateLinks()` once per chunk;
- aggregating transient run counters;
- isolating an unexpected failure in one selected chunk where safe.

The runner must not:

- instantiate any collector directly;
- inspect `COLLECTOR_TYPE`, `COLLECTOR_HANDLER` or `COLLECTOR_OPTIONS`;
- group rows by competitor;
- normalize competitor URLs;
- update `CURRENT_PRICE`, `CURRENCY`, `STATUS`, `ERROR_*`, `LAST_CHECK_AT` or `LAST_SUCCESS_AT` directly;
- implement stale-price behavior;
- contain RoyalPC/KometaPC/DNS/site-specific branches.

Those rules remain inside the existing orchestration/collector boundary.

### 2. Selection is by monitored-link ID, not product ID

Scheduled selection works on `ProductCompetitorTable::ID`.

Only rows with:

```text
ACTIVE = Y
```

are selected by the runner.

Do not pre-filter competitors by `CompetitorTable::ACTIVE` in the runner. An active link whose competitor became inactive is still allowed to reach `PriceUpdateService`, which already owns and returns `INACTIVE_COMPETITOR` without mutating collection state.

Do not join collector configuration into the runner query.

### 3. Stable snapshot horizon

At the beginning of one acquired run, capture the maximum currently existing active monitored-link ID, conceptually:

```text
runMaxId = MAX(ID) for ACTIVE=Y
```

If no active link exists, return an empty successful run without invoking `PriceUpdateService`.

Every chunk in that run must satisfy:

```text
ACTIVE = Y
ID > lastSelectedId
ID <= runMaxId
ORDER BY ID ASC
LIMIT batchSize
```

Use keyset pagination, not OFFSET pagination.

Why this is required:

- rows inserted after the run starts must wait until the next run;
- concurrent inserts cannot move the horizon and make one run unbounded;
- deletions or ACTIVE changes during the run must not cause OFFSET skipping;
- `lastSelectedId` advances from the selected chunk IDs regardless of collector outcome.

If a selected row is deleted or deactivated between selection and `PriceUpdateService`, the existing service semantics (`NOT_FOUND` / `INACTIVE_LINK`) remain authoritative.

### 4. Batch size

Default batch size: `100` monitored-link IDs.

Requirements:

- positive integer only;
- enforce a conservative upper bound (recommended `1000`) at the public adapter boundary;
- one chunk means one `PriceUpdateService::updateLinks($ids)` call;
- never call `updateLinks([$id])` in a loop merely to implement chunking;
- no whole-run DB transaction;
- no transaction wrapping collector/network work.

The runner must work correctly with a batch size smaller than the number of active links.

This task does not add a module settings UI. The default can be a constant/value object and the CLI may accept an explicit batch-size option.

### 5. One run-wide lock

Agent and CLI may be configured at the same time or one scheduled invocation may overlap a slow previous invocation. Prevent concurrent scheduled runs.

Introduce a small lock boundary, for example:

```php
interface ScheduledRunLockInterface
{
    public function acquire(): bool;
    public function release(): void;
}
```

with a default Bitrix DB-backed implementation using the supported connection-level named lock API, conceptually:

```text
Bitrix\Main\Application::getConnection()->lock(name, 0)
Bitrix\Main\Application::getConnection()->unlock(name)
```

Verify the exact runtime API before implementation.

Use one stable module-specific lock name, for example:

```text
kk.pricewatch.scheduled_price_update
```

Rules:

- lock acquisition is non-blocking (`timeout = 0`) where supported;
- if another scheduled runner already owns the lock, do not select links and do not call `PriceUpdateService`;
- return a transient `locked/skipped` run result rather than treating this as a collector failure;
- release the lock in `finally` after successful acquisition;
- never call `unlock()` when this process did not acquire the lock;
- no DB table is added solely for this lock;
- if the supported DB/runtime cannot provide the required named-lock semantics, fail closed for scheduled execution rather than silently allowing overlap.

Manual admin checks from Task 016 are not required to take this run-wide lock. The lock protects automatic runner-vs-runner overlap only.

### 6. Unexpected batch failure isolation

`PriceUpdateService` already converts expected collector/configuration/item failures into outcomes. Those are not runner exceptions.

If `updateLinks($chunkIds)` unexpectedly throws for one selected chunk:

- do not expose raw exception text through Agent or CLI output;
- record one generic transient batch failure in `ScheduledRunResult`;
- advance the keyset cursor past that selected chunk;
- continue with the next chunk where the database/runner remains usable;
- do not synthesize persistent `COLLECTOR_ERROR` rows in the runner;
- do not retry the same chunk in this task.

If selection itself or another runner-global dependency fails so the next chunk cannot be selected safely, abort the run with a generic global failure state and release the lock.

No retry/backoff/queue is introduced in Task 017.

## ScheduledRunResult

Add a small immutable result/DTO sufficient for Agent/CLI/tests without storing full run history.

At minimum expose counters/state conceptually equivalent to:

```text
locked
selected
batches
requested
success
errors
skipped
persistence_failures
batch_failures
global_failure
```

Rules:

- `selected` is the number of IDs selected by the runner;
- `batches` is the number of chunks submitted to `PriceUpdateService`;
- `requested/success/errors/skipped/persistence_failures` aggregate the returned `PriceUpdateBatchResult` values;
- a thrown batch increments `batch_failures` but must not invent per-link service outcomes;
- lock contention sets `locked=true` and leaves selection/service counters at zero;
- do not keep every per-link outcome for an arbitrarily large scheduled run unless there is a strong bounded-memory reason; aggregate counters are sufficient for this milestone.

A small list of generic batch-failure metadata such as first/last selected ID is acceptable if bounded and contains no raw exception/secret data.

No ORM history/audit table is added in this task.

## Factory/composition

Provide a default composition boundary conceptually similar to:

```php
ScheduledPriceUpdateRunnerFactory::createDefault()
```

It should wire:

- existing `PriceUpdateServiceFactory::createDefault()`;
- `ProductCompetitorTable` selection dependency or a narrow repository abstraction if justified;
- default scheduled-run lock.

Keep constructor injection available enough for deterministic unit tests.

Do not make Agent or CLI construct collectors or reproduce service wiring.

## Bitrix Agent adapter

Add one thin module-owned agent adapter, conceptually:

```php
namespace KK\PriceWatch\Agent;

final class PriceUpdateAgent
{
    public static function run(): string;
}
```

Behavior:

1. create the default scheduled runner;
2. call it once with the default batch size;
3. do not echo HTML/output;
4. do not query ORM directly;
5. do not branch on collector type;
6. always return the exact agent invocation string required for the next run, including after an unexpected adapter-boundary throwable.

Use a stable invocation string, for example:

```text
\\KK\\PriceWatch\\Agent\\PriceUpdateAgent::run();
```

Verify the exact string form against real Bitrix Agent execution before finalizing.

Expected collection errors (`PRICE_NOT_FOUND`, `COLLECTOR_ERROR`, `INACTIVE_COMPETITOR`, persistence failure outcomes, etc.) do not unregister the agent.

### Agent registration

Install/update must ensure at most one matching module agent exists.

Safe rollout requirement: register the agent **inactive by default** in Task 017.

Recommended initial registration:

```text
MODULE_ID = kk.pricewatch
ACTIVE = N
IS_PERIOD = N
AGENT_INTERVAL = 3600
```

Rationale: the current module may still contain Mock competitors and unsupported `external` competitors. Installing/upgrading the module must not unexpectedly start automatic writes.

Requirements:

- fresh install creates one inactive agent if absent;
- repeated install/update does not create duplicates;
- update must not silently reactivate an agent the administrator disabled;
- uninstall removes only this exact module-owned agent;
- do not use broad agent deletion if it could remove future unrelated `kk.pricewatch` agents;
- normal uninstall still preserves ORM monitoring data as established in prior tasks.

Do not add an admin scheduler-settings UI in this task.

## CLI / system cron adapter

Add a module-owned CLI script suitable for direct system-cron execution, conceptually:

```text
bin/pricewatch-run.php
```

The exact filename may vary for repository convention, but it must stay inside the installed module and call the same `ScheduledPriceUpdateRunner` used by the Agent adapter.

### CLI requirements

- refuse execution when `PHP_SAPI !== 'cli'`;
- establish a valid `$_SERVER['DOCUMENT_ROOT']`;
- allow an explicit document-root option if useful, but validate it before including Bitrix;
- bootstrap `bitrix/modules/main/include/prolog_before.php`;
- load `kk.pricewatch` through normal Bitrix module loading;
- define the usual non-page CLI constants such as `NO_KEEP_STATISTIC` / `NOT_CHECK_PERMISSIONS` as appropriate for the supported runtime;
- do not invoke `CAgent::CheckAgents()`; this script runs the price-watch runner directly;
- accept optional `--batch-size=N` with the same validation/bounds as the runner adapter;
- do not accept competitor URL, price, status, collector options or arbitrary link IDs as cron command input;
- print a concise machine-readable or deterministic text summary of `ScheduledRunResult`;
- never print stack traces, DB credentials, collector tokens or raw internal exception messages.

Suggested cron documentation example:

```text
*/15 * * * * /usr/bin/php /home/bitrix/www/local/modules/kk.pricewatch/bin/pricewatch-run.php --batch-size=100
```

Documentation must state that the deployment path/PHP binary are environment-specific.

### CLI exit semantics

Define and document stable process-level exit behavior.

Recommended:

- exit `0`: runner completed normally, including expected item-level collection errors/skips; lock contention may also be treated as a normal no-op and must be stated explicitly;
- non-zero: bootstrap/global runner failure or other operational failure that prevented a normal run;
- item-level `PRICE_NOT_FOUND` alone must not make cron treat the process as crashed.

If `persistence_failures` or `batch_failures` are mapped to a non-zero operational exit, document and test that policy explicitly. Do not base exit status on raw collector error count alone.

## Choosing Agent vs cron

Add concise operator documentation, e.g. `docs/scheduled-price-update.md`.

It must explain:

- both adapters use the same runner;
- the installed Bitrix Agent is inactive by default;
- administrators should normally choose one primary scheduling mechanism;
- system cron invokes the module CLI directly;
- Bitrix Agent can be explicitly activated by an administrator after configuration is ready;
- if both are accidentally active, the shared scheduled-run lock prevents overlapping automatic runs;
- Task 017 does not provide per-competitor intervals, queueing, retries or history.

Do not modify the server's crontab automatically.

## Installer/update/uninstall lifecycle

Update module version to `0.8.0`.

Installer/update responsibilities:

- module files/classes for runner, agent and CLI are installed by the normal module mechanism;
- ensure exactly one inactive Task 017 agent exists on fresh install;
- do not duplicate the agent on reinstall/update;
- preserve existing admin proxies from Task 016;
- preserve existing ORM tables/data;
- no new DB table/schema is required by Task 017.

Uninstall responsibilities:

- remove the exact Task 017 agent;
- module files disappear through normal module uninstall lifecycle;
- do not delete `b_kk_pricewatch_competitor` or `b_kk_pricewatch_product_competitor` during normal uninstall;
- do not alter competitor/link data merely because scheduler support is removed.

## Concurrency and race semantics

The implementation must remain safe when rows change during a run.

Required behavior:

- deleted after selection → existing `PriceUpdateService` returns transient `NOT_FOUND`;
- deactivated after selection → existing `INACTIVE_LINK` skip/no mutation;
- competitor deactivated after selection → existing `INACTIVE_COMPETITOR` skip/no mutation;
- row added with ID above `runMaxId` after start → not processed until next scheduled run;
- one chunk failure → later chunks can still run where safe;
- simultaneous Agent/CLI run → one acquires lock; the other is a no-op locked result.

Do not implement row-level scheduler leases, queue claims or distributed workers in Task 017.

## Security and data-integrity requirements

Required:

- scheduled adapters use persisted ORM link IDs only;
- no caller-supplied URL reaches collector execution;
- no direct persistence of collection fields outside `PriceUpdateService`;
- exact stored competitor URL/query string remains untouched;
- no secret collector options printed by CLI;
- no raw throwable messages printed by Agent/CLI;
- no state-changing HTTP endpoint is introduced;
- no arbitrary PHP callback/class name is accepted from CLI arguments;
- no shell execution is introduced by the module;
- lock release occurs in `finally` after ownership is acquired.

## Out of scope

Do not implement in Task 017:

- HTTP/external Python collector;
- RoyalPC/KometaPC/DNS implementation;
- Selenium/browser/session logic;
- queue table;
- retries/backoff;
- per-link or per-competitor next-run timestamps;
- per-competitor schedule intervals;
- scheduler settings UI;
- notifications;
- price history;
- public price comparison;
- automatic repricing;
- distributed workers;
- manual whole-catalog admin button;
- changes to collector contract v1;
- unrelated refactoring.

## Tests and source guards

Add focused tests without pretending PHPUnit is a complete Bitrix scheduler runtime.

At minimum cover as practical:

### Runner selection / pagination

- no active links → no `PriceUpdateService` call;
- only `ACTIVE=Y` link IDs are selected;
- IDs are ordered ascending;
- batch size is respected;
- more than one chunk results in one service call per chunk;
- no one-link service loop is used for a multi-ID chunk;
- keyset pagination uses `ID > lastSelectedId`, not OFFSET;
- IDs above the initial `runMaxId` are excluded from the current run;
- deletion/deactivation race does not make the runner synthesize persistence.

### Aggregation / isolation

- service batch counters aggregate correctly into `ScheduledRunResult`;
- item-level errors/skips do not abort later chunks;
- an unexpected throw from one `updateLinks()` chunk increments generic `batch_failures` and later chunks continue;
- raw throwable messages are not exposed in public result/output;
- a runner-global selection failure is represented generically and releases the lock.

### Lock

- lock acquired → runner executes;
- lock unavailable → zero selection/service calls and locked result;
- release happens after normal completion;
- release happens after unexpected throwable;
- unlock is not called when acquire failed;
- Agent and CLI default composition use the same lock name/boundary.

### Architecture

- runner delegates collection to existing `PriceUpdateService`;
- runner/Agent/CLI do not instantiate `MockCollector` or other collectors;
- no collector-type branching exists in runner/Agent/CLI;
- runner/Agent/CLI do not directly update operational collection columns;
- no competitor-specific domain/name branches are introduced;
- `composer.lock` remains unchanged unless a dependency change is explicitly justified.

### Agent

- adapter calls the scheduled runner exactly once;
- adapter returns its stable invocation string;
- expected run errors do not remove the agent;
- installer creates at most one matching agent;
- default agent state is inactive;
- default agent uses non-periodic interval semantics;
- uninstall removes the exact module-owned agent;
- update/reinstall does not duplicate/reactivate it unexpectedly.

### CLI

- non-CLI execution is rejected;
- invalid document root fails safely;
- invalid/zero/negative/oversized batch size is rejected or bounded according to the documented policy;
- CLI calls the scheduled runner, not `PriceUpdateService`/collector implementations directly;
- output contains aggregate counters only and no secrets/raw exceptions;
- documented exit code policy is covered.

### Regression

- all Task 014/015 PriceUpdateService tests remain green;
- Task 016 manual admin behavior remains green;
- admin proxy lifecycle remains green;
- existing ORM/schema tests remain green;
- module version is `0.8.0`;
- no new DB schema is introduced.

Run at minimum:

```text
composer validate
PHP syntax checks
vendor/bin/phpunit
```

CI must be green before merge.

## Real-Bitrix smoke test

Add a reproducible smoke checklist for `0.8.0`. At minimum verify on a real supported Bitrix instance:

1. upgrade/install reports module version `0.8.0`;
2. exactly one Task 017 agent exists for module `kk.pricewatch`;
3. the new agent is inactive by default;
4. repeated update/install does not create a duplicate agent;
5. direct `ScheduledPriceUpdateRunner` execution with current Mock links completes and reports correct aggregate counters;
6. run with `batchSize=2` and at least three active links reports two or more chunks as expected;
7. a success link persists new price/currency/timestamps through existing service semantics;
8. `PRICE_NOT_FOUND` preserves stale successful price state;
9. unsupported `external` competitor produces existing generic `COLLECTOR_ERROR` without aborting other chunks;
10. active link + inactive competitor produces `INACTIVE_COMPETITOR` and no timestamp mutation;
11. inactive link is not selected by the scheduled runner;
12. CLI command boots Bitrix successfully and returns the same class of aggregate result as the direct runner;
13. CLI expected item-level errors do not cause a fatal/stack trace;
14. manually invoking/activating the Bitrix Agent executes the same runner and the agent remains registered afterward;
15. hold the scheduled-run named lock in one process/session and verify a second runner invocation performs no collection and reports lock contention;
16. release the lock and verify the next run executes normally;
17. verify scheduled CLI/Agent output does not expose `COLLECTOR_OPTIONS` secrets or raw exception details;
18. Task 016 manual admin check still works after upgrade;
19. normal uninstall removes the Task 017 agent while monitoring tables/data remain;
20. reinstall restores one inactive agent and existing competitor/link data remains available.

Record the Bitrix version, PHP version, DB engine, test date, batch size, relevant link IDs and scheduler adapter used.

## Acceptance criteria

Task 017 is complete when:

1. one `ScheduledPriceUpdateRunner` is the shared automatic execution layer;
2. active monitored links are scanned with deterministic keyset chunking under a stable run horizon;
3. every chunk delegates once to the existing `PriceUpdateService`;
4. Agent and CLI contain no collector/business persistence logic;
5. overlapping automatic runs are prevented by one run-wide lock;
6. expected item errors remain isolated by existing service semantics;
7. an unexpected batch failure does not automatically discard later chunks where continuing is safe;
8. the Bitrix Agent is installed exactly once, inactive by default, and survives executions by returning its invocation string;
9. system cron can call a documented module CLI adapter without using HTTP;
10. no scheduler settings UI, queue, retry system, history table or collector transport is added;
11. module version is `0.8.0`;
12. CI is green;
13. real-Bitrix smoke checklist passes before Task 017 is marked verified.