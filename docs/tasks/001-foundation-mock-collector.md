# Task 001 — Foundation + Mock Collector

## Goal

Create the initial `kk.pricewatch` module foundation and implement a transport-independent collector contract with an in-process `MockCollector`.

## Scope

Implement:

1. Base installable module skeleton for `kk.pricewatch`.
2. Namespace/autoload structure for `KK\PriceWatch`.
3. Collector contract/value objects.
4. `CollectorInterface`.
5. `MockCollector`.
6. `exact` and `contains` URL matching.
7. Strict no-match behavior.
8. Configurable default-success behavior.
9. Structured per-item errors.
10. Tests.
11. Minimal developer documentation for adding future collector implementations.

Do not implement:
- competitors admin UI;
- product/competitor ORM tables;
- custom iblock property;
- agents/cron;
- queue;
- public block;
- real HTTP parsing;
- external HTTP Collector;
- Selenium;
- history;
- notifications;
- automatic repricing.

## Request model

Equivalent to:

```json
{
  "schema_version": "1.0",
  "request_id": "6e51b8a2-...",
  "items": [
    {
      "id": "product-link-1",
      "url": "https://example.test/product/1"
    }
  ],
  "options": {}
}
```

Requirements:
- version required and currently `1.0`;
- non-empty request ID;
- at least one item;
- unique non-empty item IDs;
- non-empty URL;
- preserve URL exactly as supplied;
- options may be empty;
- no real HTTP validation/fetching.

## Response model

Equivalent to:

```json
{
  "schema_version": "1.0",
  "request_id": "6e51b8a2-...",
  "success": true,
  "items": [
    {
      "id": "product-link-1",
      "success": true,
      "price": "99990.00",
      "currency": "RUB"
    }
  ]
}
```

Failed item:

```json
{
  "id": "product-link-2",
  "success": false,
  "error": {
    "code": "PRICE_NOT_FOUND",
    "message": "Price was not found"
  }
}
```

## Mock scenarios

Success example:

```php
[
    'match' => [
        'field' => 'url',
        'type' => 'exact',
        'value' => 'https://example.test/product/1',
    ],
    'result' => [
        'type' => 'success',
        'price' => '99990.00',
        'currency' => 'RUB',
    ],
]
```

Error example:

```php
[
    'match' => [
        'field' => 'url',
        'type' => 'contains',
        'value' => '/not-found',
    ],
    'result' => [
        'type' => 'error',
        'code' => 'PRICE_NOT_FOUND',
        'message' => 'Price was not found',
    ],
]
```

Typed configuration objects are acceptable if cleaner.

## Default behavior

Strict mode: unmatched item returns `MOCK_NO_MATCH`.

Default-success mode: unmatched item returns configured default price/currency.

Automated tests use strict mode by default.

## Batch behavior

- evaluate all items;
- return one result per item;
- preserve request/item IDs;
- allow mixed success/failure;
- one failed item must not discard successful items.

## Money validation

Success price must be a non-negative decimal string.

Reject empty values, arbitrary text, `NaN`, and negative values.

Never convert money to float.

## Errors

At minimum:
- `INVALID_REQUEST`
- `MOCK_NO_MATCH`
- `PRICE_NOT_FOUND`

Invalid whole request/config may throw typed exceptions.

Expected per-item collection failures should be returned as item results.

## Tests

At minimum:

1. exact match success;
2. contains match success;
3. explicit item error;
4. strict no-match;
5. default-success no-match;
6. mixed batch;
7. request ID round-trip;
8. item ID round-trip;
9. query parameters preserved;
10. invalid money rejected;
11. duplicate input item IDs rejected;
12. empty item list rejected.

## Acceptance criteria

- module skeleton loads under normal Bitrix conventions;
- collector domain code has no HTTP/Selenium/DNS/competitor dependency;
- `MockCollector` implements `CollectorInterface`;
- specified tests pass;
- tests perform no network calls;
- implementation follows `AGENTS.md`;
- no unrelated features appear in the diff.

## Codex invocation

```text
$implement-task
Implement docs/tasks/001-foundation-mock-collector.md.
Do not implement anything outside the task scope.
```

Then:

```text
$code-review
Review the implementation of docs/tasks/001-foundation-mock-collector.md against AGENTS.md and ADR-0001.
```
