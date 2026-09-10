# ADR-0003: Product ↔ competitor monitored link

## Context

`kk.pricewatch` now has a persistent competitor entity and a transport-independent collector contract.

The next module-owned entity must describe which Bitrix catalog element is monitored against which competitor, preserve the exact competitor product/configuration URL, and hold the latest collection state.

A single Bitrix product may be monitored against multiple competitors. The same Bitrix product may also need more than one distinct URL for the same competitor, for example when multiple competitor configurations or offers are deliberately tracked.

Competitor URLs may be long and may contain semantically significant query parameters. They must not be normalized, reordered, decoded, truncated, or otherwise rewritten by the module.

## Decision

Create D7 ORM entity `ProductCompetitorTable` backed by:

```text
b_kk_pricewatch_product_competitor
```

The row represents one monitored relation:

```text
Bitrix product element + competitor + exact competitor URL
```

## Product identity

`PRODUCT_ID` stores the numeric Bitrix iblock element ID being monitored.

Bitrix iblock element IDs are the module boundary identity for this table. The row may point to a normal catalog product element or an SKU/offer element when that is the object the caller intentionally monitors.

Do not duplicate product names, iblock names, article/SKU text, or catalog price data into this table.

Do not create a database foreign key to Bitrix core tables. Existence/lifecycle validation against iblock/catalog belongs to a future service/UI integration layer.

## Competitor relation

`COMPETITOR_ID` stores the `CompetitorTable::ID`.

Expose a normal D7 ORM reference to `CompetitorTable` for queries where practical, but do not rely on a database-level cascading foreign key in this milestone.

Deletion policy for competitors that already have monitored product links will be defined with the future admin/service layer.

## URL and uniqueness

Store the competitor URL exactly as supplied in a `TEXT`-capable field named `URL`.

Also store a derived internal field:

```text
URL_HASH = lowercase hex SHA-256 of the exact URL string
```

The URL hash exists only to support safe indexing. It must never replace the original URL and must never be shown to users as the canonical identifier.

The logical unique key is:

```text
PRODUCT_ID + COMPETITOR_ID + exact URL
```

The physical unique index should therefore use:

```text
PRODUCT_ID + COMPETITOR_ID + URL_HASH
```

This avoids creating an oversized composite index over a potentially long UTF-8 URL.

`URL_HASH` is derived data. Callers must not be able to create a mismatched URL/hash pair. Add/update handling must derive or enforce the hash from `URL` automatically.

No URL normalization is permitted before hashing. These two URLs are different monitored identities if their exact strings differ:

```text
https://example.test/product?a=1&b=2
https://example.test/product?b=2&a=1
```

## Fields

- `ID` — integer primary key, autoincrement.
- `PRODUCT_ID` — required positive integer Bitrix iblock element ID.
- `COMPETITOR_ID` — required positive integer competitor ID.
- `URL` — required non-blank exact URL, stored in a text-capable field.
- `URL_HASH` — required derived lowercase SHA-256 hex string, 64 characters.
- `ACTIVE` — boolean `Y`/`N`, default `Y`.
- `CURRENT_PRICE` — nullable fixed-precision decimal value representing the last successful collected price.
- `CURRENCY` — nullable uppercase 3-letter currency code associated with `CURRENT_PRICE`.
- `STATUS` — required generic collection status, default `new`.
- `ERROR_CODE` — nullable machine-readable last collection error code.
- `ERROR_MESSAGE` — nullable human-readable last collection error message.
- `LAST_CHECK_AT` — nullable timestamp of the most recent completed collection attempt.
- `LAST_SUCCESS_AT` — nullable timestamp of the most recent successful price collection.
- `CREATED_AT` — creation timestamp.
- `UPDATED_AT` — last row update timestamp.

## Money

`CURRENT_PRICE` must use fixed-precision database semantics, targeting a physical type equivalent to:

```text
DECIMAL(18,2)
```

Do not use floating point storage.

At PHP/integration boundaries, preserve the existing collector rule that money is represented as a decimal string.

The ORM implementation must avoid introducing float conversion in custom validators/helpers.

## Currency

`CURRENCY` is nullable because a never-checked row has no current price.

When provided, it must satisfy the same generic contract as collector results: uppercase 3-letter code, initially normally `RUB`.

Do not hardcode a database default of `RUB`; the stored currency should correspond to the successful result that produced `CURRENT_PRICE`.

Cross-field invariant `CURRENT_PRICE <-> CURRENCY` will be enforced by the future collection state service rather than raw ORM field-level validation in this milestone.

## Status model

Initial values:

```text
new
success
error
```

Use a normal string field with application-level constants/validation, not a database enum.

Meaning:

- `new` — no completed collection attempt has been applied yet;
- `success` — the most recent completed attempt succeeded;
- `error` — the most recent completed attempt failed.

Transient execution/queue states such as `pending`, `running`, `retrying` do not belong here. A future queue entity will own those states.

## Error and stale-price semantics

A collection error must not automatically erase the last successful price.

Example state after a successful check followed by an error:

```text
CURRENT_PRICE = 129990.00
CURRENCY = RUB
STATUS = error
ERROR_CODE = COLLECTOR_TIMEOUT
LAST_CHECK_AT = time of failed attempt
LAST_SUCCESS_AT = time of earlier successful attempt
```

This allows the UI to distinguish a stale last-known price from a currently successful check.

The ORM entity stores the fields only. The atomic transition rules for applying `CollectorResponse` results belong to a later `PriceUpdateService` task.

## Timestamps

`CREATED_AT` and `UPDATED_AT` initialize automatically on insert.

`UPDATED_AT` refreshes automatically on update.

`LAST_CHECK_AT` and `LAST_SUCCESS_AT` are operational timestamps and are not auto-generated by arbitrary ORM updates; they will be set by collection orchestration later.

## Indexes

Required physical indexes:

1. unique index on:

```text
PRODUCT_ID, COMPETITOR_ID, URL_HASH
```

2. non-unique lookup index on `PRODUCT_ID` if the unique composite index is not sufficient for the target DB/query planner;
3. non-unique lookup index on `COMPETITOR_ID` if needed for competitor-side listing/batching.

Prefer a small explicit set of indexes based on expected access paths. Do not add speculative indexes for status/timestamps in this milestone.

Index creation must be idempotent and must be verified against the physical DDL/API behavior of supported Bitrix/MySQL rather than assumed from ORM declarations.

## Installation

Extend the existing reusable `SchemaInstaller` to ensure the new table and required indexes exist.

Requirements:

- clean install creates competitor table and product-competitor table;
- reinstall with existing tables preserves rows;
- missing new table/index is created idempotently;
- no existing table is dropped/recreated silently;
- failures are not swallowed.

No production migration framework is introduced yet.

## Uninstall

Normal module uninstall continues to preserve module-owned tables and data.

Explicit delete-data uninstall behavior remains a future task.

## Versioning

This is the next persistent-schema functional milestone. Bump module version:

```text
0.2.0 -> 0.3.0
```

No Marketplace updater package is required yet.

## Security

This entity stores URLs but does not fetch them.

No collector/network execution is added in this milestone.

URLs are untrusted strings and must be escaped when eventually rendered in admin/public UI.

## Alternatives considered

### Unique index directly on URL

Rejected. Long UTF-8 URLs make composite index size database-dependent and fragile.

### Normalize URL before hashing

Rejected. Query parameter order/encoding may be part of a competitor configuration identity. The module must preserve exact user input.

### One row per product/competitor only

Rejected. It prevents deliberately tracking multiple competitor URLs/configurations for one internal product.

### Clear current price on collection error

Rejected. The last known successful value is operationally useful and can be displayed as stale when the latest attempt fails.

### Queue states inside this table

Rejected. Execution lifecycle belongs to a future queue entity; this table describes the last applied collection outcome.

## Consequences

Positive:

- exact competitor configuration URLs are preserved;
- multiple URLs per product/competitor remain possible;
- uniqueness is index-safe for long URLs;
- last known successful price survives temporary collector failures;
- future queue and collector implementations remain decoupled from persistence identity.

Cost:

- URL hash consistency must be enforced carefully;
- physical index creation needs real Bitrix/DB verification;
- product and competitor deletion policies are deferred to the service/admin layer.
