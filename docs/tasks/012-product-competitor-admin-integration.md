# Task 012 — Product competitor admin integration

## Goal

Add the first product-facing admin integration for `kk.pricewatch`.

A Bitrix catalog product or SKU/offer must be able to show and manage its monitored competitor links while `ProductCompetitorTable` remains the only persistent source of truth.

This task must provide:

1. a `Мониторинг цен` summary tab/block in the existing Bitrix product/offer edit UI;
2. module-owned admin pages for listing, creating, editing and deleting product ↔ competitor links for one product element;
3. safe lifecycle registration/unregistration of the Bitrix admin-tab event and admin proxies;
4. preservation of exact competitor URLs;
5. read-only display of collection state fields;
6. no collector execution yet.

This is a UI/integration milestone only. Do not implement price collection, scheduling, queueing or external HTTP/Python integration.

## Required context

Before implementation read:

- `AGENTS.md`
- `.agents/skills/implement-task/SKILL.md`
- `.agents/skills/code-review/SKILL.md`
- `docs/architecture/0003-product-competitor-link.md`
- `docs/tasks/006-product-competitor-orm.md`
- `docs/tasks/010-competitor-admin-crud.md`
- `docs/tasks/011-competitor-admin-lifecycle-pagination-fixes.md`
- `lib/Model/ProductCompetitorTable.php`
- `lib/Model/ProductUrl.php`
- `lib/Model/CompetitorTable.php`
- `lib/Admin/Access.php`
- current target Bitrix source for:
  - `OnAdminTabControlBegin`;
  - `CAdminTabControl` / tab `CONTENT` behavior;
  - product and SKU edit admin pages;
  - event registration/unregistration APIs;
  - `CAdminUiList` navigation;
  - the D7 iblock/catalog product lookup APIs selected for product validation.

Use `$implement-task`.

Do not infer Bitrix admin/event API signatures from names. Verify the exact APIs against the supported Bitrix source before implementation.

## Architectural decision for this task

`ProductCompetitorTable` is the canonical storage.

Do **not** create an iblock property that duplicates competitor IDs, URLs, price or status.

The product edit integration is a convenience UI only:

```text
Bitrix product / offer edit page
        |
        | OnAdminTabControlBegin
        v
"Мониторинг цен" summary tab
        |
        | module-owned admin URL
        v
Product competitor link CRUD
        |
        v
ProductCompetitorTable
```

The summary tab must not intercept or alter the native Bitrix product save process.

Do not put a nested HTML `<form>` inside the native product edit form. Mutations belong on module-owned admin pages.

## Scope

Implement only:

- product/offer admin-tab integration;
- product competitor link list page;
- create/edit/delete UI for one link;
- validation needed for that UI;
- exact-URL handling;
- identity-change state reset described below;
- module/event/admin-proxy lifecycle changes;
- localization;
- focused automated/source tests;
- real-Bitrix smoke-test documentation;
- version bump to `0.5.0`.

Do not implement:

- automatic or manual collector execution;
- `PriceUpdateService`;
- HTTP collector;
- Python collector;
- agent/cron/queue/retries;
- price history;
- dashboard/statistics;
- public product comparison block;
- automatic repricing;
- bulk link editing/deleting;
- custom iblock property as storage;
- competitor-specific logic;
- schema changes;
- new database tables;
- migrations unrelated to this UI;
- unrelated refactoring.

## 1. Product / offer edit integration

Register a persistent handler for Bitrix `main` event:

```text
OnAdminTabControlBegin
```

The target Bitrix core currently dispatches this event with the admin tab control object by reference. Verify the exact registration and handler signatures before coding.

### Where the tab appears

The handler must appear only on supported existing catalog product / SKU edit forms.

Verify the actual target admin scripts used by the supported Bitrix installation. Candidate scripts commonly include:

```text
/bitrix/admin/iblock_element_edit.php
/bitrix/admin/cat_product_edit.php
```

Do not blindly hardcode candidate names without verifying the target runtime/source.

Requirements:

- existing element only: numeric `ID > 0`;
- the element must actually exist;
- the integration must work for both normal catalog products and SKU/offer elements when the edited object is represented by a catalog/iblock element;
- if the current page is unrelated, do nothing;
- if `kk.pricewatch` cannot be loaded, do nothing safely;
- users with module right `D` must not see the tab;
- users with `R` may see the summary but no mutation controls;
- users with `W` may see management/add actions.

Do not use `$USER->IsAdmin()` as the module permission model.

### Tab rendering

Use a supported Bitrix admin-tab extension mechanism. In the supported core, `CAdminTabControl` supports tab entries with `CONTENT`; if that is still true in the target runtime, using `CONTENT` is acceptable.

Suggested label:

```text
Мониторинг цен
```

The tab is a compact summary, not a full CRUD editor.

Display existing `ProductCompetitorTable` rows for the current `PRODUCT_ID` with at least:

- competitor name;
- link `ACTIVE` state;
- exact competitor URL;
- current price + currency when present;
- current status (`new`, `success`, `error`);
- last check time;
- last success time.

For users with `W`, provide a clear action such as:

```text
Управлять ссылками конкурентов
```

and/or:

```text
Добавить ссылку
```

pointing to the module-owned pages described below.

For users with `R`, a read-only management/list link is acceptable, but create/edit/delete controls must not be rendered.

### Empty state

When the product has no monitored competitor links, show a concise localized empty state instead of an empty table.

### Output safety

All competitor names, URLs, statuses and other values are untrusted output and must be escaped.

A URL may be rendered as an external clickable link only if the UI has established that it is an accepted HTTP/HTTPS URL. Use safe escaping and `target="_blank"` with an appropriate `rel` attribute. Otherwise render it as text.

Never output `URL_HASH` as a user-facing canonical value.

## 2. Module-owned product link list page

Add a module-owned admin page conceptually similar to:

```text
/bitrix/admin/kk_pricewatch_product_competitors.php?PRODUCT_ID=123
```

Use install/admin proxy files with the same `/local/modules/...` then `/bitrix/modules/...` fallback pattern already used by Task 010.

The page must:

- require `Access::canRead()`;
- require a positive `PRODUCT_ID`;
- verify the referenced iblock element exists through a supported Bitrix API;
- show the product ID and product name/context;
- query only rows for that `PRODUCT_ID`;
- use deterministic ordering;
- use `CAdminUiList` if standard Bitrix list UI is used;
- use true DB-side pagination (`count`, finite `limit`, calculated `offset`) if pagination is present;
- escape all output;
- preserve module `D/R/W` behavior.

Suggested columns:

- ID;
- competitor;
- active;
- exact URL;
- current price;
- currency;
- status;
- last check;
- last success;
- updated at.

Operational fields are read-only here.

For `W`, add actions to create and edit links. Delete may be exposed from the edit page rather than a GET row action.

Do not implement state-changing GET requests.

## 3. Create/edit link page

Add a module-owned admin page conceptually similar to:

```text
/bitrix/admin/kk_pricewatch_product_competitor_edit.php?PRODUCT_ID=123
/bitrix/admin/kk_pricewatch_product_competitor_edit.php?PRODUCT_ID=123&ID=456
```

### Permissions and CSRF

- `R`: may view existing link details read-only if the page supports view mode;
- `W`: required for create, update and delete;
- all POST mutations require `check_bitrix_sessid()`;
- use PRG redirect after successful mutation;
- do not rely on UI-hidden buttons as authorization.

### Editable fields

Only these link configuration fields are editable in this task:

- `COMPETITOR_ID`;
- `URL`;
- `ACTIVE`.

Do not expose these as editable:

- `URL_HASH`;
- `CURRENT_PRICE`;
- `CURRENCY`;
- `STATUS`;
- `ERROR_CODE`;
- `ERROR_MESSAGE`;
- `LAST_CHECK_AT`;
- `LAST_SUCCESS_AT`;
- timestamps.

### Competitor selection

The selected competitor must exist.

For new links, prefer showing active competitors in the selector.

Existing links to a competitor that later became inactive must remain visible/readable and must not become impossible to inspect. If editing such a row, preserve a sensible way to display the currently selected inactive competitor.

Do not silently change or delete existing links merely because a competitor is inactive.

## 4. Exact URL requirements

The URL is configuration identity and must remain exact.

When the admin submits an accepted URL:

- do not lowercase it;
- do not reorder query parameters;
- do not decode/re-encode it;
- do not remove fragments;
- do not strip query parameters;
- do not trim the stored value;
- do not convert it through a URL builder that rewrites it.

Validation may use a trimmed copy only to decide whether the input is blank.

The value passed into `ProductCompetitorTable` must be the original submitted string.

`URL_HASH` continues to be derived by `ProductCompetitorTable`; the admin UI must never accept a caller-supplied hash.

### URL scheme

Because this UI creates collector targets and may render clickable links, require an absolute HTTP or HTTPS URL for links created/edited through this admin UI.

Validate without normalizing the accepted URL.

Reject unsafe/non-network schemes such as:

```text
javascript:
data:
file:
```

Do not introduce SSRF fetching defenses here because this task performs no network request, but keep the URL treated as untrusted data for the future collector boundary.

## 5. Identity changes and stale collection state

`PRODUCT_ID + COMPETITOR_ID + exact URL` defines the monitored identity.

Changing only `ACTIVE` does not change identity and must preserve collection state.

Changing `COMPETITOR_ID` or `URL` creates a new monitoring identity in-place. The old collected price/status must not be presented as if it belonged to the new identity.

Therefore, on an admin update where either `COMPETITOR_ID` or the exact `URL` changes, atomically reset operational state to:

```text
CURRENT_PRICE   = NULL
CURRENCY        = NULL
STATUS          = new
ERROR_CODE      = NULL
ERROR_MESSAGE   = NULL
LAST_CHECK_AT   = NULL
LAST_SUCCESS_AT = NULL
```

`URL_HASH` must be regenerated by the existing ORM event from the new exact URL.

Do not reset operational state when only `ACTIVE` changes.

Prefer placing this admin/business rule in a small module service rather than duplicating update logic in page scripts. A service such as `ProductCompetitorLinkService` is acceptable if kept narrowly scoped to link CRUD/validation.

This is not `PriceUpdateService` and must not execute collectors.

## 6. Duplicate identity handling

The physical unique identity remains:

```text
PRODUCT_ID + COMPETITOR_ID + URL_HASH
```

Create/update UI must return a readable validation error when the requested exact identity already exists for the product.

Do not weaken or remove the unique DB index.

It is acceptable to perform a friendly pre-check using the exact URL hash, but the DB uniqueness constraint remains authoritative for races.

Do not swallow database errors or convert all SQL failures into “duplicate” unless the failure is actually identified as that condition.

## 7. Product existence / context validation

`PRODUCT_ID` is a Bitrix iblock element ID.

Use a supported D7 Bitrix lookup where practical, for example `Bitrix\Iblock\ElementTable` after verifying the API in the target runtime.

At minimum:

- reject a nonexistent element ID;
- do not create links to `PRODUCT_ID <= 0`;
- display enough product context (name and ID) for an administrator to know which object is being edited.

If the supported product edit integration needs to distinguish catalog elements from arbitrary iblock elements, verify the proper `catalog` API (for example `Bitrix\Catalog\ProductTable`) before implementing that restriction. Do not invent a catalog check.

The ORM table itself remains generic to an iblock element ID as decided in ADR-0003.

## 8. Event/install/uninstall lifecycle

Add persistent event registration during module installation for the chosen product-edit tab handler.

Requirements:

- registration is idempotent for repeated install/update flows;
- do not create duplicate event handlers;
- uninstall unregisters the module-owned event handler;
- normal uninstall still preserves ORM tables and rows;
- admin proxy cleanup remains safe and must not reintroduce the `DeleteDirFiles()` boolean bug fixed in Task 011;
- uninstall must remove only module-owned admin proxy files;
- reinstall restores event integration and proxies without changing existing ORM rows.

Verify the actual Bitrix event registration/unregistration methods and signatures in the target core.

Do not add speculative compatibility wrappers.

## 9. Existing competitor deletion behavior

Task 010 blocks deletion of a competitor referenced by `ProductCompetitorTable`.

Do not regress that behavior.

A product-link delete removes only the selected `ProductCompetitorTable` row. It must never delete the competitor itself and must never delete the Bitrix product/offer.

## 10. Localization

Provide at least Russian and English language messages consistent with the module's current structure.

User-facing validation errors must be understandable and must not expose raw SQL, stack traces or internal file paths.

Machine state values may remain stable internal codes, but labels around them should be localized where practical.

## 11. Tests and source guards

Add focused tests that are meaningful even without a full Bitrix runtime.

At minimum guard as practical:

### Integration / lifecycle

- module registers `main:OnAdminTabControlBegin` using the verified API;
- uninstall unregisters the same handler;
- new admin proxy files are installed and removed as module-owned proxies;
- existing Task 011 safe cleanup pattern remains intact;
- module version is `0.5.0`.

### Product tab

- handler checks module read permission;
- handler does not run on unrelated admin pages;
- handler requires an existing positive element ID;
- the tab content contains no mutation form nested into the product form;
- operational state is rendered read-only.

### Link mutations

- write permission and `check_bitrix_sessid()` are present on POST mutations;
- URL is not normalized before persistence;
- `URL_HASH` is not accepted as an editable request field;
- URL/competitor identity change resets operational state;
- ACTIVE-only change does not intentionally clear collection state;
- duplicate identity handling is covered;
- product existence and competitor existence are validated.

### Security

- unsafe URL schemes are rejected by the admin create/edit validation;
- rendered values are escaped;
- state-changing GET actions are absent.

Source-level tests are regression guards only. Do not claim they prove real Bitrix admin/runtime behavior.

Run:

- `composer validate`;
- PHP syntax checks;
- PHPUnit;
- `git diff --check`.

Keep `composer.lock` unchanged unless there is an explicit dependency reason, which this task does not require.

## 12. Manual real-Bitrix smoke test

Add/update a checklist and run it after merge on the real development installation.

At minimum verify:

1. install/update completes without undefined method/class errors;
2. an existing catalog product edit page shows `Мониторинг цен` for a user with `R`/`W`;
3. an SKU/offer edit page also works where supported;
4. unrelated admin pages do not receive the tab;
5. `D` cannot see/access module product-link pages;
6. `R` can view but cannot create/update/delete, including crafted POST attempts;
7. `W` creates a link with an exact URL containing meaningful query parameters;
8. database `URL` exactly matches submitted input and `URL_HASH` matches SHA-256 of that exact string;
9. duplicate exact identity is rejected cleanly;
10. editing only `ACTIVE` preserves current operational fields;
11. editing URL or competitor resets operational fields to `new`/NULL as specified;
12. unsafe URL schemes are rejected;
13. deletion removes only the selected link;
14. product summary tab reflects create/edit/delete without stale values after page reload;
15. uninstall removes new admin proxies and event registration, unregisters the module, and preserves both ORM tables/data;
16. reinstall restores the UI integration and existing links remain intact.

Record Bitrix version, PHP version, DB engine, date and result. Any undefined Bitrix admin/event API is a blocker and must be reported rather than patched speculatively.

## 13. Version

Bump:

```text
0.4.0 -> 0.5.0
```

This is the first product-facing admin integration milestone.

Use a current version date matching the implementation commit/date.

## Acceptance criteria

Task is complete when:

- existing Bitrix products/offers expose a localized PriceWatch summary tab through a verified admin extension point;
- the native product save flow is not hijacked;
- `ProductCompetitorTable` remains canonical storage;
- administrators with `W` can create/edit/delete competitor links for an existing product;
- `R` is read-only and `D` is denied;
- every mutation is CSRF-protected and POST-only;
- exact URLs, including query parameters/order/encoding, are preserved;
- unsafe URL schemes are rejected by this admin UI;
- `URL_HASH` remains derived only;
- operational collection fields are read-only;
- changing URL or competitor resets stale collection state while ACTIVE-only edits preserve it;
- duplicate link identity is rejected without weakening the DB constraint;
- install/uninstall event and proxy lifecycle is idempotent and preserves data;
- version is `0.5.0`;
- CI is green;
- real Bitrix product/offer admin smoke test is completed after deployment.

## Codex invocation

```text
$implement-task
Implement docs/tasks/012-product-competitor-admin-integration.md.
Do not implement anything outside the task scope.
```

After implementation:

```text
$code-review
Review docs/tasks/012-product-competitor-admin-integration.md against AGENTS.md and ADR-0003.
Pay particular attention to the real Bitrix OnAdminTabControlBegin API, event install/uninstall lifecycle, module permissions/CSRF, exact URL preservation, duplicate identity, operational-state reset on identity changes, and avoiding nested forms or product-save interception.
```
