# Task 008 — Bitrix ORM validation API compatibility

## Goal

Fix module installation on a real Bitrix runtime by replacing the unsupported `configureValidation()` calls in module ORM entities with the supported D7 field validation API.

This is a narrow corrective task based on a real installation failure:

```text
Call to undefined method Bitrix\Main\ORM\Fields\StringField::configureValidation()
```

The failure currently occurs while initializing `CompetitorTable`, before schema creation can complete.

Do not add new business functionality.

## Required context

Before implementation read:

- `AGENTS.md`
- `docs/architecture/0002-competitor-entity.md`
- `docs/architecture/0003-product-competitor-link.md`
- `docs/tasks/003-competitor-orm.md`
- `docs/tasks/006-product-competitor-orm.md`
- `docs/tasks/007-product-competitor-bitrix-compatibility-fixes.md`
- `.agents/skills/code-review/SKILL.md`
- `lib/Model/CompetitorTable.php`
- `lib/Model/ProductCompetitorTable.php`
- current Bitrix D7 `Field` validation API semantics

Use `$implement-task`.

## Root cause

`Bitrix\Main\ORM\Fields\Field` supports attaching validators through `addValidator($validator)` and supports constructor validation metadata, but does not provide a fluent `configureValidation()` method.

Therefore every use of:

```php
->configureValidation(...)
```

in module ORM entities is invalid and may cause a fatal error at entity initialization.

The implementation must use the supported Bitrix D7 mechanism, preferably explicit `->addValidator(...)` calls because they are clear, composable, and work with both `LengthValidator` objects and callable validators.

## Scope

Implement only:

1. Remove all `configureValidation()` calls from `CompetitorTable`.
2. Remove all `configureValidation()` calls from `ProductCompetitorTable`.
3. Re-express the same validation rules through supported Bitrix D7 `addValidator()` calls or an equally supported constructor-based validation mechanism.
4. Preserve all existing field semantics and DDL-relevant `LengthValidator` instances.
5. Add regression checks preventing `configureValidation()` from returning to module ORM source.
6. Update developer/manual smoke-test documentation if useful.
7. Keep module version at `0.3.0` unless a concrete Bitrix installer requirement demands otherwise.

Do not implement:

- admin UI;
- product edit integration;
- queue;
- agents/cron;
- collector execution;
- HTTP collector;
- Python/Selenium integration;
- history;
- notifications;
- repricing;
- migration framework;
- unrelated refactoring.

## Required validation behavior to preserve

### CompetitorTable

Preserve these rules:

- `NAME` required and whitespace-only value rejected;
- `NAME` max 255;
- `COLLECTOR_TYPE` required, default `mock`, only known generic types accepted;
- `COLLECTOR_TYPE` max 64;
- `COLLECTOR_HANDLER` nullable, max 512;
- `COLLECTOR_OPTIONS` required/default `{}`, and when provided must decode as a valid JSON object;
- all previous defaults/timestamps remain unchanged.

A correct style is conceptually similar to:

```php
(new StringField('NAME'))
    ->configureRequired()
    ->configureSize(255)
    ->addValidator(static fn(string $value): bool|string =>
        trim($value) !== '' ?: 'Competitor name must not be blank.'
    )
    ->addValidator(new LengthValidator(null, 255));
```

For `COLLECTOR_HANDLER`, the `LengthValidator(null, 512)` must remain attached because it is also relevant to physical varchar generation on the supported Bitrix/MySQL path.

### ProductCompetitorTable

Preserve these rules:

- `PRODUCT_ID` > 0;
- `COMPETITOR_ID` > 0;
- `URL` required/non-blank;
- `URL_HASH` exactly 64 lowercase hex chars and length validator retained;
- `CURRENCY`, when present, passes existing uppercase 3-letter validation;
- `STATUS` only `new`, `success`, `error`;
- `ERROR_CODE` max 64;
- existing exact URL/hash invariant remains unchanged;
- current price remains fixed precision;
- timestamp behavior remains unchanged.

Use one `addValidator()` call per validator where practical. Do not introduce a wrapper merely to emulate `configureValidation()`.

## DDL preservation

This task must not accidentally remove DDL-relevant validators.

In particular preserve:

```php
new LengthValidator(null, 512)
```

for `COLLECTOR_HANDLER`, and the existing exact-length/maximum-length validators used for other string fields.

Do not assume `configureSize()` alone is sufficient for physical varchar length generation.

## Regression tests

Current GitHub CI has no Bitrix runtime, so use lightweight source-level guards only for API misuse.

At minimum add/update tests so they assert:

1. neither `lib/Model/CompetitorTable.php` nor `lib/Model/ProductCompetitorTable.php` contains `configureValidation(`;
2. ORM source contains supported `addValidator(` usage;
3. `COLLECTOR_HANDLER` still has `LengthValidator(null, 512)`;
4. existing Task 006/007 guards for namespace, decimal precision, URL hash, and index API remain green.

Do not pretend these tests prove real Bitrix runtime compatibility.

## Real Bitrix smoke test

After implementation, verify manually on the same Bitrix environment that produced the fatal error:

1. remove/redeploy the updated module files;
2. open module installation;
3. confirm there is no `configureValidation()` fatal error;
4. confirm `CompetitorTable::getEntity()` initializes;
5. confirm `ProductCompetitorTable::getEntity()` initializes;
6. install module and confirm both tables exist;
7. confirm required indexes exist;
8. add a minimal competitor and a minimal product/competitor link;
9. confirm invalid values are still rejected through ORM validation;
10. reinstall and confirm data is preserved.

If another real Bitrix API incompatibility appears after this fix, report it rather than adding speculative compatibility shims unrelated to the observed failure.

## CI

Preserve `composer.lock`.

GitHub Actions must remain green:

- install dependencies from lock file;
- `composer validate`;
- PHP syntax checks;
- PHPUnit.

## Acceptance criteria

Task is complete when:

- no production ORM entity uses `configureValidation()`;
- all prior validation behavior is retained using supported D7 APIs;
- DDL-relevant `LengthValidator` objects remain attached;
- no Task 006 URL/hash/money/status semantics change;
- no Task 007 namespace/index fixes regress;
- source-level regression tests prevent reintroduction of `configureValidation()`;
- CI is green;
- the module progresses past the observed installation fatal error on a real Bitrix runtime;
- no new feature scope is added.

## Codex invocation

```text
$implement-task
Implement docs/tasks/008-orm-validation-api-compatibility.md.
Do not implement anything outside the task scope.
```

After implementation:

```text
$code-review
Review docs/tasks/008-orm-validation-api-compatibility.md against AGENTS.md, ADR-0002 and ADR-0003.
Pay particular attention to actual Bitrix Field validation APIs, preservation of DDL-relevant LengthValidator instances, and complete removal of configureValidation() from production ORM code.
```
