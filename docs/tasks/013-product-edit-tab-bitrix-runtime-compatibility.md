# Task 013 — Product edit tab Bitrix runtime compatibility fixes

## Goal

Correct the real-Bitrix runtime incompatibilities introduced by Task 012 / PR #12 in the product/offer edit integration.

This is a narrow corrective task for the existing `0.5.0` milestone. Do not add new product features, collector execution, schema changes, queueing, history, public UI, or unrelated refactoring.

The fixes must ensure that:

1. the `Мониторинг цен` tab can be added to the supported Bitrix product / SKU edit form without a fatal error;
2. the tab `CONTENT` uses markup compatible with how `CAdminTabControl` renders `CONTENT` inside its existing edit-table `<tbody>`;
3. the product-edit integration and module-owned product-link admin pages are limited to actual catalog products/offers, not arbitrary iblock elements;
4. existing Task 012 CRUD, exact-URL, permission, identity-reset and lifecycle behavior remains unchanged;
5. the module version remains `0.5.0`.

## Required context

Before implementation read:

- `AGENTS.md`
- `.agents/skills/implement-task/SKILL.md`
- `.agents/skills/code-review/SKILL.md`
- `docs/architecture/0003-product-competitor-link.md`
- `docs/tasks/012-product-competitor-admin-integration.md`
- `lib/Admin/ProductEditTabHandler.php`
- `lib/Admin/ProductContext.php`
- `lib/Admin/ProductCompetitorLinkService.php`
- `admin/product_competitors.php`
- `admin/product_competitor_edit.php`
- `install/index.php`
- current supported Bitrix core source for:
  - `CAdminTabControl::OnAdminTabControlBegin()`;
  - `CAdminTabControl::AddTabs()`;
  - `CAdminTabControl::BeginNextTab()` and the `CONTENT` branch;
  - `Bitrix\Catalog\ProductTable`;
  - the product / offer edit scripts used by the target installation.

Use `$implement-task`.

Do not infer Bitrix API contracts from method names. Verify them against the supported core before coding.

## Confirmed runtime problem 1 — wrong `AddTabs()` API

Task 012 currently does conceptually:

```php
$tabControl->AddTabs([[
    'DIV' => 'kk_pricewatch_product_competitors',
    'TAB' => '...',
    'TITLE' => '...',
    'CONTENT' => '...',
]]);
```

This is incorrect for the supported Bitrix core.

`CAdminTabControl::AddTabs()` expects a `CAdminTabEngine`-style object by reference and calls `GetTabs()` on it. Passing an array can cause a runtime fatal such as:

```text
Call to a member function GetTabs() on array
```

For this one module-owned summary tab, do **not** introduce a custom `CAdminTabEngine` unless the verified target runtime requires it.

The event already receives the live `CAdminTabControl` object by reference. Use the simplest verified compatible mechanism, expected to be direct append to its public legacy `tabs` collection, for example conceptually:

```php
$tabControl->tabs[] = [
    'DIV' => 'kk_pricewatch_product_competitors',
    'TAB' => ...,
    'TITLE' => ...,
    'CONTENT' => ...,
];
```

Verify the actual visibility/shape of `tabs` in the supported core before implementation.

Requirements:

- remove the incorrect `AddTabs(array)` call;
- do not mutate unrelated existing tabs;
- add exactly one module tab per event execution;
- preserve the current `D/R/W` visibility behavior;
- preserve the existing page/script guards;
- avoid duplicate module tab insertion defensively if the handler can be reached more than once in an unusual admin flow.

A small private helper that checks existing tab `DIV` values before appending is acceptable.

## Confirmed runtime problem 2 — invalid `CONTENT` shape

In the supported `CAdminTabControl` implementation, the framework creates the tab wrapper and an edit table roughly as:

```html
<table class="adm-detail-content-table edit-table">
    <tbody>
        <!-- CONTENT is echoed here -->
    </tbody>
</table>
```

Therefore the module's `CONTENT` must be valid content for that `<tbody>` context.

Task 012 currently returns a top-level `<div>` containing another `<table>`, which produces invalid table structure when printed directly under `<tbody>`.

Change the rendered tab content so its top-level structure is compatible with the edit-table body, expected conceptually as:

```html
<tr>
    <td colspan="2">
        <!-- empty state or compact summary table -->
        <!-- management/view link -->
    </td>
</tr>
```

Requirements:

- no nested mutation `<form>` inside the native product form;
- output remains read-only summary content;
- a nested summary `<table>` inside the `<td>` is acceptable;
- all dynamic output remains escaped;
- HTTP/HTTPS URL link safety remains unchanged;
- empty state remains localized;
- `R` remains read-only and `W` may see the management link/button.

## Catalog product / SKU restriction

Task 012 currently treats any existing iblock element as a valid product context. That can make the `Мониторинг цен` tab appear on unrelated iblock element forms such as news/articles.

The admin integration in this milestone is specifically for catalog products and catalog SKU/offer elements.

Use the verified D7 catalog API to require that the element also exists in the catalog product table. The supported core provides `Bitrix\Catalog\ProductTable` backed by catalog product records.

Preferred design:

- keep generic iblock element lookup for `ID` and `NAME`;
- add a narrow catalog check in `ProductContext`, e.g. a method such as `findCatalogProduct()` / `isCatalogProduct()` or equivalent;
- require both the iblock element and the catalog product record for product-facing admin UI/service validation.

Do not change the persistence decision from ADR-0003: `ProductCompetitorTable::PRODUCT_ID` remains a generic Bitrix element ID and no database FK to catalog/iblock is introduced.

At minimum apply the catalog restriction to:

1. `ProductEditTabHandler` tab visibility;
2. `admin/product_competitors.php` product context validation;
3. `admin/product_competitor_edit.php` product context validation;
4. `ProductCompetitorLinkService::save()` product validation for this admin workflow.

A direct crafted request to the module-owned product-link pages for a non-catalog iblock element must fail safely and must not create/update links.

Both normal catalog products and SKU/offer elements that have a catalog product record must remain supported.

If the target Bitrix installation uses a verified equivalent catalog API rather than `Bitrix\Catalog\ProductTable`, document the reason in the implementation/PR.

## Existing behavior that must remain unchanged

Do not regress any Task 012 behavior:

- `ProductCompetitorTable` remains the source of truth;
- no iblock property is created;
- exact URL bytes are persisted without normalization;
- accepted admin URLs are absolute HTTP/HTTPS only;
- `URL_HASH` is derived by ORM and never editable;
- duplicate identity pre-check remains friendly while DB unique index stays authoritative;
- changing `COMPETITOR_ID` or exact `URL` resets operational state;
- changing only `ACTIVE` preserves operational state;
- collection state fields stay read-only;
- POST mutations require `W` and valid Bitrix sessid;
- no state-changing GET actions;
- deleting a product link deletes only that link;
- competitor deletion protection from Task 010 remains intact;
- uninstall/reinstall event/proxy lifecycle remains intact;
- no collector execution is introduced.

## Scope

Implement only:

- product edit tab insertion compatibility fix;
- valid tab `CONTENT` structure;
- catalog-product/SKU context restriction;
- focused source/unit regression tests;
- update the existing real-Bitrix smoke-test checklist where needed.

Do not implement:

- collector execution;
- `PriceUpdateService`;
- HTTP/Python collector;
- agent/cron/queue/retries;
- history;
- public product comparison;
- bulk editing;
- schema/table/index changes;
- migration framework;
- additional admin menu sections;
- unrelated styling/refactoring.

## Versioning

Keep:

```text
0.5.0
```

This task corrects the current `0.5.0` milestone and does not introduce a new functional milestone.

Do not bump to `0.5.1` or `0.6.0` unless a concrete repository release/update mechanism already requires it; if so, stop and document that requirement rather than silently expanding scope.

## Tests / regression guards

Add or update focused tests meaningful without a full Bitrix runtime.

At minimum guard:

### Tab API

- `ProductEditTabHandler` no longer calls `CAdminTabControl::AddTabs()` with an array;
- the handler uses the verified compatible tab insertion mechanism;
- exactly the expected `DIV` identifier is used;
- duplicate insertion is prevented if practical;
- `Access::canRead()` remains required.

A regression guard should explicitly fail if code reintroduces a pattern equivalent to:

```php
$tabControl->AddTabs([ ... ])
```

### CONTENT markup

- rendered `CONTENT` uses `<tr>` / `<td>` as its top-level admin edit-table body structure;
- no `<form>` is introduced in `ProductEditTabHandler`;
- dynamic output remains escaped;
- operational fields remain read-only.

### Catalog restriction

- `ProductContext` uses a verified catalog lookup (`Bitrix\Catalog\ProductTable` or documented equivalent);
- handler requires catalog product context;
- both module-owned product-link pages require catalog product context;
- `ProductCompetitorLinkService::save()` cannot accept an arbitrary non-catalog iblock element through this admin workflow.

Do not fake a Bitrix runtime in unit tests merely to satisfy coverage. Source guards are acceptable for integration API shape, but the PR must state that real runtime verification is still required.

Run:

- `composer validate`;
- PHP syntax checks;
- PHPUnit;
- `git diff --check`.

Keep `composer.lock` unchanged.

## Manual real-Bitrix smoke test

After merge, run on the real development installation before starting Task 014.

At minimum verify:

1. opening an existing normal catalog product edit page does not fatal and shows `Мониторинг цен`;
2. opening an existing SKU/offer edit page does not fatal and shows the tab where supported;
3. the tab HTML renders normally within the Bitrix edit form (no broken tab/table layout);
4. an unrelated non-catalog iblock element edit page does **not** show the tab;
5. direct access to `kk_pricewatch_product_competitors.php?PRODUCT_ID=<non-catalog-element>` is rejected safely;
6. direct create/edit POST for a non-catalog element cannot persist a link;
7. existing product link list/create/edit/delete still works for catalog products/offers;
8. exact query-string URL remains byte-for-byte unchanged after save;
9. duplicate identity behavior remains unchanged;
10. ACTIVE-only edit preserves operational state;
11. competitor/URL identity edit resets operational state;
12. `D/R/W` behavior remains correct;
13. uninstall/reinstall still removes/restores event/proxies and preserves data.

Record the Bitrix version, PHP version, database engine, date and result.

Any undefined method/class error, malformed product edit form, tab on non-catalog content, or ability to persist a link for a non-catalog element is a release blocker.

## Acceptance criteria

Task 013 is complete only when:

- the incorrect `AddTabs(array)` use is gone;
- the module tab is inserted with a verified supported mechanism;
- tab content is valid for `CAdminTabControl`'s `<tbody>` `CONTENT` context;
- catalog products and SKU/offers remain supported;
- arbitrary non-catalog iblock elements are rejected by product-facing admin integration;
- Task 012 CRUD/security/exact-URL/state-reset behavior is preserved;
- module version remains `0.5.0`;
- CI is green;
- real-Bitrix smoke test passes.

## Codex invocation

```text
$implement-task

Implement docs/tasks/013-product-edit-tab-bitrix-runtime-compatibility.md.
Do not implement anything outside the task scope.
```

Then review with:

```text
$code-review

Review docs/tasks/013-product-edit-tab-bitrix-runtime-compatibility.md against AGENTS.md, ADR-0003 and Task 012.
Pay particular attention to the real CAdminTabControl API, CONTENT markup context, catalog product/SKU validation, preservation of exact URL/state-reset semantics, permissions/CSRF, lifecycle, and scope discipline.
```
