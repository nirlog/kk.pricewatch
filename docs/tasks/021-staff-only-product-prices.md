# Task 021 — Staff-only competitor prices in product card v1

## Goal

Expose the already collected current competitor-price state inside a Bitrix catalog product card **only to authorized store staff**.

This is not a public customer feature. Guests, ordinary registered customers and users without `kk.pricewatch` read permission must receive no competitor-price data at all.

Target flow:

```text
Bitrix catalog product detail page
              |
              v
kk.pricewatch:product.prices
              |
              v
      Access::canRead()
          |         |
          N         Y
          |         |
      no output     v
             StaffProductPriceReadService
                       |
                       v
            ProductCompetitorTable
                 + CompetitorTable
                       |
                       v
             staff-only HTML block
```

Target module version: `0.12.0`.

## Security invariant

The primary acceptance rule of Task 021 is:

> Competitor prices and competitor product URLs are confidential staff information.

Only users with module permission `R` or `W` may see the block.

Users with `D`, ordinary customers and guests must not receive competitor data in any form, including:

- visible HTML;
- hidden HTML;
- comments;
- `data-*` attributes;
- inline JSON;
- JavaScript variables;
- page-source fragments;
- cached component output;
- composite/static page cache;
- AJAX responses or other frontend endpoints introduced by this task.

Do not hard-code Bitrix group IDs or names such as “Internet store managers”. The existing module-right model is the authorization source:

```text
D -> no access
R -> may view staff price block
W -> may view staff price block + existing administrative write functionality
```

The site administrator naturally has module access. The target installation can grant `R` to the appropriate store-manager group through Bitrix module rights.

## Baseline

Task 020 / module `0.11.0` is production-verified for:

- current competitor state;
- Mock and HTTP collection;
- scheduler/CLI/Agent orchestration;
- append-only price history;
- current-identity analytics;
- mixed-currency handling;
- lifecycle and update preservation;
- no destructive history cascades;
- module rights `D/R/W`;
- final clean production state after smoke tests.

Task 021 must not change collection, history or analytics semantics.

## Required context

Before implementation read:

- `AGENTS.md`
- `.agents/skills/implement-task/SKILL.md`
- `.agents/skills/code-review/SKILL.md`
- `docs/architecture/0001-collector-boundary.md`
- `docs/architecture/0002-competitor-entity.md`
- `docs/architecture/0003-product-competitor-link.md`
- `docs/tasks/014-price-update-service-mock-orchestration.md`
- `docs/tasks/016-manual-price-check-admin.md`
- `docs/tasks/017-scheduled-price-update-runner.md`
- `docs/tasks/018-http-html-collector.md`
- `docs/tasks/019-price-history.md`
- `docs/tasks/020-price-history-analytics.md`
- `lib/Admin/Access.php`
- `lib/Model/CompetitorTable.php`
- `lib/Model/ProductCompetitorTable.php`
- `install/index.php`
- current installer/update/version/package-builder files

Use `$implement-task`.

## Scope

### 1. Add a reusable Bitrix component

Implement a standard Bitrix component:

```text
kk.pricewatch:product.prices
```

The installed component should be usable from a catalog product-detail template, conceptually:

```php
$APPLICATION->IncludeComponent(
    'kk.pricewatch:product.prices',
    '',
    [
        'PRODUCT_ID' => (int) $effectiveProductId,
        'MAX_AGE_SECONDS' => 86400,
    ]
);
```

Exact packaging paths should follow Bitrix marketplace/module conventions and the repository’s installer structure.

Provide at minimum:

- component class;
- `.description.php`;
- `.parameters.php` where appropriate;
- default template;
- RU/EN localization;
- scoped CSS only if needed;
- installer/update lifecycle for the component files.

Do not couple the component to the king-komp site template internally. The component belongs to `kk.pricewatch` and must remain reusable.

### 2. Exact PRODUCT_ID contract

`PRODUCT_ID` is the exact Bitrix catalog entity whose monitored competitor links should be displayed.

Rules:

- required positive integer;
- invalid/missing value -> no output;
- no inference from request URL;
- no implicit fallback to unrelated parent/product/offer IDs;
- no aggregation across parent product + all offers;
- no “closest matching” SKU logic.

For SKU catalogs the caller must pass the effective currently displayed/selected offer ID when competitor links are attached to offers, or the product ID when links are attached to the parent product.

Automatic client-side switching when the shopper changes an offer/SKU is **out of scope for v1** and can be handled in a later integration task. Task 021 must document this explicitly so the module never shows a competitor price for the wrong SKU by guessing.

### 3. Authorization before data access

The component must perform the module permission check before querying competitor data.

Conceptually:

```text
Loader::includeModule('kk.pricewatch')
        |
        v
Access::canRead()
        |
   false -> return with zero output
        |
        true
        v
read current price rows
```

Do not execute the competitor-price ORM query for unauthorized users.

Do not introduce a separate frontend permission model.

Do not hard-code `$USER->IsAdmin()` as the only allowed path: users with module permission `R` must also work.

### 4. Cache/composite safety is mandatory

Security is more important than frontend caching in v1.

Do **not** use a component result-cache design that can mix authorized and unauthorized output.

The simplest acceptable v1 implementation is:

- authorization check before any result construction;
- no shared component output cache;
- a Bitrix composite-compatible dynamic area/frame for the staff block when required by the product page;
- authorization repeated on every server-side render/dynamic request.

If any caching is implemented, its cache key and execution order must make cross-permission leakage impossible and automated tests must prove it. Do not rely only on “normally authorized users bypass composite cache”.

A guest/customer request must never receive staff competitor data even after the same product page has been visited by an administrator or manager.

Likewise, visiting as guest first must not suppress the block for a later authorized staff request.

### 5. Dedicated read service

Add a narrow read service, conceptually:

```text
KK\PriceWatch\Service\StaffProductPriceReadService
```

Exact naming may follow repository conventions.

Input:

```text
PRODUCT_ID
MAX_AGE_SECONDS
```

Output rows should contain only presentation-safe structured data needed by the component, at minimum:

```text
link_id
competitor_id
competitor_name
exact_url
current_price|null
currency|null
status
last_check_at|null
last_success_at|null
is_stale
```

The service is read-only.

The service must query current operational state from `ProductCompetitorTable` plus `CompetitorTable`.

Do not use `PriceHistoryTable` in this frontend read path.

### 6. Which monitored links are included

Only include rows where:

```text
ProductCompetitorTable.ACTIVE = Y
CompetitorTable.ACTIVE = Y
PRODUCT_ID = requested exact PRODUCT_ID
```

Do not silently collapse multiple monitored URLs for the same competitor. Each active monitored link is a distinct row.

Recommended deterministic order:

```text
Competitor SORT ASC
Competitor NAME ASC
ProductCompetitor ID ASC
```

### 7. Current state semantics

Task 019 deliberately preserves the last successful `CURRENT_PRICE/CURRENCY/LAST_SUCCESS_AT` when a later collection fails. The staff block should respect that model.

For every active monitored link:

#### Fresh successful state

When a current price/currency exists and the latest successful value is not stale:

```text
price shown
currency shown
last successful timestamp shown
normal/fresh state
```

#### Stale successful value

When a current price exists but `LAST_SUCCESS_AT` is older than `MAX_AGE_SECONDS`:

```text
price may still be shown to staff
must be visibly marked stale
last successful timestamp shown
```

Do not present a stale price as fresh.

#### Error with preserved last price

When `STATUS=error` but a previous current price exists:

```text
preserved price may be shown
must clearly indicate update error/stale operational state
last successful timestamp shown
```

Do not expose raw internal exception traces.

A concise staff label such as `Не удалось обновить` is sufficient for v1. Raw `ERROR_MESSAGE` is not required in the product card.

#### No successful price yet

When no `CURRENT_PRICE/CURRENCY/LAST_SUCCESS_AT` exists:

```text
show monitored competitor/link row
price = unavailable
clear neutral label such as “Цена ещё не получена”
```

This is useful to staff because it exposes incomplete monitoring configuration without exposing it to customers.

### 8. Freshness parameter

Support:

```text
MAX_AGE_SECONDS
```

Default:

```text
86400
```

(24 hours)

Normalize defensively to a non-negative integer.

`0` may mean “do not mark by age” if that convention is explicitly documented and tested.

Freshness affects the staff status marker only. It must not write to the database, trigger collection or delete/hide history.

Use the application/server time semantics consistently with existing Bitrix `DateTime` values.

### 9. Default UI

The default template should be compact and clearly staff-only, for example:

```text
Служебная информация
Цены конкурентов

Royal Computers      187 040 ₽
Обновлено 14:20      Открыть товар ↗

KometaPC             189 900 ₽
Обновлено 13:55      Открыть товар ↗

DNS                   Цена ещё не получена
                      Открыть товар ↗
```

Requirements:

- clear staff-only heading/marker;
- competitor name;
- exact current price/currency when available;
- last-success time where available;
- concise stale/error/no-price state;
- external product link;
- no history graph in v1;
- no own-store price comparison in v1;
- no automatic repricing;
- no alerts/notifications.

The default template may format RUB as `₽`, but business data must remain available internally as exact decimal string + currency code. Do not use float for money logic.

### 10. External link safety

Competitor URL is an exact monitored URL and must remain exact.

Output requirements:

- escape all persisted text;
- escape URL for HTML attribute context;
- open external link in a safe way, preferably `_blank`;
- use at minimum `rel="noopener noreferrer"`;
- do not rewrite/remove query parameters;
- do not normalize the monitored URL for display/navigation;
- do not expose the URL to unauthorized users.

`nofollow` is optional because this is staff-only content and must never become public/indexable. Do not use `sponsored` merely because the destination is a competitor.

### 11. Read-only frontend path

Rendering the block must never:

- run `PriceUpdateService`;
- create collector requests;
- call Mock/HTTP/external collectors;
- perform outbound HTTP;
- update `LAST_CHECK_AT`;
- update `LAST_SUCCESS_AT`;
- change `CURRENT_PRICE/CURRENCY/STATUS`;
- insert history rows;
- write module options/business data.

The frontend card is a consumer of already collected operational state only.

### 12. No public API/AJAX endpoint in v1

Do not introduce an unauthenticated or generic frontend API for competitor prices.

Do not add an AJAX endpoint merely to render this first version unless required by Bitrix composite dynamic rendering. If a dynamic endpoint/request exists as part of Bitrix composite behavior, it must enforce the same module `R/W` permission server-side before returning data.

No endpoint may accept arbitrary product IDs and return competitor prices without authorization.

### 13. Component installation and lifecycle

Add the component to normal module lifecycle.

Fresh install must install it.

Update `0.11.0 -> 0.12.0` must install/refresh it without schema/business-data changes.

Uninstall must remove only module-owned installed component files while preserving persistent module data, exactly as existing module lifecycle preserves DB state.

Reinstall must restore the component.

Use a dedicated installer helper if that keeps responsibilities clear, conceptually:

```text
ComponentInstaller
```

Do not overload `AdminInstaller` with unrelated frontend component responsibilities if a separate installer is cleaner.

### 14. No schema migration

Task 021 needs no new DB table/column/index.

Do not modify existing competitor/link/history schema.

Target version:

```text
0.12.0
```

The update package may install component files and update version metadata but must not rewrite business data.

### 15. Existing behavior must remain unchanged

Do not change semantics of:

```text
collector contracts
Mock collector
HTTP collector
external collector boundary
PriceUpdateService
CURRENT_PRICE
CURRENCY
STATUS/error preservation
LAST_CHECK_AT
LAST_SUCCESS_AT
PriceHistoryTable
Task 020 analytics
scheduler batching / named lock
CLI exit codes
Bitrix Agent
admin permissions
uninstall data preservation
```

## Tests

Add deterministic automated coverage. At minimum prove:

1. `D` access -> component returns zero competitor output.
2. Guest/no authorized user -> zero competitor output.
3. `R` access -> component may render.
4. `W` access -> component may render.
5. Authorization happens before competitor-price query.
6. Unauthorized output contains no competitor name, price, URL, timestamp, hidden JSON or `data-*` competitor payload.
7. Authorized render followed by unauthorized render cannot leak cached output.
8. Unauthorized render followed by authorized render does not suppress staff output.
9. Component/cache/composite strategy is explicitly permission-safe.
10. Invalid/missing/non-positive `PRODUCT_ID` -> no output/query.
11. Exact product filtering only; no parent/offer aggregation.
12. Active link + active competitor only.
13. Inactive link excluded.
14. Inactive competitor excluded.
15. Multiple active monitored URLs remain multiple deterministic rows.
16. Ordering is deterministic (`SORT`, name, link ID or documented equivalent).
17. Fresh successful price state is represented correctly.
18. Stale price is marked stale according to `MAX_AGE_SECONDS`.
19. Error with preserved current price remains visible to staff with safe error-state label.
20. No-success-yet link is represented without inventing a price.
21. Price remains exact decimal string for business/presentation data.
22. Competitor names and URLs are escaped.
23. Exact URL/query string is preserved.
24. External link uses safe rel/target behavior.
25. `PriceHistoryTable` is not used by the staff product-card read path.
26. Frontend render does not invoke `PriceUpdateService` or collector classes.
27. Frontend render causes no writes/timestamp/history changes.
28. Component packaging/install files exist and are included in fresh install.
29. `0.11.0 -> 0.12.0` updater installs component and performs no schema migration.
30. Uninstall removes installed component files but preserves DB data.
31. Reinstall restores component files.
32. Existing Task 019/020 tests stay green.
33. Scheduler/CLI/Agent/collector regression stays green.
34. Full PHPUnit and PHP syntax checks pass.

Tests must not depend on live Internet or a live catalog site.

## Real Bitrix smoke

After CI is green, verify on the real king-komp Bitrix installation.

### Baseline

Record before update:

```text
module version
history count
current monitored-link states
agent state
admin proxies
module rights used for administrator/manager/customer test users
```

### Update/lifecycle

1. Update `0.11.0 -> 0.12.0` through the normal update path.
2. Confirm no DB schema/history/current-state changes from update.
3. Confirm installed component files exist.
4. Confirm module remains loadable and admin functionality still works.

### Integration

5. Include `kk.pricewatch:product.prices` in one catalog detail template using an exact monitored `PRODUCT_ID`/offer ID.
6. Do not alter production competitor URLs/prices just to manufacture output.

### Permission matrix

Use distinct sessions/accounts where practical:

7. Administrator -> block visible.
8. Store manager with module `R` -> block visible.
9. User with module `D` -> block completely absent.
10. Guest -> block completely absent.

For `D` and guest inspect both rendered page and page source/network payloads: competitor names, prices and exact URLs must not be present.

### Cache/composite leakage tests

With the same product URL:

11. Visit as administrator/manager first, then as guest/customer -> no staff data leak.
12. Visit as guest/customer first, then as administrator/manager -> staff block still appears.
13. Repeat after normal page/component/composite cache warming where applicable.

### Read-only proof

14. Take a DB snapshot/hash of monitored-link current state + history count.
15. Reload product card several times as administrator and manager.
16. Compare snapshot/hash -> no changes to current state, timestamps or history.
17. Confirm no outbound competitor collection is triggered by page view.

### Operational states

18. Verify at least one successful-price row renders correctly.
19. Verify an existing no-price/error link is shown safely to staff without leaking raw technical details to ordinary users.
20. Where practical use a temporary controlled Mock link to verify stale/error-with-preserved-price state without altering RoyalPC production URLs.

### SKU contract

21. Verify the component shows only rows for the exact ID passed by the template.
22. If the tested catalog item has offers, confirm a different offer ID does not accidentally receive parent/sibling competitor rows.

### Lifecycle

23. Safe uninstall/reinstall: DB data remains, component files disappear/reappear, agent/admin lifecycle remains correct.
24. Cleanup temporary integration/smoke data.
25. Final production CLI smoke.

## Acceptance criteria

Task 021 is complete when all are true:

- module version is `0.12.0`;
- reusable `kk.pricewatch:product.prices` component exists and is installable;
- no schema migration exists;
- component accepts an exact `PRODUCT_ID` and never guesses/aggregates SKU identities;
- only module `R/W` users can obtain competitor-price data;
- `D`, customer and guest requests receive no competitor data in HTML/source/cache/dynamic payloads;
- permission check happens before data query;
- cache/composite behavior cannot leak staff data across sessions/roles;
- active monitored links for active competitors render deterministically;
- successful, stale, error-with-last-price and no-price states are represented safely;
- current price data comes only from current operational state, not history;
- frontend page views never collect or write;
- exact competitor URL is preserved and safely escaped;
- no public generic competitor-price API is introduced;
- fresh install/update/uninstall/reinstall lifecycle for the component is correct;
- existing admin/history/analytics/collector/scheduler/Agent behavior remains green;
- deterministic automated tests pass;
- real-Bitrix permission/cache/read-only smoke passes.

## Out of scope

Do not implement in Task 021:

- public customer-visible competitor prices;
- alerts/notifications;
- automatic repricing;
- own-store price comparison/margin recommendations;
- competitor history graphs in the product card;
- collection triggered from frontend;
- frontend API for arbitrary products;
- auto-discovery of competitor URLs;
- automatic SKU/offer switching via JavaScript;
- catalog-list/category-page batch widgets;
- mass comparison tables;
- export.

These can be separate later tasks if needed.

## Deliverables

- staff-only current-price read service;
- `kk.pricewatch:product.prices` Bitrix component;
- default staff-only template + RU/EN localization;
- permission-safe composite/cache strategy;
- component installer/lifecycle support;
- `0.12.0` update/version metadata;
- deterministic automated tests;
- concise integration documentation/snippet for catalog detail templates.
