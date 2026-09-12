# Task 014 — PriceUpdateService orchestration with MockCollector

## Goal

Implement the first complete price-collection orchestration path in `kk.pricewatch`:

```text
ProductCompetitorTable rows
        |
        | explicit batch of link IDs
        v
PriceUpdateService
        |
        | group by competitor
        v
CollectorInterface
        |
        +-- MockCollector   <-- implemented now
        +-- external        <-- configured but not implemented yet
        |
        v
CollectorResponse
        |
        v
apply collection state atomically per link
        |
        v
ProductCompetitorTable
```

After this task the module must be able to take existing monitored product links, run them through the existing collector contract in batches, and persist success/error state correctly.

This is the first orchestration milestone. It is **not** a scheduling, HTTP, Python, queue or history milestone.

## Required context

Before implementation read:

- `AGENTS.md`
- `.agents/skills/implement-task/SKILL.md`
- `.agents/skills/code-review/SKILL.md`
- `docs/architecture/0001-collector-boundary.md`
- `docs/architecture/0002-competitor-entity.md`
- `docs/architecture/0003-product-competitor-link.md`
- `docs/tasks/001-foundation-mock-collector.md`
- `docs/tasks/002-collector-response-global-errors-and-ci.md`
- `docs/tasks/006-product-competitor-orm.md`
- `docs/tasks/012-product-competitor-admin-integration.md`
- `docs/tasks/013-product-edit-tab-bitrix-runtime-compatibility.md`
- `lib/Collector/CollectorInterface.php`
- `lib/Collector/CollectorRequest.php`
- `lib/Collector/CollectorResponse.php`
- `lib/Collector/CollectorItem.php`
- `lib/Collector/CollectorItemResult.php`
- `lib/Collector/Mock/MockCollector.php`
- `lib/Collector/Mock/MockScenario.php`
- `lib/Collector/Mock/MockDefaultResult.php`
- `lib/Model/CompetitorTable.php`
- `lib/Model/CollectorOptions.php`
- `lib/Model/ProductCompetitorTable.php`
- `lib/Model/CollectionStatus.php`

Use `$implement-task`.

Keep the collector boundary transport-independent. Do not put HTTP, Selenium, competitor-specific branches or scheduling logic into `PriceUpdateService`.

## Architectural decisions

### 1. Service input is an explicit batch of monitored-link IDs

Provide a public orchestration method conceptually equivalent to:

```php
PriceUpdateService::updateLinks(array $linkIds): PriceUpdateBatchResult
```

Exact class/method names may vary only for a concrete repository reason, but the behavior must remain the same.

The IDs are `ProductCompetitorTable::ID` values, not Bitrix product IDs.

Requirements:

- accept a non-empty list of positive integer link IDs;
- normalize duplicate IDs so one link is checked at most once per service call;
- do not scan the entire monitoring table when explicit IDs were supplied;
- load the requested links and required competitor configuration without N+1 queries;
- future agent/cron code must be able to chunk IDs and call this same service unchanged.

Do **not** add agent/cron selection logic in this task.

### 2. Group by competitor

`COLLECTOR_TYPE`, `COLLECTOR_HANDLER` and `COLLECTOR_OPTIONS` belong to `CompetitorTable`.

Therefore all processable requested links must be grouped by `COMPETITOR_ID` before calling a collector.

For each active competitor group:

- create exactly one `CollectorRequest` for that group;
- include every processable link in that group as one `CollectorItem`;
- use the `ProductCompetitorTable::ID` converted to string as collector item `id`;
- pass the stored exact `URL` unchanged as collector item `url`;
- pass decoded competitor `COLLECTOR_OPTIONS` into request `options`;
- execute one collector call for the group.

Do not make one collector request per link when multiple links for the same competitor are present in the batch.

Do not batch links belonging to different competitors into one collector request because their collector configuration may differ.

### 3. PriceUpdateService must depend on the collector contract, not MockCollector

The orchestration service must not instantiate `MockCollector` directly and must not contain code such as:

```php
if ($competitorName === 'DNS') { ... }
```

Introduce a narrow collector resolver/factory boundary, for example:

```php
interface CollectorFactoryInterface
{
    public function create(array $competitor): CollectorInterface;
}
```

with a default implementation that currently supports `CollectorType::MOCK`.

Equivalent typed configuration/value-object designs are acceptable if they stay small and keep the same dependency direction.

`PriceUpdateService` must only know that it receives a `CollectorInterface` for a competitor configuration.

A future HTTP/external collector must be addable at this factory boundary without changing the success/error persistence logic in `PriceUpdateService`.

### 4. External collector type remains non-networking in this task

`CompetitorTable` already permits the generic collector type `external`.

Do not implement an HTTP collector now.

If orchestration is explicitly requested for an active competitor whose collector type is not available in the current build:

- perform no network request;
- treat the group as a collector execution/configuration failure;
- apply generic persistent error code `COLLECTOR_ERROR` to processable links in that group;
- use a safe generic message rather than exposing stack traces, secrets or internal paths;
- preserve stale successful price state as described below.

Do not add competitor-specific exceptions or handlers in core.

## Mock collector configuration from competitor options

The default collector factory must be able to construct the current `MockCollector` from `CompetitorTable::COLLECTOR_OPTIONS`.

Use the existing `CollectorOptions::decode()` validation.

For `COLLECTOR_TYPE = mock`, support this development configuration shape:

```json
{
  "scenarios": [
    {
      "match": {
        "field": "url",
        "type": "exact",
        "value": "https://competitor.example/product/123?a=1&b=2"
      },
      "result": {
        "type": "success",
        "price": "129990.00",
        "currency": "RUB"
      }
    },
    {
      "match": {
        "field": "url",
        "type": "contains",
        "value": "/unavailable/"
      },
      "result": {
        "type": "error",
        "code": "PRICE_NOT_FOUND",
        "message": "Price was not found"
      }
    }
  ],
  "default": {
    "price": "99999.00",
    "currency": "RUB"
  }
}
```

Rules:

- `scenarios` is optional and defaults to an empty list;
- `default` is optional;
- when `default` is absent, MockCollector remains strict and unmatched URLs return `MOCK_NO_MATCH`;
- when `default` is present, construct the existing `MockDefaultResult`;
- invalid mock configuration must fail safely and become a group `COLLECTOR_ERROR`, not fatal the whole service call;
- do not modify the existing `MockCollector` contract merely to make orchestration easier;
- arbitrary additional option keys may remain in request `options`; do not normalize/rewrite stored JSON unnecessarily.

Document this mock options shape for the manual smoke test.

## Request identity

Each competitor group needs a non-empty unique `request_id`.

Use a small internal request ID generator with UUID-style/random collision-resistant IDs. Do not add a Composer dependency just for UUID generation.

The request ID must be generated once per collector call and round-trip through the existing collector response contract.

Do not use link IDs, timestamps alone or predictable sequential values as request IDs.

## Processability rules

For explicitly requested IDs:

### Missing link ID

If an input ID does not exist:

- do not create any database row;
- do not throw away processing of other valid IDs;
- return a transient `skipped`/not-found outcome for that input ID.

### Inactive ProductCompetitor link

If `ProductCompetitorTable::ACTIVE = N`:

- do not call the collector for that link;
- do not modify its collection state or timestamps;
- return a transient skipped outcome.

### Inactive competitor

If the referenced `CompetitorTable::ACTIVE = N`:

- do not call the collector for links in that competitor group;
- do not modify their collection state or timestamps;
- return transient skipped outcomes.

### Missing/corrupt competitor relation

If a requested active link references no existing competitor row, treat it as a configuration failure for that link/group:

- no collector call;
- persist `STATUS = error`;
- persist `ERROR_CODE = COLLECTOR_ERROR`;
- use a safe generic message;
- set `LAST_CHECK_AT`;
- preserve stale successful price/currency/last-success state.

This case should be rare because competitor deletion is already protected, but orchestration must fail safely if persistence is inconsistent.

## Collector request construction

For a processable link row:

```text
CollectorItem.id  = string(ProductCompetitorTable.ID)
CollectorItem.url = ProductCompetitorTable.URL EXACTLY AS STORED
```

Do not:

- trim the URL before sending;
- normalize it;
- reorder query parameters;
- strip fragments;
- decode/re-encode it;
- reconstruct it through a URL builder.

The collector must receive the exact configuration identity that the administrator stored.

## Response correlation safety

Before applying a `CollectorResponse` to database rows, minimally verify correlation with the request.

Required checks:

1. `response.requestId` equals the request ID;
2. for a successful global response, every returned item ID belongs to that request;
3. every requested item ID has exactly one result.

`CollectorResponse` already rejects duplicate response item IDs, so do not duplicate that validation unnecessarily.

If correlation is invalid, do **not** apply any claimed item success from that response. Treat all processable links in that request group as:

```text
STATUS = error
ERROR_CODE = INVALID_RESPONSE
```

with a safe generic message and the normal error/stale-price timestamp semantics.

This is the minimal persistence-safety correlation required by orchestration. Do not build a generic HTTP/JSON boundary validator or response parser in this task.

## Applying successful item results

For an item result with `success = true`, persist in one ORM update for that link:

```text
CURRENT_PRICE   = item.price
CURRENCY        = item.currency
STATUS          = success
ERROR_CODE      = NULL
ERROR_MESSAGE   = NULL
LAST_CHECK_AT   = checked-at timestamp
LAST_SUCCESS_AT = same checked-at timestamp
```

Rules:

- preserve money as a decimal string at the PHP/service boundary;
- do not cast price to `float`;
- rely on the existing physical `DECIMAL(18,2)` field for persistence;
- one item success must still be applied if another item in the same batch has an item-level error.

## Applying item-level errors

For an item result with `success = false`, persist:

```text
STATUS        = error
ERROR_CODE    = item.error.code
ERROR_MESSAGE = item.error.message
LAST_CHECK_AT = checked-at timestamp
```

Do **not** clear or overwrite:

```text
CURRENT_PRICE
CURRENCY
LAST_SUCCESS_AT
```

A temporary collection failure must preserve the last known successful price.

## Applying global collector failures

For a valid correlated global failure response:

```json
{
  "success": false,
  "error": {
    "code": "COLLECTOR_TIMEOUT",
    "message": "..."
  }
}
```

apply that error independently to every processable link in the group:

```text
STATUS        = error
ERROR_CODE    = global error code
ERROR_MESSAGE = global error message
LAST_CHECK_AT = checked-at timestamp
```

Again preserve:

```text
CURRENT_PRICE
CURRENCY
LAST_SUCCESS_AT
```

## Collector/factory exceptions

A collector implementation or factory may throw due to bad configuration or an unexpected runtime failure.

The service boundary must prevent one competitor group from aborting all other groups in the same `updateLinks()` call.

For an exception before a valid response is available:

- mark processable links in that competitor group as `error`;
- use `ERROR_CODE = COLLECTOR_ERROR`;
- use a safe generic human-readable message;
- set `LAST_CHECK_AT`;
- preserve stale successful values;
- continue processing other competitor groups.

Do not persist raw stack traces, exception class names, secrets, tokens, local paths or arbitrary transport exception bodies into `ERROR_MESSAGE`.

## Timestamp semantics

Use one checked-at timestamp for all links belonging to one collector request/result application.

The timestamp should represent the completed attempt, not the time the ORM row was originally loaded.

For success:

```text
LAST_CHECK_AT = checkedAt
LAST_SUCCESS_AT = checkedAt
```

For any collection/global/correlation error:

```text
LAST_CHECK_AT = checkedAt
LAST_SUCCESS_AT unchanged
```

Use the Bitrix datetime type expected by the ORM fields. Do not add new timestamp columns.

## Persistence isolation / partial batches

Do not wrap an entire multi-item or multi-competitor service call in one transaction that causes unrelated successful updates to roll back because one link fails to persist.

Each link state transition must be one coherent ORM update.

If a database update for one item fails:

- do not roll back already persisted unrelated item results;
- report that link as a transient persistence/save failure in the service result;
- continue applying other item results;
- do not falsely report the failed DB update as a collector success.

Do not add persistence history in this task.

## Transient service result

Return a structured in-memory result from `updateLinks()` so admin/manual/agent callers can understand what happened without reading logs.

Introduce a small immutable result DTO, conceptually:

```text
PriceUpdateBatchResult
- requested count
- success count
- error count
- skipped count
- persistence-failure count if needed
- per-link outcomes
```

Each per-link outcome should at minimum identify:

- link ID;
- outcome status (`success`, `error`, `skipped`, or equivalent);
- optional machine-readable code;
- optional safe message.

This result is transient. Do not create a database history table for it.

Do not use localized prose as the only machine-readable result state.

## Default composition entry point

Provide one simple default composition path so later agent/cron code and the real-Bitrix smoke test do not need to know implementation internals.

For example:

```php
$service = PriceUpdateServiceFactory::createDefault();
$result = $service->updateLinks([$linkId1, $linkId2]);
```

An equivalent small factory/composition-root design is acceptable.

Do not use a global service locator or hidden singleton.

## No admin execution button yet

Do not add a `Проверить цену` / `Run collector` button to product or competitor admin UI in this task.

The orchestration layer must first be proven independently.

A manual admin trigger can be a later task after the service contract is stable.

## Existing admin UI behavior

The current product summary/admin pages already display collection state fields.

Do not redesign those pages in this task.

After `PriceUpdateService` updates rows, the existing UI should show the new values after reload automatically because `ProductCompetitorTable` is already the source of truth.

Do not add AJAX polling or frontend JavaScript.

## Security

- MockCollector performs no network access.
- `external` collector configuration must not trigger HTTP in this task.
- preserve exact URLs but treat them as untrusted data.
- do not log or expose future collector secrets.
- exception messages persisted to rows must be safe and generic when the exception is not an explicit validated `CollectorError` response.
- do not implement CAPTCHA/anti-bot bypasses.

## Scope

Implement only:

- `PriceUpdateService` batch orchestration;
- collector factory/resolver boundary;
- MockCollector construction from competitor options;
- request ID generation;
- response correlation needed before persistence;
- success/error/global-failure state application;
- stale-price semantics;
- inactive/missing handling;
- transient batch result DTO;
- default service composition entry point;
- focused tests/source guards;
- orchestration documentation and real-Bitrix smoke-test instructions;
- version bump described below.

Do not implement:

- HTTP collector;
- Python service;
- Selenium/browser execution;
- agent/cron;
- queue/retry table or states;
- automatic scheduler selection;
- manual admin run button;
- price history;
- notifications;
- public comparison UI;
- automatic repricing;
- schema/table/index changes;
- competitor-specific parsing or branches;
- unrelated admin redesign;
- unrelated refactoring.

## Versioning

This is a new functional orchestration milestone.

Bump module version:

```text
0.5.0 -> 0.6.0
```

No database migration is required because the existing ORM fields already support this state.

Do not add Marketplace updater packages in this task unless the repository already has a mandatory updater mechanism that requires them; if such a requirement exists, document it rather than silently expanding scope.

## Automated tests

Add focused tests meaningful without requiring a full Bitrix runtime.

At minimum cover as practical:

### Collector factory / Mock configuration

- mock with exact scenario success;
- mock with contains scenario error;
- strict no-match returns `MOCK_NO_MATCH`;
- optional default result works;
- malformed mock options fail safely;
- `external` does not perform network access and is reported as unavailable/currently unsupported.

### Request construction / batching source guards

Guard that orchestration:

- uses `ProductCompetitorTable::ID` string as collector item ID;
- passes exact stored URL;
- groups by competitor;
- creates one request per competitor group rather than one request per link;
- passes decoded `COLLECTOR_OPTIONS` as request options;
- uses a collision-resistant request ID generator;
- does not instantiate `MockCollector` directly inside `PriceUpdateService`.

### Persistence state semantics

Guard/test the transition behavior:

- success writes price, currency, `success`, clears errors, sets both timestamps;
- item error writes `error`, code/message and `LAST_CHECK_AT` while preserving stale price/currency/`LAST_SUCCESS_AT`;
- global failure applies to all requested links in that group with stale-price preservation;
- invalid response correlation becomes `INVALID_RESPONSE` and does not apply claimed success values;
- collector/factory exception becomes `COLLECTOR_ERROR` with safe message;
- mixed item success/error does not discard the successful item;
- one competitor-group failure does not stop another competitor group;
- inactive links/competitors are skipped without state changes;
- a missing requested ID does not abort other IDs;
- no float conversion is introduced for prices.

If direct ORM behavior cannot be unit-tested without Bitrix runtime, isolate pure mapping/result logic where sensible and use source-level guards only for the Bitrix integration boundary. Do not build a fake Bitrix framework merely to increase coverage.

Run:

- `composer validate`;
- PHP syntax checks;
- PHPUnit;
- `git diff --check`.

Keep `composer.lock` unchanged unless there is an explicit dependency reason, which this task should not require.

## Documentation

Add a concise document describing:

- `PriceUpdateService` entry point;
- mock `COLLECTOR_OPTIONS` shape;
- success/error/stale-price semantics;
- how future agent/cron callers should pass finite batches of link IDs;
- the fact that `external` transport is not implemented yet.

Do not duplicate the full ADRs; document operational usage.

## Manual real-Bitrix smoke test

After merge, run the service directly on the development Bitrix installation before starting scheduling/HTTP work.

Use existing competitors/product links or create dedicated test rows.

At minimum verify:

1. module `0.6.0` loads without runtime errors;
2. configure an active `mock` competitor with an exact success scenario and link it to a product;
3. invoke the default `PriceUpdateService` composition with that link ID;
4. verify database/UI state becomes:
   - expected decimal price;
   - expected currency;
   - `STATUS = success`;
   - errors NULL;
   - `LAST_CHECK_AT` set;
   - `LAST_SUCCESS_AT` set;
5. configure a mock item-error scenario and verify:
   - `STATUS = error`;
   - expected error code/message;
   - `LAST_CHECK_AT` changes;
   - previous price/currency remain;
   - previous `LAST_SUCCESS_AT` remains;
6. test strict mock no-match and verify `MOCK_NO_MATCH` with the same stale-price semantics;
7. submit at least two links for the same competitor in one service call and verify mixed success/error is applied independently;
8. submit links from two competitors and verify each competitor configuration is respected independently;
9. mark a link inactive and verify it is skipped without changing timestamps/state;
10. mark a competitor inactive and verify its active links are skipped without changing timestamps/state;
11. verify a stored exact URL containing query parameters reaches mock exact matching without normalization;
12. configure `COLLECTOR_TYPE = external`, invoke the service and verify no network call is made and the link receives safe `COLLECTOR_ERROR` state;
13. reload the existing product `Мониторинг цен` tab/list and confirm the persisted collection state is visible there.

Record Bitrix version, PHP version, database engine, date and pass/fail result.

## Acceptance criteria

Task 014 is complete only when:

- `PriceUpdateService` processes explicit finite batches of link IDs;
- processable links are grouped by competitor;
- one normal collector contract is used for every group;
- the service is decoupled from the concrete MockCollector implementation through a resolver/factory boundary;
- MockCollector can be configured from competitor options;
- exact link URLs are sent unchanged;
- item successes and errors persist independently;
- global failures persist correctly;
- errors preserve stale price/currency/last-success state;
- response request/item correlation is checked before persistence;
- inactive links/competitors are skipped without state mutation;
- one group failure does not abort unrelated groups;
- no network transport is introduced;
- no agent/cron/queue/history/admin-trigger functionality is introduced;
- module version is `0.6.0`;
- CI is green;
- real-Bitrix smoke test passes.

## Codex invocation

```text
$implement-task

Implement docs/tasks/014-price-update-service-mock-orchestration.md.
Do not implement anything outside the task scope.
```

Then review with:

```text
$code-review

Review docs/tasks/014-price-update-service-mock-orchestration.md against AGENTS.md and ADR-0001/0003.
Pay particular attention to batch grouping, exact URL preservation, collector abstraction, Mock options parsing, request/response correlation, partial-batch semantics, stale-price preservation, decimal-string handling, safe exception handling, ORM state transitions, no-network scope and CI coverage.
```
