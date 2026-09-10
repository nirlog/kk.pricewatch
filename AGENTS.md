# kk.pricewatch — Codex project rules

## Project

`kk.pricewatch` is a 1C-Bitrix D7 module for monitoring competitor prices for catalog products.

The Bitrix module owns competitor configuration, product-to-competitor links, scheduling/queues, collector request creation, response validation/persistence, admin UI, permissions, and the public-side admin comparison block.

A future external Python service will own complex data acquisition for sites requiring Selenium/browser automation or site-specific logic.

The Bitrix module must not contain competitor-specific branches such as `if ($competitor === 'dns')`.

## Platform

- Module ID: `kk.pricewatch`
- PHP: 8.2+
- 1C-Bitrix: D7 APIs first
- Namespace: `KK\PriceWatch`
- Expected path: `/local/modules/kk.pricewatch/`

Prefer `Bitrix\Main` and D7 ORM. Use legacy APIs only when required by a Bitrix extension point or when no appropriate D7 equivalent exists.

## Architecture principles

1. Separate business logic from transport and parsing.
2. All collectors conform to one stable contract.
3. The first collector implementation is an in-process `MockCollector`.
4. A future external collector must be replaceable without changing orchestration or persistence logic.
5. The module must work when no external collector service is configured.
6. Product/competitor operational state belongs in module ORM tables. A future custom iblock property may be editing UI, but not the only source of truth.
7. Do not encode DNS, KometaPC, RoyalPC, or another competitor in module core.
8. Preserve complete competitor URLs, including query parameters.
9. Collector/network calls must use explicit timeouts and structured errors.
10. Batch operation is part of collector contract v1.

## Collector contract v1

Request:

```json
{
  "schema_version": "1.0",
  "request_id": "UUID",
  "items": [
    {
      "id": "123",
      "url": "https://competitor.example/product/123"
    }
  ],
  "options": {}
}
```

Response:

```json
{
  "schema_version": "1.0",
  "request_id": "UUID",
  "success": true,
  "items": [
    {
      "id": "123",
      "success": true,
      "price": "129990.00",
      "currency": "RUB"
    }
  ]
}
```

Item error:

```json
{
  "id": "123",
  "success": false,
  "error": {
    "code": "PRICE_NOT_FOUND",
    "message": "Price was not found"
  }
}
```

Rules:
- `schema_version` is required.
- `request_id` round-trips unchanged.
- item `id` round-trips unchanged.
- `price` is a non-negative decimal string, never a float.
- `currency` is an uppercase 3-letter currency code; initially `RUB`.
- global collector failure and per-item failure are different.
- one failed item must not discard successful items in the same batch.
- unknown response fields should be ignored for forward compatibility unless they conflict with required fields.

## Error model

Initial stable codes:

- `INVALID_REQUEST`
- `MOCK_NO_MATCH`
- `PRICE_NOT_FOUND`
- `COLLECTOR_ERROR`
- `COLLECTOR_TIMEOUT`
- `INVALID_RESPONSE`

Do not make application logic depend on localized error messages.

## MockCollector

`MockCollector` is a development/test implementation of the same interface a future HTTP collector will implement.

It must:
- accept the normal collector request object;
- match items against scenarios;
- support `exact` and `contains` URL matching;
- return normal collector response objects;
- support explicit success and explicit item-error scenarios;
- support default success or strict `MOCK_NO_MATCH`;
- default to strict mode in tests;
- perform no HTTP/Selenium calls;
- never use `sleep()` to simulate timeouts.

## Persistence

Use D7 ORM for module-owned data. Use fixed-precision decimal semantics for money, not floating point.

Do not add history, notifications, analytics, or automatic repricing to the first milestone unless explicitly requested.

## Security

- Treat competitor URLs and future collector endpoints as untrusted input.
- Escape admin/public output.
- Check Bitrix session tokens on state-changing admin actions.
- Check module permissions.
- Never expose future collector secrets in logs or HTML.
- Do not implement anti-bot bypasses or CAPTCHA circumvention.

## Install/update/uninstall

Every schema change must consider clean install, upgrades, repeated installer execution where applicable, uninstall behavior, and preservation of user data unless explicit deletion is requested.

Do not silently destroy module data.

## Scope discipline

For every task:
- implement only requested scope;
- avoid unrelated refactoring;
- do not delete files just because they appear unused without checking references and Bitrix integration;
- preserve compatibility unless a breaking change is explicitly authorized.

## Verification

Before completion:
- run available automated tests;
- run PHP syntax checks on changed PHP files where practical;
- inspect the final diff;
- verify autoload/installer implications;
- verify no secrets, dumps, IDE files, browser profiles, or generated artifacts were added.
