# Task 016 — Manual price check from Bitrix admin

## Goal

Expose the already implemented and real-Bitrix-verified `PriceUpdateService` as a safe manual admin action.

Administrators/managers with module write permission must be able to:

1. manually check one existing monitored product ↔ competitor link;
2. manually check all active monitored links for one catalog product / SKU;
3. see a concise result summary after the run;
4. return to the product monitoring UI and see the persisted price/status/timestamps.

The implementation must reuse the existing orchestration service unchanged:

```text
Bitrix admin UI
      |
      | explicit manual action
      v
PriceUpdateServiceFactory::createDefault()
      |
      v
PriceUpdateService::updateLinks([...])
      |
      v
existing collector contract + persistence rules
```

This is an admin-trigger milestone only. Do not add scheduling, agents, cron, HTTP transport, Python integration, price history, retries or queueing.

The current `0.6.0` orchestration has already passed unit/CI and real-Bitrix smoke for:

- exact success persistence;
- exact URL/query-string identity;
- item-level `PRICE_NOT_FOUND`;
- stale-price preservation;
- mixed success/error in one competitor group;
- independent competitor groups;
- group-level `COLLECTOR_ERROR` isolation;
- duplicate requested IDs;
- missing link `NOT_FOUND`;
- inactive link skip with no mutation;
- inactive competitor skip with no mutation.

Task 016 must expose that behavior; it must not reimplement it.

## Required context

Before implementation read:

- `AGENTS.md`
- `.agents/skills/implement-task/SKILL.md`
- `.agents/skills/code-review/SKILL.md`
- `docs/architecture/0001-collector-boundary.md`
- `docs/architecture/0002-competitor-entity.md`
- `docs/architecture/0003-product-competitor-link.md`
- `docs/tasks/012-product-competitor-admin-integration.md`
- `docs/tasks/013-product-edit-tab-bitrix-runtime-compatibility.md`
- `docs/tasks/014-price-update-service-mock-orchestration.md`
- `docs/tasks/015-price-update-service-regression-tests-ci-repair.md`
- `admin/product_competitors.php`
- `admin/product_competitor_edit.php`
- `lib/Admin/Access.php`
- `lib/Admin/ProductContext.php`
- `lib/Admin/ProductEditTabHandler.php`
- `lib/Model/ProductCompetitorTable.php`
- `lib/Service/PriceUpdateService.php`
- `lib/Service/PriceUpdateServiceFactory.php`
- `lib/Service/PriceUpdateBatchResult.php`
- `lib/Service/PriceUpdateOutcome.php`
- current module installer/admin-proxy lifecycle code.

Use `$implement-task`.

Do not infer Bitrix admin APIs, CSRF APIs, admin-message helpers, redirect helpers or list-action behavior from method names. Verify exact APIs against the supported Bitrix runtime/source before implementation.

## Architectural decisions

### 1. Manual UI is only an adapter over PriceUpdateService

The admin layer may select link IDs, authorize the user, validate context, call the existing service and render/redirect the result.

It must not:

- instantiate `MockCollector` directly;
- inspect `COLLECTOR_TYPE` to decide collection behavior;
- persist `CURRENT_PRICE`, `STATUS`, timestamps or collector errors directly;
- duplicate stale-price/error rules;
- normalize competitor URLs;
- special-case RoyalPC, KometaPC, DNS or any other competitor.

All collection semantics remain owned by `PriceUpdateService` and the collector factory boundary.

### 2. No state-changing GET requests

The existing product edit tab lives inside Bitrix's native product form. Do not insert a nested form into that tab.

Links/buttons rendered in the product tab or list may navigate with GET to a module-owned manual-check page, but GET must never execute a collector or mutate collection state.

Actual execution requires POST + valid Bitrix CSRF token.

### 3. Module permission model

Use `KK\PriceWatch\Admin\Access`.

- module right `D`: no access;
- module right `R`: may see read-only monitoring state but may not execute a manual check;
- module right `W`: may execute manual checks.

Do not use `$USER->IsAdmin()` as the authorization model.

Direct crafted requests must enforce the same permission checks as visible UI controls.

## Scope

Implement only:

- manual one-link check;
- manual all-active-links-for-product check;
- module-owned confirmation/execution page;
- entry points from current product monitoring UI;
- result presentation;
- installer/admin-proxy lifecycle for the new page;
- localization;
- focused tests/source guards;
- real-Bitrix smoke checklist;
- module version bump to `0.7.0`.

Do not implement:

- agent/cron/scheduler;
- recurring checks;
- queue;
- retry/backoff policy;
- HTTP collector;
- external Python service;
- browser/Selenium collector;
- price history table;
- notifications;
- automatic repricing;
- public frontend comparison;
- dashboard/statistics;
- bulk checks across the whole module/catalog;
- schema changes or new DB tables;
- changes to collector contract v1;
- unrelated refactoring.

## 1. Module-owned manual check page

Add a module-owned admin page conceptually similar to:

```text
/bitrix/admin/kk_pricewatch_product_price_check.php
```

with the normal install/admin proxy pattern already used by the module.

Suggested module source path:

```text
admin/product_price_check.php
```

Suggested proxy path:

```text
install/admin/kk_pricewatch_product_price_check.php
```

Exact naming may vary only for a concrete repository reason.

The page must support two explicit scopes.

### Single-link scope

Conceptual request:

```text
MODE=link
PRODUCT_ID=123
LINK_ID=456
```

Validation:

- `PRODUCT_ID > 0`;
- product/SKU exists and is a supported catalog element using the existing `ProductContext` boundary;
- `LINK_ID > 0`;
- the link exists;
- the link belongs to exactly the supplied `PRODUCT_ID`;
- do not permit checking an arbitrary link by combining another product's `PRODUCT_ID` with its ID.

The execution input to `PriceUpdateService` is exactly:

```php
[$linkId]
```

Do not expand this into another product scan.

### Product scope

Conceptual request:

```text
MODE=product
PRODUCT_ID=123
```

On execution, select link IDs from `ProductCompetitorTable` with:

```text
PRODUCT_ID = requested product
ACTIVE = Y
```

Use deterministic ordering, preferably `ID ASC`.

Then call `PriceUpdateService::updateLinks()` once with that explicit batch.

Do not scan rows belonging to other products.

Do not implement whole-catalog checking in this task.

If the product has no active monitored links, render a localized informational state and do not call `PriceUpdateService` with an empty batch.

Inactive competitors do not need to be filtered by the UI. Passing an active link whose competitor is inactive is valid; `PriceUpdateService` already owns the `INACTIVE_COMPETITOR` skip behavior.

## 2. GET behavior — confirmation/read-only only

A GET request to the manual-check page must be side-effect free.

It must:

- require at least module read permission to view the page;
- validate the requested product/link context;
- show what will be checked;
- show the current persisted monitoring state;
- show an execution button only when `Access::canWrite()` is true;
- never call `PriceUpdateService`;
- never update `LAST_CHECK_AT`, status, price or any other persistence field.

Suggested labels:

```text
Проверить цену
Проверить активные цены товара
```

For single-link mode show at least:

- product name/ID;
- competitor name;
- exact URL;
- active state;
- current price/currency;
- status;
- last check;
- last success.

For product mode show the product context and the number/list of active monitored links that will be submitted.

All dynamic values are untrusted output and must be escaped.

If an exact competitor URL is rendered as clickable, use the existing accepted HTTP/HTTPS URL policy and safe escaping/`rel` behavior already used by current admin UI.

## 3. POST execution

Execution requires all of:

- module write permission;
- POST request;
- valid `check_bitrix_sessid()`;
- valid mode and target context;
- product/link ownership validation described above.

Only after these checks call:

```php
$service = \KK\PriceWatch\Service\PriceUpdateServiceFactory::createDefault();
$result = $service->updateLinks($linkIds);
```

Equivalent dependency wiring is acceptable if it still uses the existing factory/service boundary and improves testability without changing service semantics.

Do not persist collection state in the admin page itself.

### Unexpected UI-boundary failures

`PriceUpdateService` already converts expected collector/configuration failures to outcomes.

If an unexpected throwable still escapes at the admin boundary:

- do not display a stack trace, secret, SQL text or filesystem path;
- show a localized generic failure message;
- preserve normal Bitrix admin rendering;
- logging may be used if consistent with current project/Bitrix practice, but do not log collector secrets/tokens unnecessarily.

Do not convert every expected per-link outcome into a fatal UI exception.

## 4. PRG and result presentation

After successful POST handling, use Post/Redirect/Get so browser refresh cannot re-run a collector action.

The redirected result view must make it clear what happened.

At minimum present aggregate counts from `PriceUpdateBatchResult`:

```text
requested
success
errors
skipped
persistence_failures
```

Also show per-link outcomes where practical:

```text
linkId
status
code
message
```

Requirements:

- escape all outcome messages/codes before HTML output;
- do not trust query-string result data as authoritative if it can be recomputed/read safely;
- do not place raw exception text into redirect query parameters;
- use a small server-side/Bitrix-safe flash/session mechanism if needed for transient batch outcome details;
- do not introduce a new DB table merely to store one manual-run result.

After the run provide a clear link back to:

```text
kk_pricewatch_product_competitors.php?PRODUCT_ID=...
```

The persisted table/list state remains the source of truth after reload.

## 5. Entry points in current UI

### Product monitoring list page

On `admin/product_competitors.php` add, for `W` only:

- a product-level action: `Проверить активные цены`;
- a per-row action/link: `Проверить цену`.

These links navigate to the module-owned confirmation page and therefore are safe GET navigation, not the mutation itself.

For users with only `R`, do not render execution actions.

Keep existing create/edit behavior unchanged.

### Product edit tab

The existing `Мониторинг цен` product/SKU tab may expose:

- one product-level `Проверить активные цены` link for `W`;
- optionally a per-row `Проверить` link for `W` if it can be added cleanly.

Because the native Bitrix product form owns the surrounding form, these must remain ordinary navigation links to the module-owned manual-check page.

Do not add nested forms and do not intercept native product save.

### Link edit page

It is acceptable and desirable to add a `Проверить цену` navigation action for an existing link when the user has `W`, provided it points to the same module-owned confirmation page and does not duplicate execution logic.

Do not show a check action for a not-yet-created link without a persistent `ProductCompetitorTable::ID`.

## 6. Active/inactive semantics

Manual UI does not redefine processability.

### Inactive link

Product-level mode selects only `ACTIVE=Y` links.

If a crafted/direct single-link request targets an inactive link, it is acceptable to let `PriceUpdateService` return:

```text
skipped / INACTIVE_LINK
```

The UI must not directly mutate the row or fake a success result.

### Inactive competitor

An active link pointing to an inactive competitor may be submitted to the service and must surface the existing:

```text
skipped / INACTIVE_COMPETITOR
```

No UI workaround is required.

### Missing link during race

If the link existed on GET but is deleted before POST, fail safely. Either reject context before execution or allow the service's existing `NOT_FOUND` outcome, depending on where the race is detected.

Do not create replacement rows.

## 7. Batch behavior

For product mode, one manual action must call the existing service once with one explicit link-ID batch.

Do not call `updateLinks([$id])` repeatedly in a loop.

This preserves the existing grouping-by-competitor behavior and lets links of one competitor be collected in one collector request.

The admin layer must not group by competitor itself.

No extra transaction should wrap the complete manual batch. Existing per-link persistence/failure isolation remains authoritative.

## 8. Security requirements

Required:

- module permission checks on every page boundary;
- `W` required server-side for execution;
- CSRF validation for every execution POST;
- no state-changing GET;
- product/link ownership validation;
- no caller-supplied operational fields;
- no caller-supplied URL used to execute collection — collection URLs come from persisted ORM rows only;
- escaped product names, competitor names, URLs, statuses, codes and messages;
- no raw exception details in browser output;
- no arbitrary return URL/open redirect from request input.

Do not accept a POST payload such as:

```text
URL=https://...
PRICE=...
STATUS=...
```

The manual action identifies persisted link IDs only. `PriceUpdateService` reloads canonical rows/configuration.

## 9. Admin proxy/install/uninstall lifecycle

Install the new admin proxy using the module's existing safe proxy lifecycle.

Requirements:

- install/update creates the new proxy;
- uninstall removes only the module-owned proxy;
- do not reintroduce the unsafe cleanup behavior fixed in Task 011;
- reinstall restores the proxy;
- no ORM data is deleted by normal uninstall;
- no new event handler is required solely for the manual action page.

If installer code already owns an explicit proxy-file list, add the new proxy there rather than inventing a second mechanism.

## 10. Localization

Add Russian and English language messages following current module conventions.

At minimum localize:

- page title;
- single-link/product-mode confirmation copy;
- execute buttons;
- no-active-links state;
- access/validation errors;
- generic unexpected failure;
- aggregate result labels;
- outcome labels/actions/back link.

Stable machine codes such as `PRICE_NOT_FOUND`, `INACTIVE_LINK` and `COLLECTOR_ERROR` may remain unchanged.

Do not translate or rewrite stored collector error messages before persistence; only escape them for output.

## 11. Tests and source guards

Add focused tests that are meaningful without pretending to execute a full Bitrix admin runtime.

At minimum cover/guard as practical:

### Authorization / CSRF

- execution path requires module write permission;
- POST execution checks `check_bitrix_sessid()`;
- GET path does not execute `PriceUpdateService`;
- users with only `R` do not receive execution controls;
- direct execution attempt without `W` is rejected.

### Scope correctness

- single-link mode passes exactly the requested persisted link ID;
- link must belong to requested `PRODUCT_ID`;
- product mode selects only rows for requested `PRODUCT_ID`;
- product mode selects only `ACTIVE=Y` links;
- deterministic ID ordering is used;
- empty product batch does not invoke the service;
- product mode performs one `updateLinks([...])` call, not N one-link calls.

### Architecture

- admin execution uses `PriceUpdateServiceFactory` / `PriceUpdateService` boundary;
- no `MockCollector` instantiation in admin code;
- no collector-type branching in admin code;
- admin code does not directly update `CURRENT_PRICE`, `CURRENCY`, `STATUS`, `ERROR_*`, `LAST_CHECK_AT` or `LAST_SUCCESS_AT`;
- no URL supplied by the request is passed to collector execution.

### UI / security

- state-changing GET action is absent;
- new proxy is included in install/uninstall lifecycle;
- product edit tab contains navigation only, not a nested mutation form;
- rendered outcome messages are escaped;
- unexpected throwable text is not exposed directly;
- crafted product/link mismatch is rejected.

### Regression

- existing ProductCompetitor CRUD tests remain green;
- existing PriceUpdateService tests remain green;
- `composer.lock` stays unchanged unless a dependency change is explicitly justified;
- module version is `0.7.0`.

Run:

```text
composer validate
PHP syntax checks
vendor/bin/phpunit
git diff --check
```

Use the repository's exact existing CI commands where they differ.

## 12. Real-Bitrix smoke test after merge

Run this on the real development installation.

### Permissions and safe GET

1. `W` user sees `Проверить цену` for an existing link and `Проверить активные цены` for the product.
2. `R` user may view monitoring state but cannot see/execute manual-check actions.
3. `D` user cannot access the manual-check page.
4. Open a check-confirmation GET page and confirm `LAST_CHECK_AT` does not change before POST.
5. Refresh the GET confirmation page repeatedly and confirm no collection occurs.
6. POST without/with invalid sessid is rejected and does not change `LAST_CHECK_AT`.

### Single-link success

7. Use an existing active mock link with exact URL match.
8. Execute one-link check.
9. Confirm result summary reports one success.
10. Confirm DB/list state has expected `CURRENT_PRICE`, `CURRENCY`, `STATUS=success`, cleared error fields, updated `LAST_CHECK_AT` and `LAST_SUCCESS_AT`.
11. Browser refresh after PRG must not run the collector again; `LAST_CHECK_AT` must remain unchanged.

### Single-link item error / stale state

12. Configure one link for `PRICE_NOT_FOUND` after it already has a successful price.
13. Execute from admin UI.
14. Confirm UI surfaces item error safely.
15. Confirm stale price/currency/`LAST_SUCCESS_AT` are preserved and only error/check state changes according to existing service semantics.

### Product batch

16. Product has at least two active links for the same competitor: one success and one item error.
17. Run `Проверить активные цены` once.
18. Confirm aggregate result contains both outcomes and DB state matches each independently.
19. Add/use another competitor and confirm the same product batch preserves cross-competitor isolation.
20. Configure one competitor as unsupported `external` and confirm its group reports safe `COLLECTOR_ERROR` while another competitor group still succeeds.

### Skip/race behavior

21. Directly target an inactive link in single-link mode and confirm `INACTIVE_LINK` is shown as skipped with no persisted mutation.
22. Active link + inactive competitor yields `INACTIVE_COMPETITOR` with no persisted mutation.
23. Product-level action excludes inactive links from its selected batch.
24. Delete a link between confirmation GET and execution POST; request must fail safely or show `NOT_FOUND`, without creating data.
25. Attempt a crafted `PRODUCT_ID` + `LINK_ID` mismatch and confirm execution is rejected.

### Lifecycle

26. Existing product edit tab still loads for normal catalog product and SKU/offer.
27. No nested form/product-save regression is introduced.
28. Uninstall removes the new admin proxy while preserving ORM tables/data.
29. Reinstall restores the manual-check page and existing data remains intact.

## Acceptance criteria

Task 016 is complete when:

- a `W` user can safely trigger a check for one persisted monitored link;
- a `W` user can safely trigger one batch check for all active links of one product/SKU;
- all execution goes through the existing `PriceUpdateService` boundary;
- GET is side-effect free;
- POST requires valid CSRF and module write permission;
- product/link scope cannot be forged across products;
- manual batch preserves existing success/error/skip/failure isolation semantics;
- UI does not expose raw exception details;
- browser refresh after POST cannot repeat the collection action;
- admin proxy install/uninstall lifecycle is correct;
- no collector/network/scheduling/history architecture is added;
- automated tests and CI are green;
- real-Bitrix smoke passes;
- module version is `0.7.0`.

## Out of scope / next likely milestone

After Task 016 and its real-Bitrix smoke are complete, the next likely milestone is scheduled execution over existing links using the same `PriceUpdateService` — with explicit batch selection/chunking and one common execution path callable from both Bitrix agent and cron. Do not start that work in Task 016.