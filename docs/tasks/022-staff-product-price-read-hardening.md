# Task 022 — Staff product-price read hardening v1

## Goal

Harden the already working staff-only product-card competitor-price read path for predictable load, graceful failure and production-scale operation without changing Task 021 security semantics.

Task 021 / `0.12.1` is already production-verified on real Bitrix for:

- administrator / `W` access;
- store-manager / `R` access;
- ordinary-user / `D` denial;
- guest denial;
- `admin -> guest` and `guest -> admin` composite isolation;
- exact `PRODUCT_ID` reads;
- no frontend collection/history writes;
- product-card integration on the site.

Production usage for now is **product-level**. Do not add SKU/offer aggregation, automatic offer switching or client-side SKU synchronization in this task.

Target module version: `0.13.0`.

## Security invariant

All Task 021 confidentiality rules remain mandatory.

Competitor names, prices, exact URLs and timestamps are staff-only data.

```text
D / guest -> no competitor data
R / W     -> may read/render competitor data
```

Authorization must remain before any competitor-price ORM read.

Do not weaken the composite/dynamic-frame solution proven in `0.12.1`.

## Required context

Before implementation read:

- `AGENTS.md`
- `.agents/skills/implement-task/SKILL.md`
- `.agents/skills/code-review/SKILL.md`
- `docs/tasks/021-staff-only-product-prices.md`
- `lib/Admin/Access.php`
- `lib/Model/CompetitorTable.php`
- `lib/Model/ProductCompetitorTable.php`
- `lib/Service/StaffProductPriceRepositoryInterface.php`
- `lib/Service/OrmStaffProductPriceRepository.php`
- `lib/Service/StaffProductPriceReadService.php`
- `install/components/kk.pricewatch/product.prices/*`
- `lib/Installer/SchemaInstaller.php`
- current installer/update/version/package-builder files

Use `$implement-task`.

## Scope

### 1. Bound the staff read path

The current ORM read uses `fetchAll()` without an explicit limit. Make the product-card read bounded.

Add component parameter:

```text
MAX_ROWS
```

Rules:

```text
default = 50
minimum = 1
maximum = 200
```

Invalid values fall back to `50`.

The limit must be enforced below the component as well, so direct service/repository use cannot accidentally become unbounded.

Do not load an unbounded result and then `array_slice()` it.

The ORM query itself must be bounded.

Recommended approach:

```text
requested MAX_ROWS = N
repository query limit = N + 1
first N rows -> render
row N+1 exists -> HAS_MORE = true
```

This permits truncation detection without an additional `COUNT(*)` query.

Never issue a second count query merely to display the total number of links.

### 2. Structured read result

Return enough structured state to distinguish normal and truncated results cleanly.

A small value object is preferred, conceptually:

```text
StaffProductPriceReadResult
  rows: list<array>
  has_more: bool
```

Exact naming may follow repository conventions.

Preserve existing row semantics:

```text
link_id
competitor_id
competitor_name
exact_url
is_url_safe
current_price|null
currency|null
status
last_check_at|null
last_success_at|null
is_stale
```

Money must remain decimal string data. No float business logic.

### 3. Repository query contract

`OrmStaffProductPriceRepository` must continue to perform one deterministic ORM query for the exact requested product.

Required filters remain:

```text
PRODUCT_ID = exact requested ID
ProductCompetitor.ACTIVE = Y
Competitor.ACTIVE = Y
```

Required order remains:

```text
Competitor SORT ASC
Competitor NAME ASC
ProductCompetitor ID ASC
```

Apply the bounded limit at ORM query level.

No N+1 reads.

No `PriceHistoryTable` access.

No collector/service calls.

No writes.

### 4. Add a read-path index

Add an additive index to `b_kk_pricewatch_product_competitor` for the staff product-card read path:

```text
ix_kk_pw_pc_product_active_competitor
(PRODUCT_ID, ACTIVE, COMPETITOR_ID)
```

Requirements:

- create through normal `SchemaInstaller` idempotently;
- do not drop or rewrite existing indexes;
- keep existing unique identity index unchanged;
- do not modify business rows;
- repeated installer/update execution must be safe.

The existing `ix_kk_pw_pc_product` may remain. Do not remove it in this task.

The new index is intended to narrow active links for one product and provide the joined competitor key efficiently. The final sort still depends on competitor `SORT/NAME`; do not add speculative indexes attempting to solve cross-table ordering.

### 5. Graceful frontend failure isolation

The optional competitor-price block must never take down the catalog product page because the module read path fails.

For an authorized `R/W` request, isolate failures around the staff read operation.

If the read throws:

```text
product page continues rendering
ROWS = empty
READ_FAILED = true
no exception trace/raw SQL/raw error message in HTML
```

The template may show a neutral staff-only state, e.g.:

```text
Цены конкурентов временно недоступны
```

Only authorized staff may see this state.

For `D`/guest the dynamic frame remains completely empty even when the module/read path is unhealthy.

Do not expose exception messages, SQL, file paths or stack traces to the product page.

No new error-log database table is required. Server-side logging through an existing Bitrix/PHP mechanism is allowed but not required by this task.

### 6. Truncation UI

When `HAS_MORE = true`, show a concise staff-only notice such as:

```text
Показаны первые 50 связей мониторинга
```

Do not claim a total count because this task deliberately avoids an extra count query.

The notice must not appear for normal untruncated results.

Keep it localized RU/EN.

### 7. Keep cross-request caching disabled

Do not add component result cache, tagged data cache or another cross-request cache in this task.

Rationale:

- the block is staff-only;
- unauthorized requests already perform zero competitor ORM reads;
- authorized rendering should remain one small bounded query;
- current price/status changes frequently enough that cache invalidation would add unnecessary complexity;
- Task 021 security/composite isolation is already proven.

Explicitly preserve:

```text
no StartResultCache
no shared HTML cache
permission check before read
composite dynamic frame in template
```

If future measurement proves a cross-request cache is needed, design it as a separate task with explicit invalidation rules.

### 8. Product-level production usage

Do not implement SKU/offer support in Task 022.

The component keeps its exact-ID technical contract, but production documentation should state that the current king-komp/air-msk integration passes the parent product ID.

Do not:

- inspect `OFFERS` / `JS_OFFERS`;
- react to offer-selection JS;
- aggregate parent + offer links;
- infer an offer from request data;
- add AJAX endpoints for SKU switching.

### 9. Site-specific layout stays outside the module

Do not add king-komp/air-msk fixed positioning to the module default CSS.

The production site currently positions the block through site-template CSS. That is intentional.

The module template should remain reusable and layout-neutral.

Do not add module CSS such as production-specific:

```text
position: fixed
right: 0
top: ...
z-index: ...
```

unless it is generic component styling unrelated to site placement.

### 10. Update and versioning

Target version:

```text
0.13.0
```

Add a normal `0.13.0` updater.

The updater must:

- ensure the new additive read index exists;
- refresh component files/localization/parameters as needed;
- update version metadata;
- preserve all competitor/link/history business data;
- not run collection;
- not create history rows;
- not alter current prices/status/timestamps.

Using idempotent `SchemaInstaller` + `ComponentInstaller` is acceptable if the update remains narrowly scoped and safe.

### 11. Existing semantics remain unchanged

Do not change:

```text
collector contract
Mock collector
HTTP collector
PriceUpdateService
scheduler / CLI / Agent
CURRENT_PRICE/CURRENCY semantics
error preservation
LAST_CHECK_AT/LAST_SUCCESS_AT
price-history append rules
Task 020 analytics
exact URL identity/hash rules
module D/R/W rights
Task 021 safe-link policy
Task 021 composite permission isolation
uninstall data preservation
```

## Out of scope

Do not add in Task 022:

- public customer competitor prices;
- SKU/offer switching;
- category/list-page competitor prices;
- bulk frontend API;
- AJAX price endpoint;
- alerts/notifications;
- automatic repricing;
- competitor-price history graph on product card;
- own-price vs competitor calculations;
- cross-request cache;
- new collector types.

## Tests

Add deterministic automated coverage. At minimum prove:

1. invalid/non-positive `PRODUCT_ID` -> zero read/query;
2. `D`/guest -> zero competitor read and zero data output;
3. `R/W` -> read allowed;
4. permission check still precedes repository/service read;
5. default `MAX_ROWS=50`;
6. invalid `MAX_ROWS` -> `50`;
7. values below minimum normalize/fall back safely according to documented behavior;
8. values above `200` cannot request more than `200` rows;
9. repository query receives a bounded limit;
10. service requests at most `MAX_ROWS + 1` rows;
11. exactly `MAX_ROWS` rows -> `HAS_MORE=false`;
12. `MAX_ROWS + 1` rows -> only first `MAX_ROWS` returned and `HAS_MORE=true`;
13. no extra count query is introduced;
14. deterministic ordering contract remains unchanged;
15. active-link/active-competitor filtering remains unchanged;
16. exact product filtering remains unchanged;
17. multiple monitored URLs remain separate rows;
18. decimal price strings remain exact;
19. stale/error/no-price semantics remain unchanged;
20. valid exact http/https URL remains unchanged;
21. unsafe URL remains non-clickable;
22. authorized read failure does not escape from the component boundary;
23. authorized read failure produces only a neutral staff-safe state;
24. denied/guest failure path still renders no staff/error data;
25. component class does not call `createFrame()`;
26. template retains the composite dynamic frame;
27. no `StartResultCache` / shared output cache;
28. no `PriceHistoryTable` in the frontend read path;
29. no `PriceUpdateService` / collector invocation in frontend path;
30. no frontend business-data writes;
31. `SchemaInstaller` contains idempotent index `ix_kk_pw_pc_product_active_competitor` on `PRODUCT_ID, ACTIVE, COMPETITOR_ID`;
32. existing indexes are not dropped;
33. `0.13.0` updater performs only safe schema/index + component refresh work;
34. update package includes the new updater/component/version files;
35. Task 019/020/021 regression tests remain green;
36. full PHPUnit passes;
37. PHP syntax checks pass.

Tests must not depend on live Internet or a live Bitrix catalog site.

## Real Bitrix smoke

After CI is green, verify on the real installation.

### Baseline before update

Record:

```text
module version = 0.12.1
current production monitored-link states
history total and per-link counts
agent state
admin proxies
existing product-competitor indexes
```

Do not assume prices are unchanged from an earlier day; capture the immediate pre-update state and compare against that snapshot.

### Update

1. Update `0.12.1 -> 0.13.0` normally.
2. Confirm module version `0.13.0`.
3. Confirm component files match module source.
4. Confirm new index exists exactly once:

```text
ix_kk_pw_pc_product_active_competitor
(PRODUCT_ID, ACTIVE, COMPETITOR_ID)
```

5. Confirm existing unique/history indexes remain.
6. Confirm update did not change production link state/history counts.
7. Confirm Agent/admin proxies remain correct.

### Product-card regression

Using the existing production product-level integration:

8. Administrator / `W` -> block visible.
9. Manager / `R` -> block visible.
10. `D` user -> block absent.
11. Guest/incognito -> block absent.
12. Recheck one `admin -> guest` and one `guest -> admin` sequence; no composite leakage/suppression.
13. Product page layout remains intact; site-specific positioning continues to come from the site template CSS, not module CSS.

### Query-plan observation

Run an `EXPLAIN` for the SQL-equivalent of the product-card read with an explicit bounded `LIMIT` and record the plan.

Because the production table may be very small, **do not fail the smoke solely because MySQL chooses a table scan instead of the new index**. The hard acceptance requirement is that the intended index exists and the actual frontend query is bounded to one exact product and a finite limit.

### Final acceptance

Task 022 passes when:

```text
security matrix unchanged
composite isolation unchanged
read query bounded
row truncation deterministic
frontend read failure cannot break product page
new read index installed idempotently
no business/history mutation from update/render
full CI green
real product-card smoke green
```
