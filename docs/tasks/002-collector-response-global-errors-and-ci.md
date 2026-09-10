# Task 002 — CollectorResponse global errors + CI

## Goal

Strengthen collector contract v1 before persistence and external HTTP integration.

The current contract distinguishes per-item failures, but `CollectorResponse` does not yet model a structured global collector failure. Add that capability, enforce response invariants, reject duplicate response item IDs, and add CI so the committed PHPUnit suite is actually executed on pull requests.

## Scope

Implement only:

1. Structured global error support in `CollectorResponse`.
2. Clear success/failure construction API for `CollectorResponse`.
3. Response invariants for `success`, `error`, and item collection.
4. Duplicate response item ID validation.
5. Tests for the new response behavior.
6. GitHub Actions CI for PHP 8.2, Composer validation, PHP syntax checks, and PHPUnit.
7. Minimal documentation updates required to describe global collector failures.

Do not implement:

- competitors ORM;
- product ↔ competitor links;
- admin UI;
- custom iblock property;
- queue;
- agents/cron;
- real HTTP collector;
- Selenium/Python integration;
- retry policy;
- price history;
- notifications;
- automatic repricing.

## Required context

Before implementation read:

- `AGENTS.md`
- `docs/architecture/0001-collector-boundary.md`
- `docs/tasks/001-foundation-mock-collector.md`
- current collector code and tests

Use `$implement-task`.

## CollectorResponse contract

A successful response is logically equivalent to:

```json
{
  "schema_version": "1.0",
  "request_id": "request-1",
  "success": true,
  "items": [
    {
      "id": "item-1",
      "success": true,
      "price": "99990.00",
      "currency": "RUB"
    }
  ]
}
```

A global failure is logically equivalent to:

```json
{
  "schema_version": "1.0",
  "request_id": "request-1",
  "success": false,
  "items": [],
  "error": {
    "code": "COLLECTOR_TIMEOUT",
    "message": "Collector request timed out"
  }
}
```

## Required invariants

### Successful response

When `success === true`:

- global `error` MUST be `null`;
- `items` MAY contain successful and failed item results;
- mixed per-item success/failure remains valid;
- item IDs MUST be unique within the response.

The global `success` flag describes whether the collector request itself completed and produced a usable batch response. It does **not** mean that every item succeeded.

Therefore this is valid:

```text
global success = true
item A = success
item B = PRICE_NOT_FOUND
item C = success
```

### Failed response

When `success === false`:

- global `error` MUST be present;
- global error MUST use `CollectorError`;
- response item IDs, if any are allowed by the chosen implementation, MUST still be unique.

For contract v1, prefer the simplest rule:

- global failure returns an empty `items` collection.

If a different rule is chosen, document why and add tests proving the behavior is unambiguous.

## Construction API

Prefer named factories rather than requiring callers to manually keep constructor flags consistent.

Target API shape:

```php
CollectorResponse::success(
    string $requestId,
    array $items,
): CollectorResponse;
```

and:

```php
CollectorResponse::failure(
    string $requestId,
    string $code,
    string $message,
): CollectorResponse;
```

The exact internal constructor visibility may be chosen by the implementation, but invalid combinations must not be constructible through the normal public API.

`schema_version` remains collector contract version `1.0`.

## Error codes

Do not create a large error framework in this task.

The response must be able to represent at least the stable global codes already defined in `AGENTS.md`, including:

- `COLLECTOR_ERROR`
- `COLLECTOR_TIMEOUT`
- `INVALID_RESPONSE`

Do not make logic depend on localized message text.

## Duplicate result IDs

`CollectorResponse` must reject duplicate `CollectorItemResult::id` values.

Example invalid response:

```text
items:
- id = product-1
- id = product-1
```

This must fail deterministically during response construction.

Do not implement request-vs-response completeness matching in this task. A future boundary validator for external HTTP responses may compare the response with the original request.

## MockCollector changes

Update `MockCollector` only as required by the new `CollectorResponse` construction API.

Its existing behavior must remain unchanged:

- global response success is `true` when the mock completed normally;
- item-level `PRICE_NOT_FOUND` or `MOCK_NO_MATCH` does not make the entire collector response a global failure;
- mixed batches remain supported;
- no HTTP/network behavior is added.

## Serialization

`CollectorResponse::toArray()` must serialize global failure as:

```php
[
    'schema_version' => '1.0',
    'request_id' => 'request-1',
    'success' => false,
    'items' => [],
    'error' => [
        'code' => 'COLLECTOR_TIMEOUT',
        'message' => 'Collector request timed out',
    ],
]
```

For successful responses, omit `error` or serialize it consistently as `null`. Choose one representation and document/test it. Prefer omitting the field on success to match the existing item-result style unless there is a concrete reason not to.

## Tests

Add tests covering at minimum:

1. successful response factory;
2. successful mixed batch remains globally successful;
3. global failure contains structured `CollectorError`;
4. global failure serializes correctly;
5. global success has no global error;
6. duplicate response item IDs are rejected;
7. failure without an error cannot be created through the public API;
8. success with a global error cannot be created through the public API;
9. existing `MockCollector` tests continue to pass;
10. request ID remains unchanged in both success and failure responses.

Do not weaken the existing Task 001 tests.

## GitHub Actions CI

Add a workflow under `.github/workflows/` that runs on at least:

- `pull_request`
- pushes to `main`

Use PHP 8.2.

The workflow must:

1. check out the repository;
2. set up PHP 8.2;
3. install Composer dependencies;
4. run `composer validate`;
5. run PHP syntax checks against project PHP files, excluding `vendor/`;
6. run PHPUnit.

Prefer reproducible Composer installation. If `composer.lock` is generated as a normal consequence of installing the current dev dependencies, commit it unless there is a documented reason not to.

Do not add unrelated linters or static analyzers in this task.

## Documentation

Update `docs/collectors.md` only as necessary to explain:

- global collector failure versus item failure;
- `CollectorResponse::success()`;
- `CollectorResponse::failure()`;
- expected structured global error.

If ADR-0001 requires a small clarification to stay accurate, update it narrowly. Do not rewrite the ADR.

## Verification

Before completion:

1. run PHPUnit locally in the Codex environment if dependencies can be installed;
2. run PHP syntax checks;
3. run `composer validate`;
4. run `git diff --check`;
5. inspect final diff for scope creep.

If dependency installation is unavailable in the execution environment, state that explicitly, but still create the CI workflow so GitHub can perform the authoritative test run.

## Acceptance criteria

The task is complete when:

- `CollectorResponse` represents structured global failures;
- invalid success/error combinations are prevented;
- duplicate response item IDs are rejected;
- item-level failures still do not change a normal batch into a global failure;
- existing Task 001 behavior remains intact;
- tests cover the new invariants;
- GitHub Actions executes the test suite on pull requests and `main`;
- CI is green before merge;
- no ORM/admin/HTTP collector functionality is added.

## Codex invocation

```text
$implement-task
Implement docs/tasks/002-collector-response-global-errors-and-ci.md.
Do not implement anything outside the task scope.
```

After implementation:

```text
$code-review
Review the implementation of docs/tasks/002-collector-response-global-errors-and-ci.md against AGENTS.md and ADR-0001.
Pay particular attention to global-vs-item failure semantics and CI actually running PHPUnit.
```
