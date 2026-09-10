# Task 004 — ORM schema corrections

## Goal

Correct the physical database schema behavior introduced in Task 003 before any product-link or admin functionality is built on top of it.

This is a narrow corrective task. Do not add new business features.

## Required context

Before implementation read:

- `AGENTS.md`
- `docs/architecture/0002-competitor-entity.md`
- `docs/tasks/003-competitor-orm.md`
- `lib/Model/CompetitorTable.php`
- `lib/Installer/SchemaInstaller.php`
- current CI workflow

Use `$implement-task`.

## Scope

Implement only:

1. Ensure `COLLECTOR_HANDLER` is physically created with capacity for 512 characters under Bitrix D7 ORM table creation.
2. Add the corresponding ORM validation so application-level maximum length matches the physical column contract.
3. Add `composer.lock` for reproducible CI dependency resolution.
4. Add or adjust automated checks where useful without introducing a fake Bitrix runtime.
5. Add a concise manual Bitrix verification note for the actual generated column type/length.

Do not implement:

- product ↔ competitor links;
- admin UI;
- queue;
- agents/cron;
- HTTP collector;
- parsing;
- Python/Selenium integration;
- new competitor fields;
- schema migration framework;
- uninstall UI;
- unrelated refactoring.

## COLLECTOR_HANDLER correction

Task 003 requires:

```text
COLLECTOR_HANDLER
- optional string
- maximum 512 characters
```

The current field declaration uses `configureSize(512)` but does not include a matching `LengthValidator`.

Under Bitrix D7 schema generation, field metadata used for application normalization and metadata used by the SQL helper for generated varchar length are not necessarily the same thing. The implementation must ensure the actual generated table column satisfies the 512-character contract.

Update the ORM definition so both:

- application validation rejects values longer than 512 characters;
- `createDbTable()` generates a varchar capacity of 512 characters on the supported Bitrix/MySQL setup.

Prefer the normal Bitrix D7 field/validator mechanism. Do not replace the whole table creation with vendor-specific raw SQL merely to solve this field length.

At minimum the field should include an appropriate `LengthValidator(null, 512)` in addition to any existing size configuration required by Bitrix.

## Existing installations

This project is still in early development and Task 004 is a corrective task before production migration support exists.

Do not invent a migration subsystem in this task.

Document the following explicitly:

- a clean install after this correction must create the correct 512-character column;
- if a local development installation already created the previous 255-character column, the developer should uninstall/delete the development table as appropriate and reinstall for verification, since normal module uninstall intentionally preserves data;
- production-safe ALTER/migration handling will be introduced only when the project actually requires upgrade packages.

Do not silently drop or alter existing tables during ordinary module load/install.

## Composer lock

Generate and commit `composer.lock` from the current `composer.json` without changing dependency constraints unless absolutely required to produce a valid lock file.

CI must then install dependencies from the lock file.

No new dependencies are part of this task.

## Automated tests/checks

Do not build fragile stubs of Bitrix ORM internals solely to simulate generated SQL.

Where practical, add a lightweight test or source-level assertion that protects the intended ORM metadata, for example ensuring the field validation includes a 512-character limit. If this cannot be tested cleanly without Bitrix runtime, document the limitation and rely on the manual integration check.

All existing tests must continue to pass.

## Manual Bitrix verification

Update the relevant documentation with a short verification procedure on a real Bitrix installation:

1. install/reinstall the module against a clean competitor table;
2. inspect `b_kk_pricewatch_competitor`;
3. confirm `COLLECTOR_HANDLER` is physically `varchar(512)` or the DB-engine equivalent with at least 512-character capacity;
4. add/update a competitor with a handler longer than 255 but no longer than 512 characters and confirm it is stored intact;
5. verify a value longer than 512 characters is rejected by ORM validation;
6. confirm existing Task 003 defaults/timestamps still work.

Do not add a production debug page for this verification.

## Versioning

This correction does not need a new public module version yet unless the implementation requires it for a concrete Bitrix mechanism. Prefer keeping `0.2.0` until the next functional milestone.

## CI

The existing GitHub Actions workflow must remain green.

Verify:

- Composer installs from committed `composer.lock`;
- `composer validate` succeeds;
- PHP syntax checks succeed;
- PHPUnit succeeds.

## Acceptance criteria

Task is complete when:

- `COLLECTOR_HANDLER` has a 512-character application validation limit;
- clean Bitrix table creation produces a column capable of storing 512 characters;
- no competitor-specific schema is introduced;
- `composer.lock` is committed;
- dependency constraints are unchanged unless technically necessary;
- existing tests remain green;
- CI is green;
- manual Bitrix verification steps are documented;
- no new business functionality is added.

## Codex invocation

```text
$implement-task
Implement docs/tasks/004-orm-schema-corrections.md.
Do not implement anything outside the task scope.
```

After implementation:

```text
$code-review
Review docs/tasks/004-orm-schema-corrections.md against AGENTS.md and ADR-0002.
Verify the physical DDL implications of the D7 field metadata, not only the PHP declaration.
```
