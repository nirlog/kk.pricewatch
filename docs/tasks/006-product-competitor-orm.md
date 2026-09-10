# Task 006 — Product ↔ Competitor ORM

## Goal

Add the persistent monitored-link entity connecting a Bitrix catalog element to a competitor and an exact competitor product/configuration URL.

The entity must preserve the exact URL, safely enforce logical uniqueness for long URLs, store the latest collection outcome, and integrate idempotently with the existing schema installer.

This task adds persistence only. It must not implement collection orchestration, admin UI, queues, agents, HTTP requests, or product editing integration.

## Required context

Before implementation read:

- `AGENTS.md`
- `docs/architecture/0001-collector-boundary.md`
- `docs/architecture/0002-competitor-entity.md`
- `docs/architecture/0003-product-competitor-link.md`
- `docs/tasks/003-competitor-orm.md`
- `docs/tasks/004-orm-schema-corrections.md`
- current `CompetitorTable`, `SchemaInstaller`, collector money validation, and CI

Use `$implement-task`.

Task 005 (`composer.lock`) is currently deferred because the Codex execution environment cannot access Packagist. Do not fabricate or manually edit a lock file in this task. If a valid lock file already exists when this task starts, preserve it.

## Scope

Implement only:

1. D7 ORM entity for product ↔ competitor monitored links.
2. Generic collection-status constants/validation.
3. Exact URL preservation and derived SHA-256 URL hash.
4. Logical/physical uniqueness for `PRODUCT_ID + COMPETITOR_ID + exact URL` using the URL hash.
5. Latest collection result fields (price/currency/status/error/timestamps) as storage only.
6. D7 relation/reference to `CompetitorTable` where appropriate.
7. Idempotent table/index installation through the existing schema installer.
8. Data-preserving uninstall behavior.
9. Module version bump to `0.3.0`.
10. Bitrix-independent unit tests for URL hashing/status helpers where practical.
11. Lightweight metadata/source checks where appropriate without creating a fake Bitrix runtime.
12. Manual real-Bitrix verification documentation.

Do not implement:

- admin list/edit UI;
- iblock custom property;
- product edit-tab integration;
- queue;
- agents/cron;
- manual "check price" action;
- `PriceUpdateService`;
- applying `CollectorResponse` to persistence;
- HTTP collector;
- local HTML parsing;
- Python/Selenium integration;
- price history;
- notifications;
- automatic repricing;
- delete-data uninstall UI;
- production migration/updater framework;
- competitor-specific columns or behavior.

## ORM entity

Create a class equivalent in responsibility to:

```text
KK\PriceWatch\Model\ProductCompetitorTable
```

Database table:

```text
b_kk_pricewatch_product_competitor
```

Use D7 `DataManager` and normal field objects.

## Required fields

### ID

- integer;
- primary key;
- autoincrement.

### PRODUCT_ID

- required integer;
- must be greater than zero;
- stores Bitrix iblock element ID;
- do not duplicate product metadata into this table.

Do not create a database FK to Bitrix core tables in this task.

### COMPETITOR_ID

- required integer;
- must be greater than zero;
- stores `CompetitorTable::ID`.

Expose a D7 `ReferenceField`/relation to `CompetitorTable` if cleanly supported, but do not add database-level cascade deletion behavior.

### URL

- required;
- non-blank;
- store in a text-capable field;
- preserve exact input string;
- do not normalize query parameters;
- do not trim/reorder/decode/re-encode the stored canonical value except rejecting a value that is entirely blank.

This task does not fetch or validate the remote URL.

### URL_HASH

- required derived internal field;
- exactly 64 lowercase hexadecimal characters;
- value is:

```php
hash('sha256', $exactUrl)
```

The hash must be based on the exact stored URL string.

Callers must not be able to create a mismatched `URL` / `URL_HASH` pair.

Required behavior:

- on add, derive hash from supplied `URL`;
- on update when `URL` changes, recompute hash automatically;
- if caller supplies a conflicting `URL_HASH`, do not trust it;
- an update that tries to modify only `URL_HASH` must not corrupt consistency.

Choose the cleanest D7 event/service implementation available, but document the invariant.

### ACTIVE

- boolean stored `Y`/`N`;
- default `Y`.

### CURRENT_PRICE

- nullable;
- fixed-precision decimal storage;
- physical target equivalent to `DECIMAL(18,2)`;
- stores the last successful collected price;
- must not use floating point persistence.

Use Bitrix D7 decimal/fixed-precision field facilities appropriate for the supported runtime. Verify the physical DDL implications rather than assuming method names are sufficient.

Do not implement collection-time state transitions in this task.

### CURRENCY

- nullable string;
- maximum/exact application format: uppercase 3-letter code when present;
- no default `RUB` at database level.

Reuse existing generic money/currency validation semantics where cleanly possible without coupling ORM storage to collector transport classes unnecessarily.

### STATUS

- required string;
- default `new`;
- initial allowed values:
  - `new`
  - `success`
  - `error`
- no database enum.

Provide a Bitrix-independent helper/constants class, e.g.:

```php
CollectionStatus::NEW
CollectionStatus::SUCCESS
CollectionStatus::ERROR
```

Unknown values must be rejected at the application/ORM validation boundary.

Do not add queue/transient states such as `pending`, `running`, or `retrying` here.

### ERROR_CODE

- nullable string;
- max 64 characters;
- machine-readable last collection error code.

### ERROR_MESSAGE

- nullable text field;
- last human-readable collection error message.

### LAST_CHECK_AT

- nullable datetime;
- last completed collection attempt timestamp;
- not automatically modified by arbitrary ORM updates.

### LAST_SUCCESS_AT

- nullable datetime;
- last successful price collection timestamp;
- not automatically modified by arbitrary ORM updates.

### CREATED_AT

- required datetime;
- auto initialized on insert.

### UPDATED_AT

- required datetime;
- auto initialized on insert;
- automatically refreshed on update.

## Stale-price semantics

Do not add ORM validation that forces price/error fields into one rigid cross-field combination.

The following future state is valid and must remain representable:

```text
CURRENT_PRICE = 129990.00
CURRENCY = RUB
STATUS = error
ERROR_CODE = COLLECTOR_TIMEOUT
LAST_CHECK_AT = latest failed attempt
LAST_SUCCESS_AT = earlier successful attempt
```

A later collection-state service will perform atomic transitions and clear/set fields appropriately.

## URL helper

Prefer a small Bitrix-independent helper/value class for exact URL hashing, for example:

```php
ProductUrl::hash(string $url): string
```

or equivalent responsibility.

Required tests:

- exact same URL produces same lowercase SHA-256;
- query parameter order changes the hash;
- query string is preserved exactly by the helper/entity preprocessing;
- blank URL is rejected;
- Unicode/percent-encoded URL text is hashed byte-for-byte as supplied.

Do not perform canonicalization.

## Logical uniqueness

Logical duplicate:

```text
same PRODUCT_ID
same COMPETITOR_ID
same exact URL
```

must not be insertable twice.

Use the physical unique index:

```text
PRODUCT_ID, COMPETITOR_ID, URL_HASH
```

Do not create a unique index directly over the full `URL` text field.

Because SHA-256 collisions are theoretically possible, do not describe `URL_HASH` as the canonical identity. The full URL remains source data. For this module's uniqueness/index purpose SHA-256 is accepted.

## Indexes

At minimum create and verify idempotently:

```text
UNIQUE(PRODUCT_ID, COMPETITOR_ID, URL_HASH)
```

Also add simple lookup indexes for `PRODUCT_ID` and `COMPETITOR_ID` only if the chosen Bitrix/DB implementation benefits from them and they can be created cleanly/idempotently. Avoid speculative status/date indexes.

The schema installer must not fail when rerun after indexes already exist.

Do not assume ORM field declarations automatically create all required secondary indexes. Inspect the chosen Bitrix DB API / generated DDL path.

## Schema installer

Extend the existing `SchemaInstaller` rather than introducing a second independent installation path.

Required behavior:

- clean install creates `b_kk_pricewatch_competitor` if missing;
- clean install creates `b_kk_pricewatch_product_competitor` if missing;
- required indexes are created when missing;
- rerun/reinstall is idempotent;
- existing rows are preserved;
- tables are not dropped/recreated automatically;
- failures are not silently swallowed.

If Bitrix does not expose a clean cross-DB helper for idempotent secondary index creation, use the narrowest supported SQL-helper/connection mechanism and document the DB implication. Do not introduce broad vendor-specific schema code unnecessarily.

## Uninstall

Normal `DoUninstall()` must continue to preserve both module-owned tables and their data.

No delete-data option in this task.

## Version

Bump:

```text
0.2.0 -> 0.3.0
```

Use the existing version-date format.

No Marketplace updater package/migration framework yet.

## Validation

At minimum enforce:

- positive `PRODUCT_ID`;
- positive `COMPETITOR_ID`;
- non-blank `URL`;
- derived valid `URL_HASH`;
- valid status;
- valid uppercase currency when present;
- fixed-precision money field semantics.

Do not require `CURRENT_PRICE` and `CURRENCY` together at raw ORM level yet; that belongs to future state-transition/service validation.

## Tests

Current CI has no Bitrix runtime. Do not create fragile fake D7 classes merely to inflate test coverage.

Add Bitrix-independent PHPUnit tests at minimum for:

1. `new`, `success`, `error` statuses accepted;
2. unknown/empty status rejected;
3. exact URL SHA-256 generation;
4. different query parameter order produces a different hash;
5. blank URL rejected by URL helper/validation;
6. existing collector/model tests continue to pass.

Add lightweight source/metadata checks only where they provide real regression value, for example ensuring the ORM declaration includes the intended fixed precision and URL hash/index contract. Do not pretend such tests prove actual Bitrix DDL.

## Manual Bitrix verification

Add/update documentation with real-environment checks:

1. install/reinstall module on a development Bitrix instance;
2. confirm `b_kk_pricewatch_product_competitor` exists;
3. inspect physical field types, especially `URL`, `URL_HASH`, and `CURRENT_PRICE`;
4. confirm `CURRENT_PRICE` is physically fixed precision equivalent to `DECIMAL(18,2)`;
5. confirm the unique index on `PRODUCT_ID, COMPETITOR_ID, URL_HASH` exists;
6. insert one row with a long URL/query string and confirm exact round-trip;
7. insert the exact duplicate and confirm uniqueness rejection;
8. insert same product+competitor with a different exact URL and confirm it succeeds;
9. update URL and confirm `URL_HASH` changes automatically;
10. attempt a caller-supplied mismatched hash and confirm consistency is preserved;
11. confirm defaults `ACTIVE=Y`, `STATUS=new` and timestamps;
12. update the row and confirm `UPDATED_AT` changes;
13. uninstall/reinstall and confirm existing rows remain.

Do not add a production debug/admin page for this verification.

## CI

Existing GitHub Actions must remain green:

- Composer install;
- Composer validation;
- PHP syntax checks;
- PHPUnit.

Do not fail the task solely because Codex cannot generate `composer.lock` while its environment is blocked from Packagist; Task 005 already records that infrastructure limitation.

## Documentation

Add only the minimum developer documentation needed to explain:

- table purpose;
- exact URL and URL hash semantics;
- logical uniqueness;
- field/status meaning;
- stale last-known price behavior;
- manual Bitrix verification.

Do not document admin UI or collection actions that do not exist.

## Acceptance criteria

Task is complete when:

- `ProductCompetitorTable` maps to `b_kk_pricewatch_product_competitor`;
- exact URLs are stored without normalization;
- `URL_HASH` is automatically derived from exact URL;
- logical duplicate product+competitor+URL rows are prevented through the unique hash index;
- same product+competitor with a different exact URL remains allowed;
- current price uses fixed-precision storage, not float;
- `STATUS` supports only `new`, `success`, `error`;
- last successful price can coexist with a later `STATUS=error`;
- timestamps/defaults are defined correctly;
- schema/index installation is idempotent and data-preserving;
- normal uninstall preserves data;
- version is `0.3.0`;
- no queue/admin/collector execution is added;
- tests are green in GitHub Actions;
- physical DDL/index verification steps are documented.

## Codex invocation

```text
$implement-task
Implement docs/tasks/006-product-competitor-orm.md.
Do not implement anything outside the task scope.
```

After implementation:

```text
$code-review
Review docs/tasks/006-product-competitor-orm.md against AGENTS.md, ADR-0002 and ADR-0003.
Pay particular attention to exact URL preservation, URL_HASH consistency, composite unique-index DDL, fixed-precision CURRENT_PRICE storage, install/reinstall safety, and stale-price semantics.
```
