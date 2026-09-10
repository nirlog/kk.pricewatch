# Task 009 — ProductCompetitor physical string lengths

## Goal

Correct the physical database column lengths generated for `CURRENCY` and `STATUS` in `ProductCompetitorTable` so the real Bitrix/MySQL schema matches the intended ORM contract.

This is a narrow schema-correction task discovered by real installation smoke testing. Do not add new business functionality.

## Required context

Before implementation read:

- `AGENTS.md`
- `docs/architecture/0003-product-competitor-link.md`
- `docs/tasks/006-product-competitor-orm.md`
- `docs/tasks/007-product-competitor-bitrix-compatibility-fixes.md`
- `docs/tasks/008-orm-validation-api-compatibility.md`
- `.agents/skills/code-review/SKILL.md`
- `lib/Model/ProductCompetitorTable.php`
- `lib/Model/CompetitorTable.php`
- current tests and CI workflow

Use `$implement-task`.

## Real Bitrix/MySQL finding

A real clean module installation produced:

```sql
CURRENCY varchar(255) DEFAULT NULL,
STATUS varchar(255) NOT NULL
```

although the ORM declares:

```php
->configureSize(3)
```

for `CURRENCY` and:

```php
->configureSize(16)
```

for `STATUS`.

The same real environment confirmed that `COLLECTOR_HANDLER` is correctly generated as `varchar(512)` when a matching `LengthValidator` is present.

The correction must therefore ensure that D7 ORM metadata contains DDL-relevant length validators for both fields.

## Scope

Implement only:

1. Make `CURRENCY` physically generate as `varchar(3)` on a clean Bitrix/MySQL install.
2. Make `STATUS` physically generate as `varchar(16)` on a clean Bitrix/MySQL install.
3. Preserve the existing application validation semantics for both fields.
4. Add lightweight source/metadata regression checks that can run without Bitrix runtime.
5. Update developer/manual verification documentation if needed.
6. Keep module version at `0.3.0` unless a concrete technical requirement mandates otherwise.

Do not implement:

- ALTER/migration logic for already-created tables;
- updater packages;
- admin UI;
- product edit integration;
- collectors;
- queue/agents/cron;
- price update orchestration;
- history;
- notifications;
- unrelated refactoring.

## Required ORM correction

### CURRENCY

Keep the current nullable field and uppercase 3-letter currency validation.

In addition, add an explicit DDL-relevant validator equivalent to:

```php
new LengthValidator(3, 3)
```

or another Bitrix-supported validator configuration that produces the same application and physical schema contract.

The intended declaration is equivalent in responsibility to:

```php
(new StringField('CURRENCY'))
    ->configureNullable()
    ->configureSize(3)
    ->addValidator(new LengthValidator(3, 3))
    ->addValidator(/* existing uppercase currency validation */)
```

Do not hardcode `RUB` as a default.

### STATUS

Keep:

- required field;
- default `CollectionStatus::NEW`;
- allowed values only `new`, `success`, `error`.

Add an explicit DDL-relevant maximum length validator equivalent to:

```php
new LengthValidator(null, 16)
```

The intended declaration is equivalent in responsibility to:

```php
(new StringField('STATUS'))
    ->configureRequired()
    ->configureSize(16)
    ->configureDefaultValue(CollectionStatus::NEW)
    ->addValidator(new LengthValidator(null, 16))
    ->addValidator(/* existing status validation */)
```

Do not replace the field with a database enum.

## Preserve existing behavior

Do not change:

- exact URL preservation;
- `URL_HASH` derivation;
- `CURRENT_PRICE` precision `DECIMAL(18,2)` semantics;
- status values;
- stale-price semantics;
- timestamps;
- indexes;
- uninstall data-preservation behavior;
- competitor table schema.

## Existing installations

Do not add automatic ALTER statements in this task.

The current installer intentionally does not mutate existing table columns when the table already exists.

For development verification, after merging this task it is acceptable to remove the existing test tables and reinstall the module from a clean schema.

Production-safe column migration will be addressed only when the project introduces an explicit update/migration mechanism.

Document this limitation clearly; do not claim an existing `varchar(255)` installation will be changed automatically.

## Tests

Current CI does not include a Bitrix runtime. Do not add fake D7 implementations.

Add or strengthen lightweight source-level regression tests to ensure at minimum:

1. `CURRENCY` has `configureSize(3)` and `new LengthValidator(3, 3)` in its declaration;
2. `STATUS` has `configureSize(16)` and `new LengthValidator(null, 16)` in its declaration;
3. existing currency/status semantic validators remain present;
4. existing ProductCompetitor metadata guards still pass.

Source-level tests are regression guards only and must not claim to prove physical DDL.

All existing PHPUnit tests must remain green.

## Manual real-Bitrix verification

After merging, verify on the same Bitrix/MySQL development environment using a clean recreation of the module-owned test tables.

At minimum:

1. uninstall the module if needed;
2. remove the preserved development tables only when safe for this test environment;
3. install the module again;
4. run:

```sql
SHOW CREATE TABLE b_kk_pricewatch_product_competitor;
```

5. confirm:

```sql
CURRENCY varchar(3) DEFAULT NULL
STATUS varchar(16) NOT NULL
```

6. reconfirm:

```sql
CURRENT_PRICE decimal(18,2)
URL text
URL_HASH varchar(64)
```

7. confirm the existing unique and competitor lookup indexes remain correct;
8. confirm module installation completes without errors.

## CI

GitHub Actions must remain green:

- dependencies install from committed `composer.lock`;
- `composer validate` succeeds;
- PHP syntax checks succeed;
- PHPUnit succeeds.

Do not modify `composer.json` dependency constraints or `composer.lock` unless strictly required by an unrelated infrastructure issue; such changes are outside this task.

## Acceptance criteria

Task is complete when:

- `CURRENCY` ORM metadata includes DDL-relevant exact length 3 validation;
- `STATUS` ORM metadata includes DDL-relevant maximum length 16 validation;
- existing semantic validation remains intact;
- no automatic migration/ALTER logic is introduced;
- no unrelated business functionality is changed;
- tests are green in GitHub Actions;
- manual verification documentation states that a clean real Bitrix/MySQL install must produce `varchar(3)` and `varchar(16)` respectively.

## Codex invocation

```text
$implement-task
Implement docs/tasks/009-product-competitor-physical-string-lengths.md.
Do not implement anything outside the task scope.
```

After implementation:

```text
$code-review
Review docs/tasks/009-product-competitor-physical-string-lengths.md against AGENTS.md and ADR-0003.
Pay particular attention to real DDL implications of StringField validators, preservation of currency/status semantics, scope discipline, and the absence of automatic migration logic.
```
