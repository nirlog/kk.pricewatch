# Task 003 — Competitor ORM entity

## Goal

Add the first persistent module-owned entity: competitors.

Implement a D7 ORM table for generic competitor configuration, integrate idempotent table creation into the module installer, preserve data on uninstall by default, and keep the schema independent of any specific competitor.

This task prepares the module for later admin UI and product-to-competitor links. It must not implement those features yet.

## Required context

Before implementation read:

- `AGENTS.md`
- `docs/architecture/0001-collector-boundary.md`
- `docs/architecture/0002-competitor-entity.md`
- `docs/tasks/001-foundation-mock-collector.md`
- `docs/tasks/002-collector-response-global-errors-and-ci.md`
- current installer and collector code

Use `$implement-task`.

## Scope

Implement only:

1. `CompetitorTable` D7 ORM entity.
2. Generic collector type constants/validation for `mock` and `external`.
3. JSON encoding/decoding helper for collector options.
4. Idempotent database schema installer for the competitor table.
5. Integration of schema creation into module installation.
6. Data-preserving uninstall behavior.
7. Module version bump to `0.2.0`.
8. Unit tests for Bitrix-independent validation/helpers.
9. Minimal developer documentation for the new entity and manual Bitrix integration verification.

Do not implement:

- competitors admin list/edit UI;
- product ↔ competitor table;
- custom iblock property;
- queue;
- agents/cron;
- price collection orchestration;
- external HTTP requests;
- local HTML parsing;
- Python/Selenium integration;
- price history;
- notifications;
- automatic repricing;
- competitor-specific logic or columns;
- secrets/API tokens.

## ORM entity

Create a class equivalent in responsibility to:

```text
KK\PriceWatch\Model\CompetitorTable
```

The exact namespace may differ only if there is a clear project-wide convention that is more appropriate.

Database table name:

```text
b_kk_pricewatch_competitor
```

Use D7 ORM `DataManager` and field objects.

## Required fields

### ID

- integer;
- primary key;
- autoincrement.

### NAME

- string;
- required;
- maximum 255 characters;
- must reject an empty/blank value at the application/ORM validation boundary.

### ACTIVE

- boolean;
- database representation `Y` / `N`;
- default `Y`.

### SORT

- integer;
- default `500`.

### DOMAIN

- nullable/optional string;
- maximum 255 characters;
- metadata only;
- must not be used to choose collector implementation;
- no uniqueness requirement in this task.

### COLLECTOR_TYPE

- required string;
- maximum length sufficient for future collector type names;
- default `mock`;
- initial allowed values:
  - `mock`
  - `external`

Do not use a database enum.

Provide a small PHP class/value helper for the known collector type values, for example:

```php
CollectorType::MOCK
CollectorType::EXTERNAL
```

It must reject unknown collector types through application-level validation.

Do not implement transport behavior for `external` yet.

### COLLECTOR_HANDLER

- nullable/optional string;
- maximum 512 characters;
- stores an implementation-independent handler reference/path.

Example future values:

```text
/api/collectors/browser
/api/collectors/dns
```

Do not execute, fetch, normalize, or resolve the handler in this task.

Do not branch on known handler strings.

### COLLECTOR_OPTIONS

- text field;
- contains JSON object data;
- logical default is an empty object.

Do not use PHP `serialize()` / `unserialize()`.

Do not introduce site-specific columns such as:

```text
DNS_REGION
PRICE_SELECTOR
WAIT_SELECTOR
BROWSER_MODE
```

Those values belong in `COLLECTOR_OPTIONS` when needed.

### CREATED_AT

- datetime;
- initialized automatically on insert.

### UPDATED_AT

- datetime;
- initialized automatically on insert;
- automatically refreshed on update without requiring callers to provide it.

## Collector options helper

Implement a Bitrix-independent helper/value object responsible only for JSON conversion/validation.

Required behavior:

```php
CollectorOptions::encode([])
```

returns valid JSON object representation.

```php
CollectorOptions::decode($json)
```

returns a PHP associative array.

Requirements:

- use JSON exception mode (`JSON_THROW_ON_ERROR`) or equivalent strict error handling;
- reject invalid JSON;
- reject a JSON list/array if the contract expects an object/options map;
- preserve nested arrays/scalars inside option values;
- never use PHP serialization;
- do not interpret competitor-specific keys;
- define and test behavior for empty/null database input consistently with ADR-0002.

Prefer canonical encoding that does not escape Unicode unnecessarily if practical.

Example logical options:

```json
{
  "region": "Санкт-Петербург",
  "price_selector": ".price"
}
```

The helper must treat these keys as opaque.

## Collector type validation

Provide Bitrix-independent validation that can be unit tested in the current CI environment.

At minimum:

- `mock` is valid;
- `external` is valid;
- arbitrary values such as `dns`, `browser`, empty string, or `unknown` are rejected as collector **types**.

Note: `dns` and `browser` are future handler identities/paths, not collector type values in the Bitrix module.

## Table installation

Add a small reusable schema installation component rather than putting all database details directly into `DoInstall()`.

A reasonable responsibility is:

```text
DatabaseInstaller / SchemaInstaller
```

with an operation similar to:

```php
install(): void
```

that ensures required tables exist.

Requirements:

- clean install creates `b_kk_pricewatch_competitor`;
- if the table already exists, installation succeeds without dropping/recreating it;
- existing rows are preserved;
- use Bitrix database/ORM facilities rather than hard-coded MySQL DDL where practical;
- failures must not be silently swallowed;
- avoid leaving the module registered after a failed fresh installation if schema creation did not complete.

The implementation must be structured so future module tables can be added to the same schema installer.

## Uninstall behavior

For this task, uninstall must preserve competitor data by default.

That means a normal `DoUninstall()` must **not** drop `b_kk_pricewatch_competitor`.

Document this behavior.

Do not add an uninstall UI/checkbox in this task. A later installer UX task may add an explicit delete-data option.

Reinstalling the module with an existing preserved table must be safe.

## Version

Update:

```text
0.1.0 → 0.2.0
```

Set a valid current version date consistent with the existing module format.

No Marketplace update package/updater is required in this task.

Do not invent a production migration framework yet.

## Validation boundaries

The entity/configuration layer must enforce at least:

- non-empty `NAME`;
- known `COLLECTOR_TYPE`;
- valid collector-options JSON/object representation.

The following are intentionally allowed to be empty in this task:

- `DOMAIN`;
- `COLLECTOR_HANDLER`.

Do not require a handler merely because `COLLECTOR_TYPE=external` at raw ORM level. That cross-field rule belongs to the future service/admin validation layer.

## Security

This task stores configuration only.

- no HTTP requests;
- no URL fetching;
- no Selenium;
- no handler execution;
- no secrets in `COLLECTOR_OPTIONS`;
- no competitor-specific behavior.

## Testing

Current GitHub CI does not contain a Bitrix runtime, so do not fake full ORM integration with fragile stubs merely to increase test count.

Add normal PHPUnit tests for Bitrix-independent code, at minimum:

1. `mock` collector type accepted;
2. `external` collector type accepted;
3. unknown collector type rejected;
4. empty collector type rejected;
5. empty options encode/decode round-trip;
6. nested options round-trip;
7. Cyrillic/Unicode option values round-trip;
8. invalid JSON rejected;
9. JSON list rejected when an options object is required;
10. site-specific keys are preserved opaquely.

All existing collector tests must continue to pass.

## Manual Bitrix integration checklist

Add a concise document or section describing how to verify this task on a real Bitrix installation.

At minimum verify:

1. install module from a clean state;
2. table `b_kk_pricewatch_competitor` exists;
3. create a competitor through `CompetitorTable::add()` with minimal fields;
4. default `ACTIVE=Y`, `SORT=500`, `COLLECTOR_TYPE=mock` are applied;
5. `CREATED_AT` and `UPDATED_AT` are populated;
6. update the row and verify `UPDATED_AT` changes;
7. uninstall module and verify table/data remain;
8. reinstall module and verify the existing row remains and installation succeeds.

Do not add temporary debug/admin pages to production module code for this checklist.

## CI and Composer lock

Do not change dependency constraints in this task.

If `composer.lock` is still absent, generate and commit it so GitHub Actions uses reproducible dependency versions. This is the only allowed CI housekeeping outside the ORM feature itself.

After adding the lock file, CI must continue to pass on PHP 8.2.

## Documentation

Add/update only the minimum documentation required to explain:

- competitor table fields;
- meaning of `COLLECTOR_TYPE`;
- meaning of `COLLECTOR_HANDLER`;
- generic `COLLECTOR_OPTIONS` JSON;
- data-preserving uninstall behavior;
- manual Bitrix verification steps.

Do not document admin UI that does not exist yet.

## Acceptance criteria

Task is complete when:

- `CompetitorTable` exists and maps to `b_kk_pricewatch_competitor`;
- all required fields/defaults are defined;
- the schema contains no competitor-specific columns;
- collector type validation supports only the current generic types;
- collector options use validated JSON rather than PHP serialization;
- clean schema installation is implemented;
- repeated/reinstall schema creation is idempotent and data-preserving;
- normal uninstall does not drop competitor data;
- module version is `0.2.0`;
- existing collector contract/tests remain unchanged in behavior;
- new Bitrix-independent unit tests pass;
- GitHub Actions is green;
- `composer.lock` is committed if it was previously absent;
- no admin UI, product links, parsing, queues, agents, or external calls are added.

## Codex invocation

```text
$implement-task
Implement docs/tasks/003-competitor-orm.md.
Do not implement anything outside the task scope.
```

After implementation:

```text
$code-review
Review the implementation of docs/tasks/003-competitor-orm.md against AGENTS.md, ADR-0001 and ADR-0002.
Pay particular attention to Bitrix install/reinstall safety, data-preserving uninstall, timestamp behavior, JSON options validation, and absence of competitor-specific schema.
```
