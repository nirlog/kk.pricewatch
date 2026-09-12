# Task 015 — PriceUpdateService regression tests & CI repair

## Goal

Repair the red CI left after Task 014 / PR #14 and add focused regression coverage for the new `PriceUpdateService` orchestration contract **without changing its production behavior**.

This is a corrective task for the existing `0.6.0` milestone.

The task is complete only when:

1. the stale admin source test no longer expects module version `0.5.0`;
2. the complete repository CI is green on PHP 8.2;
3. regression guards cover the critical orchestration invariants introduced by Task 014;
4. no collector transport, scheduler, history, admin run button or new product functionality is introduced;
5. module version remains `0.6.0`.

## Required context

Before implementation read:

- `AGENTS.md`
- `.agents/skills/implement-task/SKILL.md`
- `.agents/skills/code-review/SKILL.md`
- `docs/architecture/0001-collector-boundary.md`
- `docs/architecture/0003-product-competitor-link.md`
- `docs/tasks/014-price-update-service-mock-orchestration.md`
- `docs/price-update-service.md`
- `lib/Service/PriceUpdateService.php`
- `lib/Service/CollectorFactoryInterface.php`
- `lib/Service/DefaultCollectorFactory.php`
- `lib/Service/PriceUpdateBatchResult.php`
- `lib/Service/PriceUpdateOutcome.php`
- `lib/Service/PriceUpdateServiceFactory.php`
- `lib/Service/RandomRequestIdGenerator.php`
- `lib/Collector/CollectorRequest.php`
- `lib/Collector/CollectorResponse.php`
- `lib/Collector/CollectorItemResult.php`
- `lib/Model/ProductCompetitorTable.php`
- `lib/Model/CompetitorTable.php`
- `tests/Admin/ProductCompetitorAdminSourceTest.php`
- `tests/Service/DefaultCollectorFactoryTest.php`
- `tests/Service/PriceUpdateValueObjectsTest.php`
- failing GitHub Actions run from PR #14

Use `$implement-task`.

Do not rewrite orchestration merely to make tests aesthetically cleaner. Prefer focused tests/source guards over a broad dependency-injection refactor when CI has no Bitrix runtime.

## Confirmed CI failure

Task 014 intentionally bumped:

```text
0.5.0 -> 0.6.0
```

but `tests/Admin/ProductCompetitorAdminSourceTest.php` still contains a source assertion for:

```php
'VERSION' => '0.5.0'
```

GitHub Actions therefore fails even though the new version is correct.

Update this assertion to `0.6.0`.

Do not remove the version assertion entirely. It remains a useful guard for the current milestone.

## Production code policy

The default expectation is:

```text
no production code changes
```

Files under `lib/Service`, `lib/Collector`, `lib/Model`, `admin`, `install/index.php` and installer/schema code should remain behaviorally unchanged.

A very small production change is allowed only if, while adding tests or performing the real-Bitrix smoke test, a concrete correctness defect in Task 014 is demonstrated. In that case:

- document the defect in the PR;
- keep the fix minimal;
- add a regression test/guard for it;
- do not expand into Task 016 functionality.

Do not refactor static D7 ORM access into a repository abstraction solely for unit-test convenience in this task.

## Regression coverage required

The current Task 014 tests cover the collector factory, Mock configuration, UUID generation and result counts, but not the central orchestration semantics.

Because normal CI does not contain a Bitrix runtime, add focused source-level regression guards for integration behavior that cannot be executed as ordinary PHPUnit without faking Bitrix internals.

A new test such as:

```text
tests/Service/PriceUpdateServiceSourceTest.php
```

is appropriate.

Do not create fake `Bitrix\Main` ORM classes merely to make `PriceUpdateService` execute in CI.

### 1. Explicit finite batch loading

Guard that `PriceUpdateService`:

- accepts explicit link IDs through `updateLinks()`;
- normalizes duplicate IDs;
- rejects empty/non-positive/non-integer IDs;
- loads `ProductCompetitorTable` using the requested ID set (`@ID` or verified equivalent);
- does not contain an unfiltered full-table scan for monitored links.

Do not overfit the test to whitespace or irrelevant formatting.

### 2. Competitor grouping / no N+1

Guard that:

- competitor IDs are collected from the loaded links;
- competitor rows are loaded in one batch query;
- links are grouped by `COMPETITOR_ID`;
- `processGroup()` or equivalent is called once per competitor group rather than once per link;
- no competitor-name/site-specific branch exists in core orchestration.

The source guard should fail if a future change regresses to one collector call per link.

### 3. Exact URL and collector item identity

Guard that collector request items are constructed as:

```text
item.id  = string(ProductCompetitorTable.ID)
item.url = stored ProductCompetitorTable.URL
```

and that `PriceUpdateService` does not use URL normalization helpers such as:

- `trim()` on the URL before request creation;
- `parse_url()` / URL rebuilding;
- `http_build_query()`;
- query-parameter sorting/removal.

Do not prohibit unrelated `trim()` use elsewhere in the file if not applied to the stored URL.

### 4. Collector boundary / factory isolation

Guard that:

- `PriceUpdateService` depends on `CollectorFactoryInterface`;
- it does not instantiate `MockCollector` directly;
- it does not implement HTTP/network access;
- `DefaultCollectorFactory` is the component that currently constructs `MockCollector`;
- unsupported `external` configuration remains unavailable/no-network for this milestone.

### 5. Response correlation

Guard the existing minimum correlation contract:

- response `requestId` must equal request `requestId`;
- successful responses reject item IDs outside the request;
- successful responses require one result for every requested item;
- correlation failure maps the group to `INVALID_RESPONSE`;
- claimed item successes are not applied before correlation passes.

Do not add a generic JSON/HTTP response parser in this task.

### 6. Success persistence semantics

Guard that successful item persistence sets all of:

```text
CURRENT_PRICE
CURRENCY
STATUS = success
ERROR_CODE = null
ERROR_MESSAGE = null
LAST_CHECK_AT
LAST_SUCCESS_AT
```

and that the service does not cast `item.price` to float before handing it to ORM.

The collector/service boundary must continue to carry decimal strings.

### 7. Error / stale-price semantics

Guard that item/global/configuration/correlation failures update:

```text
STATUS = error
ERROR_CODE
ERROR_MESSAGE
LAST_CHECK_AT
```

and do **not** include these fields in the error update:

```text
CURRENT_PRICE
CURRENCY
LAST_SUCCESS_AT
```

This is a critical persistence contract: a temporary collector failure must preserve the last known successful price.

Use source assertions precise enough to distinguish the `persistError()` field map from the success field map.

### 8. Partial-batch isolation

Guard that:

- each link is updated independently through `ProductCompetitorTable::update()`;
- there is no transaction surrounding the whole multi-link/multi-competitor call;
- persistence failure returns `PERSISTENCE_FAILURE` / `PERSISTENCE_ERROR` for that link;
- processing code does not throw/return immediately in a way that discards unrelated item/group outcomes.

Do not introduce a queue or retry mechanism.

### 9. Inactive / missing behavior

Guard the existing machine-readable outcomes:

```text
NOT_FOUND
INACTIVE_LINK
INACTIVE_COMPETITOR
COLLECTOR_ERROR
```

and verify that inactive link / inactive competitor branches produce skipped outcomes before group execution/persistence.

For a missing competitor relation, preserve the Task 014 behavior: persistent `COLLECTOR_ERROR` with safe generic text.

### 10. Safe exception handling

Guard that collector/factory exceptions:

- are caught at the competitor-group boundary;
- persist generic `COLLECTOR_ERROR` rather than raw exception text;
- do not include `$exception->getMessage()` in persisted generic error state;
- do not stop processing other competitor groups.

Explicit structured `CollectorError` messages from a valid `CollectorResponse` may still be persisted as designed.

## Existing pure unit tests

Keep and run the existing tests for:

- exact Mock scenario;
- contains/error Mock scenario;
- strict `MOCK_NO_MATCH`;
- Mock default success;
- malformed Mock options;
- unsupported external collector;
- UUIDv4-style request IDs;
- `PriceUpdateBatchResult` counters.

If useful, strengthen these tests narrowly, but do not duplicate all existing MockCollector tests.

## Versioning

Keep:

```text
0.6.0
```

Do not bump to `0.6.1` or `0.7.0`.

Update the stale test expectation from `0.5.0` to `0.6.0`.

## CI requirements

Run the exact repository verification path:

```text
composer install --no-interaction --prefer-dist
composer validate
PHP syntax check for all non-vendor PHP files
vendor/bin/phpunit
git diff --check
```

Acceptance requires **zero PHPUnit failures**.

Do not claim CI is green based only on local syntax checks. Confirm the GitHub Actions workflow for the PR completes with `conclusion=success` before merge.

`composer.lock` must remain unchanged unless a concrete dependency problem is discovered; adding dependencies is out of scope.

## Real-Bitrix smoke test

After the corrective PR is merged and CI is green, run Task 014 against the real development installation before Task 016.

No admin execution button exists yet, so use a controlled PHP/admin shell or other existing trusted developer execution path to call:

```php
$result = \KK\PriceWatch\Service\PriceUpdateServiceFactory::createDefault()
    ->updateLinks([$linkId1, $linkId2]);
```

Record Bitrix version, PHP version, database engine, date and result.

At minimum verify:

1. one Mock exact-success link stores expected `CURRENT_PRICE`, `CURRENCY`, `STATUS=success`, clears errors and sets both timestamps;
2. exact URL with query parameters matches byte-for-byte;
3. strict unmatched Mock returns `MOCK_NO_MATCH` and preserves a previously successful price/currency/`LAST_SUCCESS_AT`;
4. explicit item error (`PRICE_NOT_FOUND`) preserves stale successful state and advances only `LAST_CHECK_AT`;
5. mixed success + item error for the same competitor applies both independently;
6. two competitors in one service call are processed independently;
7. inactive link is skipped without any timestamp/state mutation;
8. inactive competitor is skipped without any timestamp/state mutation;
9. duplicate input link ID is processed only once and `requestedCount` reflects the normalized batch;
10. missing input link ID produces transient `NOT_FOUND` without creating data;
11. `COLLECTOR_TYPE=external` performs no network access and persists safe `COLLECTOR_ERROR` while preserving stale values;
12. invalid Mock options produce safe `COLLECTOR_ERROR` and do not stop another valid competitor group;
13. existing product monitoring UI shows the persisted state correctly after reload.

If practical on the dev database, also verify a deliberately missing competitor relation or another controlled configuration failure path without damaging real test data.

Any float-induced price corruption, stale-price loss, cross-group abort, exact-URL mismatch, raw exception leakage, or unexpected network access is a release blocker.

## Stale-write concurrency note

Do **not** implement identity snapshot/recheck in Task 015.

Document as a future requirement before introducing a slow external HTTP/browser collector: a collector result should not be allowed to overwrite a link whose URL/competitor identity changed while a long-running collection request was in flight.

This is not a MockCollector blocker and belongs to the external-collector/concurrency milestone.

## Scope

Implement only:

- stale version-test correction (`0.5.0` -> `0.6.0`);
- focused orchestration regression tests/source guards;
- narrow strengthening of existing Task 014 tests where useful;
- test/smoke-test documentation updates if needed;
- a concrete production bug fix only if one is demonstrated during this work.

Do not implement:

- HTTP collector;
- Python service;
- Selenium/browser logic;
- agent/cron;
- queue/retries;
- history;
- manual admin collection button;
- public comparison;
- notifications;
- repricing;
- schema/index changes;
- repository abstraction purely for tests;
- concurrency/stale-write protection;
- unrelated refactoring.

## Acceptance criteria

Task 015 is complete only when:

- module version remains `0.6.0`;
- no test still expects `0.5.0` for the current module version;
- orchestration source guards cover grouping, exact URL, factory boundary, correlation, success fields, stale-price error fields, partial isolation, skip behavior and safe exceptions;
- existing pure unit tests remain green;
- `composer.lock` is unchanged;
- full GitHub Actions CI is green;
- production behavior from Task 014 remains unchanged unless a documented correctness fix was necessary;
- real-Bitrix Task 014 smoke test is ready to run after merge.

## Codex invocation

```text
$implement-task

Implement docs/tasks/015-price-update-service-regression-tests-ci-repair.md.
Do not implement anything outside the task scope.
```

Then review with:

```text
$code-review

Review docs/tasks/015-price-update-service-regression-tests-ci-repair.md against AGENTS.md, ADR-0001, ADR-0003 and Task 014.
Pay particular attention to the previously failing version assertion, full GitHub Actions status, meaningful PriceUpdateService regression coverage, stale-price semantics, response correlation, exact URL preservation, partial-batch isolation, safe exception handling, and scope discipline.
```
