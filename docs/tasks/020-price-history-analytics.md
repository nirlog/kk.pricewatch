# Task 020 — Price history analytics v1

## Goal

Turn the durable history introduced in Task 019 into a useful, read-only analytics view for a single monitored product/competitor link without changing collection or persistence semantics.

Target flow:

```text
Product competitor list
        |
        | History
        v
kk_pricewatch_price_history.php
        |
        +--> current monitored-link identity
        |        |
        |        v
        |   analytics summary
        |   + server-rendered chart
        |
        +--> full immutable history table
                 |
                 v
          PriceHistoryTable
```

The existing history table remains the source of truth. Task 020 adds presentation/query logic only: summary metrics and a compact price-history chart for the current live identity of the selected monitored link.

This task is **not** an alert-rules, notification, Telegram/email, automatic repricing, product-price comparison, retention-policy, CSV export, raw observation log, external collector or browser-automation milestone.

Target module version: `0.11.0`.

## Baseline

Task 019 / module `0.10.0` is verified on real Bitrix for:

- append-only compact price-change history;
- first-success baseline recording;
- unchanged-price deduplication;
- `A -> B -> A` preservation;
- collector errors creating no history;
- recovery without duplicate history;
- exact URL / URL_HASH identity snapshots;
- identity change preserving old history and creating a new baseline;
- per-link atomic current-state + history persistence;
- read-only admin history list;
- manual, CLI scheduler and Bitrix Agent integration;
- uninstall/reinstall preserving history;
- deletion of a live link/competitor not cascading into history;
- production cleanup after smoke testing.

Task 020 must not regress any of those behaviors.

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
- `lib/Model/ProductCompetitorTable.php`
- `lib/Model/PriceHistoryTable.php`
- `lib/Service/PriceHistoryDecision.php`
- `admin/product_competitors.php`
- `admin/price_history.php`
- current installer/update/version files

Use `$implement-task`.

## Architectural decisions

### 1. Read-only analytics only

Task 020 must not modify collection, current-state persistence, history recording or scheduler behavior.

The analytics page/service must never write to:

```text
b_kk_pricewatch_competitor
b_kk_pricewatch_product_competitor
b_kk_pricewatch_price_history
```

Opening or refreshing analytics must not trigger collection or change timestamps/history.

### 2. Analytics scope is the current live monitored-link identity

The full history table can contain multiple historical identities for one `PRODUCT_COMPETITOR_ID` because Task 019 preserves history after URL/competitor changes.

Do not connect unrelated identities into one chart.

When the page has a valid live `PRODUCT_COMPETITOR_ID`, derive the current identity from `ProductCompetitorTable` and scope analytics to:

```text
PRODUCT_COMPETITOR_ID
PRODUCT_ID
COMPETITOR_ID
URL_HASH
```

The existing history table below analytics continues to show all rows allowed by the page filters, including older identities.

If the requested link no longer exists, immutable history remains readable but current-identity analytics are unavailable. Show a clear read-only message; do not guess an identity from the latest historical row.

### 3. Dedicated read model/service

Prefer a narrow deterministic read service, conceptually:

```text
KK\PriceWatch\Service\PriceHistoryAnalyticsService
KK\PriceWatch\Service\PriceHistoryAnalyticsResult
```

Exact names may follow repository conventions.

Keep analytics querying/decision rules out of the admin template where practical.

For the current identity expose at minimum:

```text
point count
first effective price + timestamp
latest effective price + timestamp
previous effective price + timestamp (when present)
minimum price
maximum price
currency
latest-vs-previous direction: up/down/unchanged-or-unavailable
```

Business price comparison must keep decimal-string semantics. Do not use float arithmetic to decide equality, direction, min or max.

### 4. Effective ordering rules

Task 019 established append/commit order through history `ID` under the serialized per-link persistence boundary.

Use:

```text
ID ASC
```

for effective first/latest/previous state decisions.

For timeline display use:

```text
COLLECTED_AT ASC, ID ASC
```

so equal-second timestamps remain deterministic.

Do not use timestamp order alone to decide the effective latest state.

### 5. Currency safety

A current identity can contain multiple currencies because currency changes are valid effective transitions.

Do not draw one continuous price line across currencies.

For v1:

- one currency -> normal summary + chart;
- multiple currencies -> keep table readable and show explicit mixed-currency warning;
- do not render a misleading connected price chart;
- cross-currency min/max/direction must be unavailable rather than implicitly compared;
- do not perform FX conversion.

### 6. Server-rendered dependency-free chart

Add a compact chart to the existing history admin page for a valid, single-currency current identity.

Prefer deterministic inline SVG or an equivalent dependency-free Bitrix-native rendering.

Requirements:

- no CDN;
- no new JS chart runtime dependency;
- no Composer dependency solely for charting;
- responsive inside Bitrix admin content;
- deterministic markup/data for tests;
- chart is supplementary; history table remains authoritative;
- accessible title/description or text fallback;
- zero points -> explicit empty state;
- one point -> valid single-point rendering;
- equal prices -> no division-by-zero;
- equal timestamps -> deterministic ordering using ID.

Numeric conversion may be used only for SVG coordinate scaling. It must not influence persistence, price comparison, min/max, direction, deduplication or displayed exact monetary values.

### 7. Bound chart rendering cost

Do not render unbounded SVG for arbitrary history sizes.

Use a documented chart point cap, recommended:

```text
500 effective points
```

If current-identity history exceeds the cap:

- summary metrics still use the complete identity history;
- chart may show only the latest capped window;
- UI states that only latest N points are plotted;
- ordering stays deterministic;
- stored history is never deleted/aggregated.

### 8. Existing history page remains read-only and permission-correct

Keep:

```text
/bitrix/admin/kk_pricewatch_price_history.php
```

Permissions remain:

```text
R -> view
W -> view
D -> denied
```

Do not add mutation actions.

Escape persisted names, URLs and any other persisted text before HTML output.

### 9. Navigation and UX

The existing `История` / `History` action remains the main entry point.

For `PRODUCT_ID + PRODUCT_COMPETITOR_ID` context show, in order:

1. current monitored-link/competitor context;
2. analytics summary;
3. chart / empty / mixed-currency state;
4. existing paginated immutable history table;
5. existing back navigation.

For product-only/global history without one valid link ID:

- preserve current history-table behavior;
- do not invent a multi-link aggregate chart;
- optionally show a concise message that analytics require a specific monitored link.

### 10. No alerts in Task 020

Do not add:

- threshold rules;
- percent-change rules;
- email/Telegram/Bitrix notifications;
- webhooks;
- alert queue/state tables;
- automatic actions when price changes.

Keep the analytics service clean enough that Task 021 can consume read data later without coupling alerts to the admin chart page.

### 11. No own-store price comparison yet

Do not compare competitor history to the Bitrix catalog selling price in this task.

No margin, repricing recommendation, price-type selection or own-price historical semantics belong here.

### 12. No schema migration

Task 020 introduces no persistent table or column.

Do not alter Task 019 history schema/indexes or backfill/rewrite history.

Bump module version to:

```text
0.11.0
```

Provide normal update-package metadata required by repository conventions. If an updater entry point is required, it must be idempotent and must not modify business data. Re-ensuring admin proxies is acceptable when required by current installer/update conventions.

Fresh install and `0.10.0 -> 0.11.0` update must expose identical analytics UI.

### 13. Existing behavior must remain unchanged

Do not change semantics of:

```text
CURRENT_PRICE
CURRENCY
STATUS
ERROR_CODE
ERROR_MESSAGE
LAST_CHECK_AT
LAST_SUCCESS_AT
PriceHistoryTable append rules
PriceUpdateService outcomes
scheduler batching / named lock
CLI exit codes
Bitrix Agent
Mock collector
HTTP collector
external collector isolation
```

## Tests

Add deterministic automated coverage. At minimum prove:

1. No current-identity history returns explicit empty analytics.
2. One point produces valid baseline summary and chart data.
3. `A -> B -> A` returns correct first/latest/previous/min/max/direction.
4. Effective latest/previous uses `ID` order even with equal `COLLECTED_AT`.
5. Timeline order is `COLLECTED_AT ASC, ID ASC`.
6. Older URL_HASH identity rows of the same link are excluded from current-identity analytics.
7. Previous competitor identity rows are excluded.
8. Deleted live link keeps full history readable but current analytics unavailable.
9. Repeated unchanged success adds no history and does not change effective analytics.
10. Single-currency history produces chart data.
11. Mixed-currency identity emits warning and no misleading connected chart.
12. Business money comparisons are string/decimal-safe, not float-based.
13. Equal prices do not break chart scaling.
14. Equal timestamps remain deterministic.
15. Chart point cap is deterministic while summary uses full identity history.
16. Persisted analytics/table text is escaped.
17. `R` and `W` can view; `D` is denied.
18. Product-only/global history remains functional without a fake aggregate chart.
19. Existing history pagination/filter/order remains green.
20. Task 019 persistence tests remain green.
21. HTTP collector regression remains green.
22. Mock collector regression remains green.
23. Scheduler/CLI tests remain green.
24. Agent lifecycle tests remain green.
25. `0.11.0` update/version package tests pass and prove no history schema rewrite.
26. Full PHPUnit suite and PHP syntax checks pass.

Tests must not depend on live Internet, live RoyalPC prices, browser timing or external chart services.

## Real Bitrix smoke

After CI is green, verify on the real Bitrix environment.

Record before testing:

```text
module version
PHP version
Bitrix main version
history row count
RoyalPC link IDs/current prices
admin proxy state
agent state
```

Required sequence:

1. Update `0.10.0 -> 0.11.0` through the normal update-package path.
2. Confirm history schema/indexes and existing history row count are unchanged.
3. Open a RoyalPC link with one baseline row; verify one-point summary/chart state.
4. Reload analytics and prove current-state timestamps and history count do not change merely from viewing.
5. Create temporary controlled Mock competitor/link and collect `A -> B -> A` without touching production RoyalPC URLs.
6. Verify summary: point count 3, first A, previous B, latest A, correct min/max and direction, correct chart order.
7. Repeat same A and verify no new history/effective point.
8. Change temporary exact URL; old identity stays in table, current analytics becomes empty until new identity first success, then shows one baseline point only.
9. Exercise controlled mixed-currency current identity and verify warning/no connected cross-currency chart.
10. Verify product-only/global history view still works without a chart.
11. Verify normal W access and, where practical, R view / D denial.
12. Run scheduler CLI; unchanged production prices must not add history and analytics remain correct.
13. Run Bitrix Agent adapter; same non-regression.
14. Safe uninstall/reinstall; history, analytics page/proxy and agent lifecycle remain correct.
15. Delete temporary smoke link/competitor; confirm Task 019 history-preservation behavior, then remove only synthetic smoke history with strict safety checks.
16. Final production CLI smoke.

Do not permanently alter production RoyalPC URL/configuration/history to manufacture chart points.

## Acceptance criteria

Task 020 is complete when all are true:

- module version is `0.11.0`;
- no new business-data table/column exists;
- existing history page shows read-only analytics for a valid selected live link;
- analytics uses only the current exact monitored-link identity;
- older identities remain visible in immutable history but are not joined into the current chart;
- deleted links do not cause guessed analytics;
- summary first/latest/previous/min/max/direction is deterministic and decimal-safe;
- mixed currencies are never numerically connected/compared as one series;
- dependency-free chart handles 0/1/equal-price/equal-time cases;
- chart rendering is bounded;
- R/W/D permissions and escaping remain correct;
- opening analytics never triggers collection or writes data;
- no alerting or own-store repricing logic is introduced;
- `0.10.0 -> 0.11.0` update is safe and non-destructive;
- existing history/collector/scheduler/Agent behavior remains green;
- deterministic automated tests pass;
- real-Bitrix smoke passes.

## Deliverables

- analytics read service/result model;
- updated read-only history admin UI with summary/chart states;
- RU/EN localization;
- `0.11.0` version/update metadata;
- automated tests;
- any concise developer/admin documentation required by repository conventions.
