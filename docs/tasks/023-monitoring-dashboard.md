# Task 023 — Monitoring operations dashboard v1

## Goal

Add a dedicated Bitrix administration page that gives authorized staff an operational overview of the current competitor-monitoring state.

The page should answer, at a glance:

- how many active monitored links exist;
- which links are currently healthy;
- which links are in an error state;
- which links still have no successful price;
- which last-known prices are stale;
- when each link was last checked / last successfully updated;
- where an operator should go to inspect the link, its history, or manually re-check it.

This task is an **administrative read/operations UI**. It must not change collection semantics, history semantics, product-card confidentiality, scheduling, or automatically mutate monitoring data.

Target module version: `0.14.0`.

## Baseline

Task 022 / module `0.13.0` is production-verified on real Bitrix for:

- module update lifecycle;
- staff-only product-card block;
- module rights `D/R/W`;
- administrator / manager / ordinary-user / guest security matrix;
- composite visit-order isolation;
- bounded product-card ORM read;
- `MAX_ROWS` normalization and `N + 1` truncation;
- additive read index;
- current-state and history preservation;
- Agent / CLI / admin proxy lifecycle.

Production monitoring currently works at **product level**. Do not add SKU/offer aggregation or offer-switching behavior in Task 023.

## Security invariant

The dashboard contains confidential operational competitor information.

Module rights remain the only authorization model:

```text
D -> no dashboard access
R -> read-only dashboard access
W -> dashboard access + links to existing write/manual-check flows
```

Requirements:

- require `Access::canRead()` before any dashboard ORM read;
- `D` users must receive normal Bitrix access denial, not partial dashboard data;
- do not hard-code Bitrix user-group IDs or names;
- `R` users must not receive new mutating controls;
- manual re-check remains available only to `W` through the existing manual-check page;
- escape every persisted/user-visible value;
- do not render raw SQL, PHP exception messages, stack traces or filesystem paths;
- do not expose collector secrets/options;
- do not show raw `ERROR_MESSAGE` in the dashboard table in v1; show stable `ERROR_CODE` and safe status labels instead.

## Required context

Before implementation read:

- `AGENTS.md`
- `.agents/skills/implement-task/SKILL.md`
- `.agents/skills/code-review/SKILL.md`
- `docs/architecture/0002-competitor-entity.md`
- `docs/architecture/0003-product-competitor-link.md`
- `docs/tasks/016-manual-price-check-admin.md`
- `docs/tasks/019-price-history.md`
- `docs/tasks/020-price-history-analytics.md`
- `docs/tasks/021-staff-only-product-prices.md`
- `docs/tasks/022-staff-product-price-read-hardening.md`
- `admin/menu.php`
- `admin/product_competitors.php`
- `admin/product_competitor_edit.php`
- `admin/price_history.php`
- `admin/product_price_check.php`
- `lib/Admin/Access.php`
- `lib/Admin/ProductContext.php`
- `lib/Model/CompetitorTable.php`
- `lib/Model/ProductCompetitorTable.php`
- `lib/Model/ProductUrl.php`
- `lib/Installer/AdminInstaller.php`
- `install/index.php`
- current update/version/package-builder files.

Use `$implement-task`.

## Operational scope and health definitions

The dashboard v1 monitors only relations that are currently operationally enabled:

```text
ProductCompetitorTable.ACTIVE = Y
CompetitorTable.ACTIVE = Y
```

Inactive links / inactive competitors are configuration state, not active monitoring health, and remain manageable through the existing competitor/product-link administration pages.

Use one shared health definition for both summary counters and list filtering. Do not implement slightly different stale/no-price rules in several admin files.

### Successful current value

A link has a successful current value only when all three fields exist:

```text
CURRENT_PRICE != null
CURRENCY != null
LAST_SUCCESS_AT != null
```

This deliberately matches the staff product-card semantics from Task 021/022.

### Stale value

For v1 use the same default age threshold as the product-card block:

```text
STALE_AFTER_SECONDS = 86400
```

A value is stale when:

```text
successful current value exists
AND LAST_SUCCESS_AT < now - 86400 seconds
```

Staleness is independent from the latest `STATUS`.

For example, a link may simultaneously be:

```text
STATUS = error
CURRENT_PRICE = last known successful price
stale = true
```

Do not erase or hide the preserved price merely because the latest check failed.

The stale threshold is a service constant in v1. Do not add a module option/settings page solely for this task.

### Healthy

A link is healthy when:

```text
STATUS = success
successful current value exists
stale = false
```

### Error

A link is in error when:

```text
STATUS = error
```

### No successful price

A link has no successful price when the successful-current-value invariant is not satisfied.

Typical examples:

```text
STATUS = new, CURRENT_PRICE = null
STATUS = error, CURRENT_PRICE = null
```

### Problem scope

The combined `problems` filter is:

```text
error OR stale OR no successful price
```

Rows must not be duplicated when more than one condition is true.

## Scope

### 1. Add a dedicated administration page

Add a normal module admin page, conceptually:

```text
admin/monitoring.php
```

with installed Bitrix admin proxy:

```text
/bitrix/admin/kk_pricewatch_monitoring.php
```

The page title should be localized, conceptually:

```text
KK PriceWatch — Состояние мониторинга
KK PriceWatch — Monitoring status
```

Use normal Bitrix administration UI conventions already used by the module (`CAdminUiList`, filters, navigation, context/actions where appropriate).

Do not build a custom SPA or frontend framework for this page.

### 2. Add the page to the module menu

The root module menu currently contains the competitors page.

Add `Monitoring` / `Мониторинг` as the first module-menu item and keep the existing competitors item.

Conceptually:

```text
KK PriceWatch
  Мониторинг
  Конкуренты
```

`Access::canRead()` remains the root-menu permission gate.

### 3. Dedicated operational read layer

Do not put all monitoring-state query/business logic directly in the admin page.

Add a narrow read-only service/repository layer, conceptually:

```text
MonitoringDashboardService
MonitoringDashboardRepositoryInterface
OrmMonitoringDashboardRepository
```

Exact names may follow repository conventions.

Responsibilities:

- apply the active-link + active-competitor scope;
- centralize health/stale/no-price criteria;
- produce summary counts;
- produce a paginated, filtered operational row set;
- preserve exact decimal strings and exact competitor URLs;
- perform reads only.

Do not use `PriceHistoryTable` for dashboard health/current-state calculations.

The dashboard is based on the current operational fields in `ProductCompetitorTable` plus competitor metadata.

### 4. Summary counters

At the top of the page show compact summary counters for the full active monitoring scope:

```text
Всего активных связей
Актуально
Ошибки
Устаревшие
Цена не получена
```

English localization must also exist.

Definitions:

```text
total_active      = all active links with active competitor
healthy           = STATUS=success + successful current value + not stale
errors            = STATUS=error
stale             = successful current value + stale=true
no_success_price  = successful current value invariant is false
```

Important: problem counters are **independent and may overlap**.

For example one link may count both in `errors` and `stale`, or in `errors` and `no_success_price`.

Do not present the problem counters as mutually exclusive subtotals that must add up to `total_active`.

A small explanatory localized note is acceptable if needed.

The summary remains global for all active monitoring links in v1; table filters do not need to recalculate the summary cards.

### 5. Operational table

Below the summary show a paginated table. At minimum include:

```text
Link ID
Product
Competitor
Current price
Status
Freshness / health
Last check
Last success
Error code
Source
```

Recommended row presentation:

```text
#293 / Product name
Royal Computers
187 040 ₽
success
Актуально
15.09.2026 14:20
15.09.2026 14:20
—
Открыть источник ↗
```

For an error with a preserved price:

```text
29 990 ₽
error
Ошибка обновления
[last successful timestamp remains visible]
ERROR_CODE
```

For an error/new row without a successful price:

```text
Цена не получена
error/new
Цена не получена
...
ERROR_CODE when available
```

If a preserved price is stale and the latest status is also error, both facts should remain visible (for example status=`error` plus health badge=`Устарела`). Do not reduce the row to one lossy status string.

Money remains a decimal string in service data. Presentation formatting may use the same safe formatting approach as the product-card template; do not use float arithmetic.

### 6. Safe source links

The table may expose an `Open source` action/link for authorized staff.

Requirements:

- use the exact persisted URL unchanged;
- validate with the shared `ProductUrl::isAcceptedHttpUrl()` policy or documented equivalent shared helper;
- only absolute `http` / `https` URLs become clickable;
- escape attribute/text output;
- use `target="_blank" rel="noopener noreferrer"`;
- unsafe legacy values must be non-clickable;
- do not normalize/reorder/remove query parameters.

### 7. Product names without N+1 queries

The dashboard should show a useful product label, ideally:

```text
Product name (#293)
```

Do **not** call `ProductContext::find()` / `findCatalogProduct()` once per dashboard row.

For one page of results:

1. collect unique `PRODUCT_ID` values;
2. resolve product names in one batched `Bitrix\Iblock\ElementTable` query (or equivalent one-query batch approach);
3. map names back to rows.

If `iblock` cannot be loaded or an element was deleted, the dashboard must still render and show at least:

```text
#PRODUCT_ID
```

Do not make a missing/deleted Bitrix product fatal to the monitoring dashboard.

### 8. Filters

Add standard Bitrix admin filters for at least:

```text
PRODUCT_ID       exact positive integer
COMPETITOR_ID    active competitor dropdown
STATUS           all / new / success / error
HEALTH           all / problems / healthy / stale / no_price / error
```

Rules:

- filters combine with logical `AND` between filter fields;
- `HEALTH=problems` uses `error OR stale OR no_price` internally;
- do not duplicate rows for overlapping problem conditions;
- invalid product/competitor/status/health values are ignored or normalized safely;
- no raw SQL fragments from request parameters;
- the page remains scoped to active link + active competitor rows.

A free-text product-name search is out of scope for v1.

### 9. Pagination and sorting

Use normal admin navigation with a finite SQL `LIMIT/OFFSET`.

Do not load all monitoring links and paginate in PHP.

A count query for admin pagination is acceptable.

Allow only a whitelist of sortable columns. At minimum reasonable sort fields are:

```text
ID
PRODUCT_ID
COMPETITOR_ID
CURRENT_PRICE
STATUS
LAST_CHECK_AT
LAST_SUCCESS_AT
```

Do not pass arbitrary request field names into ORM ordering.

The exact default sort may follow current admin conventions, but it must be deterministic with `ID` as a tie-breaker where needed.

### 10. Query discipline

For one normal dashboard page render, avoid per-row database access.

Expected shape:

```text
summary aggregate/count reads
+ one pagination count
+ one bounded page query
+ one batched product-name query
+ one small competitor list query for filter options
```

Do not introduce:

```text
one query per product row
one query per competitor row
one history query per row
one collector call per row
```

A small fixed number of summary count/aggregate queries is acceptable in v1. A single conditional aggregate query is welcome but not required if it reduces clarity or Bitrix portability.

No new database index is required by Task 023. Do not add speculative status/date indexes without real measurement.

### 11. Row actions

For every row, provide safe navigation to existing module functionality.

Read-capable (`R`/`W`) actions may include:

```text
Open/view relation
Open product monitoring links
Open history
Open source URL
```

Use existing pages and exact context parameters:

```text
kk_pricewatch_product_competitor_edit.php
kk_pricewatch_product_competitors.php
kk_pricewatch_price_history.php
```

For `W` only, add:

```text
Check price manually
```

by navigating to the existing manual-check flow:

```text
kk_pricewatch_product_price_check.php
MODE=link
PRODUCT_ID=<exact row product>
LINK_ID=<exact row link>
```

Task 023 must not implement a second/manual-check POST handler inside the dashboard.

`R` users must not see the manual-check action.

### 12. Read-only dashboard boundary

Rendering/filtering/sorting the dashboard must never:

- run `PriceUpdateService`;
- invoke a collector;
- issue outbound HTTP;
- update `CURRENT_PRICE` / `CURRENCY` / `STATUS`;
- update `LAST_CHECK_AT` / `LAST_SUCCESS_AT`;
- insert history rows;
- change competitor/link activity;
- write module options;
- enqueue jobs.

The only write-capable navigation introduced by this task is a `W`-only link to the **existing** manual-check page.

### 13. Graceful read failure

An operational dashboard query failure must not expose raw exceptions to the browser.

For an authorized user:

- show a localized neutral `CAdminMessage` such as `Не удалось загрузить состояние мониторинга`;
- do not include SQL text, exception messages, stack traces or paths;
- avoid rendering misleading counters/table data after a failed read.

Normal server/PHP/Bitrix logging is optional and must not expose collector secrets.

### 14. Localization

Provide RU and EN localization for:

- menu item;
- page title;
- counter labels;
- table headers;
- filter labels/options;
- health labels;
- no-price/stale/error/healthy labels;
- row actions;
- safe read-failure message;
- any explanatory overlap note.

Do not hard-code Russian business labels directly in the admin PHP page.

### 15. Admin proxy / install / uninstall lifecycle

Add the normal proxy under:

```text
install/admin/kk_pricewatch_monitoring.php
```

Fresh install must copy it through the existing `AdminInstaller`.

Update uninstall verification in `install/index.php` so the new proxy is treated exactly like the existing module-owned admin proxies.

Uninstall must remove the module-owned monitoring proxy while preserving module DB data, as today.

Reinstall must restore the proxy and menu entry.

### 16. Update and versioning

Target version:

```text
0.14.0
```

Add:

```text
install/updates/0.14.0/updater.php
install/updates/0.14.0/description.ru
install/updates/0.14.0/description.en
```

The updater should be narrowly scoped.

Because Task 023 has no schema or frontend-component change, the expected updater behavior is conceptually:

```text
require module include.php
AdminInstaller->install(...)
```

Do not run `SchemaInstaller` merely out of habit when no schema change exists.

Do not run collection or modify business/history data.

Update-package generation must include the new runtime admin/service/lang files, proxy, updater and version metadata.

### 17. No schema migration

Task 023 needs no new table, column or index.

Do not modify:

```text
b_kk_pricewatch_competitor
b_kk_pricewatch_product_competitor
b_kk_pricewatch_price_history
```

Do not rewrite existing rows during update.

### 18. Existing behavior must remain unchanged

Do not change semantics of:

```text
collector contracts
Mock collector
HTTP collector
PriceUpdateService
manual price-check behavior
scheduler / CLI / Agent
CURRENT_PRICE / CURRENCY
error preservation
LAST_CHECK_AT / LAST_SUCCESS_AT
price-history append rules
Task 020 analytics
exact URL identity/hash
Task 021/022 staff product-card security/composite behavior
Task 022 MAX_ROWS behavior
module D/R/W rights
uninstall data preservation
```

## Out of scope

Do not add in Task 023:

- public/customer monitoring dashboard;
- dashboard AJAX API;
- inline automatic refresh/polling;
- notifications/alerts;
- e-mail/Telegram/Bitrix24 alerts;
- automatic repricing;
- bulk manual collection from the dashboard;
- enable/disable toggles in table rows;
- editable prices/statuses;
- SKU/offer aggregation;
- product-name free-text search;
- charts per row;
- history queries per row;
- module settings page for stale threshold;
- new DB indexes without measured need.

## Tests

Add deterministic automated coverage. At minimum prove:

1. dashboard admin entry requires module load + `Access::canRead()`;
2. `D` access cannot reach dashboard data;
3. `R` can read but does not receive manual-check/write action;
4. `W` receives the existing manual-check navigation action;
5. operational scope includes only active links with active competitors;
6. `total_active` count follows that scope;
7. healthy definition matches `status=success + complete current value + not stale`;
8. error count uses `STATUS=error`;
9. stale definition uses complete current value + `LAST_SUCCESS_AT < now-86400`;
10. no-success-price definition matches missing price/currency/last-success invariant;
11. summary problem counters may overlap without double-adding rows to the table;
12. `HEALTH=problems` means `error OR stale OR no_price`;
13. `HEALTH=healthy/stale/no_price/error` filters select correct rows;
14. `STATUS` filter accepts only known statuses;
15. `PRODUCT_ID` filter is exact and positive;
16. `COMPETITOR_ID` filter is exact and safely normalized;
17. filters combine without raw SQL request fragments;
18. list query is paginated with finite `limit`/`offset`;
19. sort field is whitelist-controlled;
20. deterministic tie-break ordering is retained;
21. dashboard current-state path does not read `PriceHistoryTable`;
22. dashboard render path does not invoke `PriceUpdateService` or collectors;
23. dashboard read causes no business-data writes;
24. product names are resolved in one batch query for a page, not one query per row;
25. missing/deleted product falls back to `#PRODUCT_ID` without page failure;
26. competitor names / error codes / URLs / product names are escaped;
27. only safe absolute http/https exact URLs are clickable;
28. exact competitor query string is preserved;
29. raw `ERROR_MESSAGE` is not rendered by the dashboard;
30. read failure produces only a neutral localized admin message;
31. menu contains Monitoring before Competitors;
32. RU/EN localization exists for the new page/menu/filter/status/action labels;
33. new `kk_pricewatch_monitoring.php` proxy exists in `install/admin`;
34. fresh install copies the proxy;
35. uninstall removes/verifies the proxy without deleting DB data;
36. `0.13.0 -> 0.14.0` updater installs admin entry points only and performs no schema migration;
37. update package includes new proxy/admin/lang/service/version/update files;
38. existing Task 019/020/021/022 regression tests remain green;
39. scheduler/CLI/Agent/collector regressions remain green;
40. full PHPUnit passes;
41. PHP syntax checks pass.

Tests must not depend on live Internet or a live catalog site.

## Real Bitrix smoke

After CI is green, verify on the real installation.

### Baseline before update

Capture immediately before updating:

```text
module version = 0.13.0
current monitored-link rows/state
history total and per-link counts
Agent state
existing admin proxies
```

### Update lifecycle

1. Update `0.13.0 -> 0.14.0` through the normal update path.
2. Confirm version `0.14.0`.
3. Confirm `/bitrix/admin/kk_pricewatch_monitoring.php` exists.
4. Confirm all previous admin proxies remain.
5. Confirm no table/index schema change was introduced.
6. Confirm monitored-link current state and history counts are unchanged by update.
7. Confirm Agent state remains unchanged.

### Permission smoke

8. Administrator / `W` -> Monitoring page visible.
9. Manager / `R` -> Monitoring page visible.
10. `D` user -> access denied, no dashboard data.
11. `R` user -> no manual-check action.
12. `W` user -> manual-check action is present and opens the existing manual-check page.

### Current production-state smoke

Using the actual current data, compare dashboard values with direct ORM/SQL observations taken at the same time.

At minimum verify:

13. total active row count matches active links + active competitors.
14. error filter returns the current error links.
15. no-price filter returns rows without successful current value.
16. one exact product filter returns only that product's monitoring links.
17. one competitor filter returns only that competitor's links.
18. source links preserve exact URLs/query strings.
19. history action opens the correct exact link identity.
20. product/link action opens the correct exact product/link context.

Do not hard-code expected production IDs/counts in automated tests; production state changes over time.

### Read-only proof

21. Capture before dashboard visits:

```text
CURRENT_PRICE
CURRENCY
STATUS
ERROR_CODE
LAST_CHECK_AT
LAST_SUCCESS_AT
history count
```

22. Visit/filter/sort several dashboard views as `R`/`W` without executing manual check.
23. Capture the same state again.
24. Confirm dashboard reads themselves changed none of those fields/counts.

### Performance observation

25. With SQL debug/monitoring or a concise instrumentation method, verify there is no one-query-per-row product-name lookup and no history query per row.
26. Confirm the main table query uses finite pagination limit/offset.

The production dataset may be small; this is a query-shape smoke, not a benchmark target.

## Final acceptance

Task 023 passes when:

```text
0.14.0 update is safe
Monitoring admin page exists
D denied
R read-only
W can navigate to existing manual check
summary health counters are correct
problem/health filters are correct
list is paginated and deterministic
product names are batch-resolved
no per-row history/collector queries
safe exact source URLs
no raw error/exception leakage
no schema/business/history mutation
existing product-card security remains unchanged
full CI green
real Bitrix smoke green
```
