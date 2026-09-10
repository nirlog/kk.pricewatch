# Task 007 — ProductCompetitor Bitrix compatibility fixes

## Goal

Correct the Bitrix runtime compatibility defects introduced by Task 006 before any further functionality is built on top of `ProductCompetitorTable`.

This is a narrow corrective task. Do not add new business functionality.

## Required context

Before implementation read:

- `AGENTS.md`
- `docs/architecture/0002-competitor-entity.md`
- `docs/architecture/0003-product-competitor-link.md`
- `docs/tasks/006-product-competitor-orm.md`
- `.agents/skills/code-review/SKILL.md`
- `lib/Model/ProductCompetitorTable.php`
- `lib/Installer/SchemaInstaller.php`
- current CI workflow

Use `$implement-task`.

## Scope

Implement only:

1. Fix all invalid Bitrix ORM namespaces in `ProductCompetitorTable`.
2. Replace the non-existent SQL-helper index creation API with the supported Bitrix connection API.
3. Preserve idempotent creation of the required unique and lookup indexes.
4. Make the unique-index handling explicit and correct.
5. Add lightweight regression checks that can run without a Bitrix runtime.
6. Update manual real-Bitrix verification documentation if needed.
7. Keep all existing behavior from Task 006 intact.

Do not implement:

- admin UI;
- product edit integration;
- iblock custom properties;
- queue;
- agents/cron;
- collector execution;
- `PriceUpdateService`;
- HTTP parsing;
- Python/Selenium integration;
- history;
- notifications;
- repricing;
- migration framework;
- unrelated refactoring.

## 1. Correct Bitrix ORM namespaces

The current `ProductCompetitorTable` uses invalid imports under:

```php
Bitrix\MainORM\...
```

They must use the real Bitrix D7 namespace:

```php
Bitrix\Main\ORM\...
```

At minimum verify/correct imports for:

- `DataManager`
- `Event`
- `EventResult`
- field classes
- `Reference`
- `LengthValidator`
- `Join`

Do not change the module namespace `KK\PriceWatch\...`.

After this change the class must be loadable in a real Bitrix runtime.

## 2. Correct index creation API

The current installer calls a non-existent method similar to:

```php
$connection->getSqlHelper()->getCreateIndexSql(...)
```

Do not use this API.

Use the supported Bitrix DB connection index creation mechanism, equivalent in responsibility to:

```php
$connection->createIndex(
    $table,
    $name,
    $columns,
    null,
    Connection::INDEX_UNIQUE
);
```

for the composite unique index.

For normal non-unique indexes use the same supported connection-level API with the appropriate index type/default.

Import/use the actual Bitrix connection class/constants required by the supported runtime. Do not hard-code MySQL `CREATE INDEX` SQL unless there is no supported Bitrix API.

Required indexes remain:

```text
UNIQUE(PRODUCT_ID, COMPETITOR_ID, URL_HASH)
INDEX(PRODUCT_ID)
INDEX(COMPETITOR_ID)
```

## 3. Idempotence

Re-running `SchemaInstaller::install()` must not fail merely because the required tables/indexes already exist.

Preserve these rules:

- existing table rows are not dropped;
- existing tables are not recreated;
- missing required indexes are added;
- existing required indexes are left intact;
- installer failures are not silently swallowed.

### Unique-index verification caveat

The previous code used:

```php
$connection->isIndexExists($table, $columns)
```

which may establish that an index exists for those columns without proving that it is `UNIQUE`.

For this early-development milestone, choose one of these approaches:

1. Prefer a supported Bitrix metadata/API path that can confirm the required unique index correctly; or
2. if the available cross-DB API cannot distinguish uniqueness cleanly, make the limitation explicit, use a deterministic required index name, avoid destructive automatic replacement, and document the real-Bitrix/manual verification step.

Do not drop/recreate an existing production index automatically in this task merely to force uniqueness.

The normal clean-install path must definitely create the composite index as UNIQUE.

## 4. Preserve Task 006 behavior

Do not alter the intended model semantics:

- exact `URL` is preserved byte-for-byte;
- `URL_HASH` is lowercase SHA-256 of the exact stored URL;
- caller-supplied mismatched hash is not trusted;
- changing only `URL_HASH` must not corrupt consistency;
- `CURRENT_PRICE` remains fixed precision equivalent to `DECIMAL(18,2)`;
- status values remain `new`, `success`, `error`;
- stale last-successful price remains representable after a later error;
- normal uninstall preserves data;
- module version remains `0.3.0` unless a concrete Bitrix requirement makes a bump necessary.

## 5. Regression checks without Bitrix runtime

Current GitHub Actions does not include Bitrix core, so do not create fake ORM implementations.

Add lightweight checks that meaningfully prevent recurrence, at minimum:

1. ensure `lib/Model/ProductCompetitorTable.php` does not contain `Bitrix\\MainORM\\`;
2. ensure the file contains the expected `Bitrix\\Main\\ORM\\` imports;
3. ensure `SchemaInstaller` does not contain `getCreateIndexSql`;
4. ensure the composite unique-index creation path uses Bitrix connection API and `Connection::INDEX_UNIQUE` (or the equivalent supported constant/API);
5. preserve existing metadata tests for decimal precision and URL-hash behavior.

Source-level tests are acceptable here only as guards. They must not claim to prove full Bitrix integration.

All existing PHPUnit tests must remain green.

## 6. Manual Bitrix smoke test

Update/retain concise documentation for a real Bitrix installation.

At minimum verify:

1. module installs without fatal class-not-found errors;
2. `ProductCompetitorTable` can be autoloaded;
3. both module tables exist;
4. `b_kk_pricewatch_product_competitor` has the required unique composite index;
5. lookup indexes exist;
6. exact duplicate product+competitor+URL is rejected by the database unique constraint;
7. same product+competitor with a different exact URL succeeds;
8. reinstall is idempotent and preserves rows;
9. `CURRENT_PRICE` physical type remains equivalent to `DECIMAL(18,2)`.

Do not add temporary production debug pages.

## Composer / CI

A valid `composer.lock` now exists in the repository. Preserve it.

Do not change dependency constraints.

GitHub Actions must remain green:

- dependencies install from `composer.lock`;
- `composer validate` succeeds;
- PHP syntax checks succeed;
- PHPUnit succeeds.

## Acceptance criteria

Task is complete when:

- no `Bitrix\MainORM\...` imports remain in `ProductCompetitorTable`;
- real D7 imports use `Bitrix\Main\ORM\...`;
- installer no longer calls `getCreateIndexSql()`;
- clean install creates the required composite index as UNIQUE using supported Bitrix DB APIs;
- lookup indexes are created idempotently;
- reinstall does not destroy data;
- Task 006 URL/hash/money/status semantics remain unchanged;
- lightweight regression tests guard both corrected defects;
- GitHub Actions is green;
- manual real-Bitrix smoke-test steps are documented;
- no new feature scope is added.

## Codex invocation

```text
$implement-task
Implement docs/tasks/007-product-competitor-bitrix-compatibility-fixes.md.
Do not implement anything outside the task scope.
```

After implementation:

```text
$code-review
Review docs/tasks/007-product-competitor-bitrix-compatibility-fixes.md against AGENTS.md, ADR-0002 and ADR-0003.
Pay particular attention to real Bitrix class namespaces, actual availability of DB APIs, clean-install index DDL, uniqueness semantics, reinstall idempotence, and preservation of Task 006 behavior.
```
