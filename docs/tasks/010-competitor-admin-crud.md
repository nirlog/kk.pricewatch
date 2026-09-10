# Task 010 — Competitor admin CRUD

## Goal

Add the first native 1C-Bitrix administrative UI for `kk.pricewatch`: a permission-aware competitor list and create/edit/delete workflow backed by the existing `CompetitorTable` ORM entity.

This task starts the admin layer only. It must not execute collectors or add product integration.

## Required context

Before implementation read:

- `AGENTS.md`
- `.agents/skills/implement-task/SKILL.md`
- `.agents/skills/code-review/SKILL.md`
- `docs/architecture/0001-collector-boundary.md`
- `docs/architecture/0002-competitor-entity.md`
- `docs/architecture/0003-product-competitor-link.md`
- `docs/tasks/003-competitor-orm.md`
- `docs/tasks/007-product-competitor-bitrix-compatibility-fixes.md`
- `docs/tasks/008-orm-validation-api-compatibility.md`
- `docs/tasks/009-product-competitor-physical-string-lengths.md`
- `lib/Model/CompetitorTable.php`
- `lib/Model/ProductCompetitorTable.php`
- `lib/Model/CollectorType.php`
- `lib/Model/CollectorOptions.php`
- `install/index.php`
- current module/admin/menu conventions supported by the target Bitrix core.

Use `$implement-task`.

Because previous source-only CI checks missed real Bitrix API incompatibilities, verify every Bitrix admin API/method/class used by this task against the actual supported Bitrix API/source. Do not invent fluent methods or assume an API exists because it looks plausible.

The existing `kk.quiz` module may be inspected as a UI/integration precedent, but do not copy its larger custom ACL subsystem into this module.

## Scope

Implement only:

1. native Bitrix admin menu entry `KK PriceWatch → Конкуренты`;
2. competitor list page;
3. competitor create/edit page;
4. safe competitor deletion;
5. standard module-level D/R/W permissions;
6. installation/removal of required admin entry-point files;
7. localization for visible admin strings;
8. focused tests/source guards possible without a Bitrix runtime;
9. manual real-Bitrix smoke-test documentation;
10. module version bump to `0.4.0`.

Do not implement:

- product edit integration;
- custom iblock properties;
- product-to-competitor admin UI;
- price checking;
- `PriceUpdateService`;
- collector execution;
- external HTTP collector;
- Python/Selenium integration;
- queue, agents or cron;
- history;
- notifications;
- repricing;
- dashboard/statistics;
- import/export;
- custom ACL tables or role systems;
- unrelated schema changes or migrations.

## 1. Standard module permissions

Use the standard Bitrix module permission model for this milestone.

Required access levels:

```text
D = access denied
R = read-only
W = full write access
```

The module installer must advertise module-group rights through the supported Bitrix mechanism, including `MODULE_GROUP_RIGHTS = 'Y'` and a valid `GetModuleRightList()` (or the exact equivalent required by the target Bitrix runtime).

Visible labels must be localized, e.g.:

```text
[D] Доступ закрыт
[R] Просмотр
[W] Полный доступ
```

For runtime checks use the actual Bitrix module-right API supported by the target core (for example the documented `CMain::GetUserRight()` path where applicable).

Rules:

- `D`: module menu is hidden and direct admin-page access is denied;
- `R`: competitor list and competitor details may be viewed, but no state-changing action is available or accepted;
- `W`: create, edit and delete are allowed;
- do not rely only on `$USER->IsAdmin()`;
- server-side checks are mandatory even if a button is hidden in the UI;
- do not implement a custom permissions table in this task.

If a small helper is introduced to centralize these checks, keep it generic to this module and simple enough to test where possible.

## 2. Administrative menu

Add a native Bitrix module admin menu definition using the supported module convention (`admin/menu.php` or exact target-core equivalent).

The user-facing hierarchy must be:

```text
KK PriceWatch
└── Конкуренты
```

The menu must:

- be visible only with at least `R` access;
- open the competitor list page;
- include the edit page in `more_url`/equivalent so menu highlighting remains correct;
- use localized text/title;
- not add collector, product, queue, history or settings menu items yet.

Prefer the normal module menu mechanism over registering a global event handler unless the target Bitrix runtime genuinely requires an event for the intended placement.

## 3. Admin entry points and install lifecycle

Admin pages must be reachable through normal `/bitrix/admin/...` entry points while implementation remains inside the module.

Use the standard Bitrix module install pattern for admin proxy/wrapper files. The wrapper resolution must be compatible with the module's current `/local/modules/kk.pricewatch/` installation and must not unnecessarily prevent a future `/bitrix/modules/kk.pricewatch/` installation.

Suggested public admin entry-point names:

```text
kk_pricewatch_competitors.php
kk_pricewatch_competitor_edit.php
```

Exact internal filenames may differ if there is a clear reason, but keep names deterministic and module-prefixed.

Installer requirements:

- installation copies/registers the required admin entry points idempotently;
- repeated installation does not duplicate or corrupt them;
- uninstall removes only admin entry-point files owned by `kk.pricewatch`;
- uninstall continues to preserve ORM tables/data;
- do not remove unrelated files from `/bitrix/admin`;
- failures must not be silently swallowed.

Do not add a schema migration in this task.

## 4. Competitor list

Create a native Bitrix admin list page backed by `CompetitorTable`.

Prefer standard Bitrix administrative UI primitives such as the supported `CAdminList`, `CAdminSorting`, filters/navigation, toolbar/context buttons, or their target-runtime equivalent. Legacy admin classes are acceptable here because this is a Bitrix administrative extension point.

Minimum columns:

```text
ID
Активен
Сортировка
Название
Домен
Тип коллектора
Обработчик
Изменён
```

`COLLECTOR_OPTIONS` does not need to be rendered in full in the list.

Default ordering:

```text
SORT ASC, NAME ASC, ID ASC
```

Allow sorting only through an explicit whitelist of safe ORM fields. Never pass arbitrary request values directly into ORM order expressions.

Minimum filters:

- name search;
- domain search;
- active (`Y`/`N`);
- collector type (`mock`/`external`).

The list must not load an unbounded full table into memory. Use normal Bitrix pagination/navigation.

UI behavior:

- `W` users see a `Добавить конкурента` action;
- clicking competitor name/ID opens edit/details;
- `R` users can open rows but do not see create/delete/write controls;
- all dynamic HTML values are escaped with the appropriate Bitrix escaping function;
- invalid filter/sort input is handled safely.

Bulk editing/deleting is deliberately out of scope for this first admin milestone.

## 5. Create/edit form

Create a native Bitrix admin form for a competitor.

Editable fields for `W`:

```text
NAME
ACTIVE
SORT
DOMAIN
COLLECTOR_TYPE
COLLECTOR_HANDLER
COLLECTOR_OPTIONS
```

Use Russian labels and useful concise field hints/tooltips.

Recommended UI controls:

- `NAME`: text, required;
- `ACTIVE`: checkbox;
- `SORT`: integer input;
- `DOMAIN`: text;
- `COLLECTOR_TYPE`: select containing only values exposed by `CollectorType` (`mock`, `external`);
- `COLLECTOR_HANDLER`: text;
- `COLLECTOR_OPTIONS`: multiline textarea for a JSON object.

On an existing competitor also show useful read-only metadata such as ID, `CREATED_AT` and `UPDATED_AT`.

Use `CompetitorTable::add()` / `update()` as the persistence boundary so existing ORM validation remains authoritative.

Do not duplicate the complete validation contract in the admin page. Admin-specific input normalization may prepare values, but the ORM must still reject invalid data.

### Collector options

`COLLECTOR_OPTIONS` must remain a JSON object, never PHP serialization.

Validate it through the existing `CollectorOptions` helper before persistence and surface a clear localized form error. It is acceptable to normalize valid input through the existing `decode()` + `encode()` helpers before storing it.

Do not add competitor-specific options or branches.

### Form behavior

For `W` access support:

- Save;
- Apply;
- Cancel/back to list.

For `R` access render the existing row in read-only mode and do not accept POST mutations.

If a requested edit ID does not exist, return a clear admin error instead of silently switching to create mode.

## 6. CSRF/session safety

Every state-changing admin action must:

1. require `W` module permission;
2. require the appropriate HTTP method/state-changing form flow;
3. validate the Bitrix session token using the supported `check_bitrix_sessid()`/equivalent mechanism before ORM mutation.

Do not mutate on an unprotected plain GET request.

After successful POST use a redirect/PRG-style flow where practical to prevent accidental resubmission on refresh.

## 7. Safe deletion and referential integrity

Deletion is in scope, but `CompetitorTable` currently has no physical cascading foreign key to product links.

Therefore deletion must not create orphaned rows in `b_kk_pricewatch_product_competitor`.

Before deleting a competitor, check whether any `ProductCompetitorTable` row references its ID.

Rules:

- if referenced by at least one product link: block deletion and show a localized error explaining that the competitor is used by product links and should be deactivated or links removed later;
- if unreferenced: allow deletion for `W` users after CSRF/session validation and explicit confirmation;
- never cascade-delete product links in this task;
- do not silently deactivate when the user requested delete.

A delete action may live on the edit page for this first milestone; a list-row delete action is optional only if it can be implemented with the same POST/session/write-permission guarantees.

## 8. Error handling

Admin pages must display ORM and validation errors in a native, readable Bitrix admin form/list style.

Do not show raw stack traces, SQL, internal file paths, secrets, or arbitrary exception internals to normal admin users.

Unexpected failures may be logged through an appropriate existing Bitrix mechanism if needed, but do not add a logging subsystem in this task.

## 9. Localization

Do not hard-code the main visible admin strings in business/admin page logic.

Add normal module language files for the new menu/list/form/install permission labels.

Russian localization is required. Add English equivalents as well where the repository's current localization structure supports them cleanly.

## 10. Version

This is a user-visible feature milestone. Bump module version:

```text
0.3.0 → 0.4.0
```

Keep the version date valid for the current project date.

No Marketplace updater package is required yet.

## 11. Automated checks without Bitrix runtime

GitHub Actions still does not contain Bitrix core. Do not create fake implementations of `CAdminList`, `CMain`, ORM, or other Bitrix runtime classes just to make integration tests green.

Add useful lightweight tests/source guards where they genuinely protect the contract. At minimum cover or assert as practical:

- module declares D/R/W rights rather than admin-only access;
- state-changing handlers contain both write-access and session-token checks;
- competitor persistence uses `CompetitorTable` rather than raw SQL;
- deletion checks `ProductCompetitorTable` references before delete;
- menu/list/edit public entry-point names remain wired consistently;
- visible output paths use escaping where dynamic competitor data is rendered;
- `composer.lock` is preserved.

Pure PHP helper logic introduced by this task should receive normal PHPUnit tests.

Source guards are only regression checks and must not be presented as proof that Bitrix admin integration works.

Existing tests, Composer validation and PHP syntax checks must remain green.

## 12. Manual real-Bitrix smoke test

Document and perform where the environment is available:

1. update/install `kk.pricewatch` on the real Bitrix dev installation;
2. confirm module remains installable with existing ORM tables/data;
3. confirm `KK PriceWatch → Конкуренты` appears for a user with `R`/`W` rights;
4. confirm a `D` user cannot see the menu and cannot open list/edit URLs directly;
5. confirm an `R` user can list/view but cannot create, save or delete even by crafted POST;
6. confirm a `W` user can create a competitor with defaults and valid JSON options;
7. edit all supported fields and confirm persistence plus `UPDATED_AT` change;
8. submit invalid `NAME`, `COLLECTOR_TYPE`, overly long handler, and invalid JSON and confirm readable errors without data corruption;
9. confirm output safely handles characters such as `<`, `>`, `&`, quotes in allowed text fields;
10. delete an unreferenced competitor successfully with confirmation;
11. create a product-competitor relation and confirm deletion of that competitor is blocked;
12. reinstall/re-run module installation and confirm admin entry points remain usable and ORM data is preserved;
13. uninstall and confirm module-owned admin proxy files are removed while ORM data tables remain preserved.

Pay particular attention to real runtime errors such as undefined Bitrix methods/classes. Any such error is a blocker even if CI is green.

## Acceptance criteria

Task is complete when:

- native Bitrix competitor list and create/edit screens work on the real dev installation;
- menu hierarchy is `KK PriceWatch → Конкуренты`;
- standard module rights D/R/W are declared and enforced server-side;
- `D` cannot access, `R` is read-only, `W` can mutate;
- all mutations validate the Bitrix session token;
- list has pagination, safe whitelisted sorting and required filters;
- competitor fields are persisted only through `CompetitorTable`;
- invalid ORM/JSON input is shown as a readable admin error;
- dynamic competitor values are escaped in HTML;
- referenced competitors cannot be deleted and no product-link orphans are created;
- admin entry points are installed idempotently and removed safely on uninstall;
- ORM data remains preserved on normal uninstall;
- module version is `0.4.0`;
- no collector execution/product UI/queue/history scope is added;
- Composer/syntax/PHPUnit CI is green;
- real Bitrix smoke test is documented, and any available runtime verification results are reported honestly.

## Codex invocation

```text
$implement-task
Implement docs/tasks/010-competitor-admin-crud.md.
Do not implement anything outside the task scope.
```

After implementation:

```text
$code-review
Review docs/tasks/010-competitor-admin-crud.md against AGENTS.md, ADR-0002 and ADR-0003.
Pay particular attention to actual Bitrix admin API compatibility, D/R/W permission enforcement, CSRF/session checks, HTML escaping, safe deletion with existing ProductCompetitor references, install/uninstall admin entry points, pagination/sort whitelisting, and preservation of ORM data.
```
