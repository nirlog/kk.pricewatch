# Task 019 — Price history v1

## Goal

Add durable append-only price-change history to `kk.pricewatch` without changing the existing meaning of `ProductCompetitorTable` as the current operational state.

Target flow:

```text
Manual admin check / Scheduled runner / Bitrix Agent
                    |
                    v
            PriceUpdateService
                    |
             successful item
                    |
                    v
     atomic per-link persistence boundary
          |                     |
          v                     v
ProductCompetitorTable     PriceHistoryTable
 current state             immutable history
```

After this task the module must preserve the current price/status/timestamps exactly as before and additionally record a historical row when a successful collection establishes a new baseline or changes the effective price/currency for the current monitored-link identity.

This task is the storage/history milestone. It is **not** a notifications, charting, alert rules, retention-policy, aggregation, export, external collector or browser automation milestone.

Target module version: `0.10.0`.

## Baseline

Task 018 / module `0.9.1` is verified on real Bitrix for:

- live RoyalPC HTTP collection;
- DOM/XPath extraction;
- successful persistence;
- stale-price preservation after item errors;
- recovery after collector errors;
- manual admin checks;
- scheduler/CLI execution;
- Bitrix Agent execution;
- DB named-lock contention;
- wrong-domain and HTTP-status failures;
- mixed item success/failure batches;
- mock collector regression;
- external collector isolation;
- uninstall/reinstall with preserved monitoring data.

Task 019 must not regress those paths.

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
- `lib/Model/ProductCompetitorTable.php`
- `lib/Service/PriceUpdateService.php`
- `lib/Installer/SchemaInstaller.php`
- current admin product/competitor pages
- current update-package/version files

Use `$implement-task`.

## Architectural decisions

### 1. Keep current state and history separate

`ProductCompetitorTable` remains the single current operational-state row for a monitored link.

Do not turn it into an event log and do not add repeated-price history columns to it.

Introduce a dedicated ORM entity conceptually named:

```text
KK\PriceWatch\Model\PriceHistoryTable
```

with physical table:

```text
b_kk_pricewatch_price_history
```

The history table is module-owned, append-only business data. Normal collection flows must insert history rows but never update old history rows in place.

Historical rows must survive:

- later price changes;
- collector errors;
- monitored-link URL/competitor edits;
- monitored-link deactivation;
- safe module uninstall/reinstall.

Do not cascade-delete history merely because the current monitored link changes or is later removed.

### 2. History row schema

At minimum store these fields:

```text
ID                       integer, primary, autoincrement
PRODUCT_COMPETITOR_ID    integer, required
PRODUCT_ID               integer, required snapshot
COMPETITOR_ID            integer, required snapshot
URL                      text, required exact snapshot
URL_HASH                 char/varchar(64), required snapshot
PRICE                    decimal(18,2), required
CURRENCY                 char/varchar(3), required
COLLECTED_AT             datetime, required
```

Rules:

- `PRODUCT_COMPETITOR_ID` identifies the monitored-link row that produced the observation.
- `PRODUCT_ID`, `COMPETITOR_ID`, `URL` and `URL_HASH` are snapshots taken at collection time, not live joins used as the only historical identity.
- `URL` must preserve the exact persisted URL bytes/query string; do not normalize or rebuild it.
- `URL_HASH` must use the same SHA-256 identity rule as `ProductCompetitorTable`.
- `PRICE` uses the same decimal semantics as the current operational price; never use float arithmetic.
- `CURRENCY` uses the same uppercase three-letter validation as current state.
- `COLLECTED_AT` is the same successful collection timestamp used for `LAST_SUCCESS_AT` for that persistence operation.

Do not add collector-specific fields or RoyalPC-specific fields.

Optional ORM `Reference` fields are acceptable for convenience, but there must be no destructive DB foreign-key cascade dependency on the current link or competitor rows.

### 3. Indexes

Create indexes suitable for the first read paths. At minimum:

```text
(PRODUCT_COMPETITOR_ID, COLLECTED_AT)
(PRODUCT_ID, COLLECTED_AT)
(COMPETITOR_ID, COLLECTED_AT)
(PRODUCT_COMPETITOR_ID, URL_HASH, COLLECTED_AT)
```

Exact index names may follow existing repository naming conventions.

Do not create a uniqueness rule on price/currency that would prevent a legitimate sequence such as:

```text
100000 -> 95000 -> 100000
```

from recording the final return to `100000`.

### 4. v1 records price changes, not every successful poll

Task 019 history is a compact price-change history, not a raw observation log.

For the current monitored-link identity, create a history row when:

1. there is no previous history row for that identity and a collection succeeds; or
2. the new successful `(PRICE, CURRENCY)` differs from the latest history row for that identity.

Do **not** create another row when a later successful check returns the same price and currency as the latest history row.

Identity for this rule is the current snapshot combination represented by the monitored link, including at least:

```text
PRODUCT_COMPETITOR_ID
PRODUCT_ID
COMPETITOR_ID
URL_HASH
```

Therefore changing the exact URL or competitor creates a new history baseline after the next successful collection, even if the numerical price happens to equal the previous identity's last price.

Required examples:

```text
first success 100000 RUB       -> insert
next success 100000 RUB        -> no insert
next success 95000 RUB         -> insert
next success 95000 RUB         -> no insert
next success 100000 RUB        -> insert
URL changes, then 100000 RUB   -> insert new baseline for new identity
```

Errors and skipped links never create history rows.

### 5. Integrate history at the success persistence boundary

History is not a collector concern.

Do not modify `CollectorInterface`, `CollectorRequest`, `CollectorResponse`, HTTP extraction or mock behavior to support history.

The integration point belongs in the existing success persistence path owned by `PriceUpdateService` (or a narrow injected persistence/recorder collaborator used by it).

A design such as this is acceptable:

```text
PriceUpdateService
    -> PriceHistoryRecorder / SuccessPersistenceService
        -> ProductCompetitorTable
        -> PriceHistoryTable
```

Keep the collaborator narrow and testable.

### 6. Current-state update and history insert are atomic per successful link

A successful collection must not leave the database in a state where current operational data says the new price was persisted but the required history change row failed, or vice versa.

For each successful item persistence:

- open a short DB transaction;
- serialize persistence for that monitored-link row at the DB row level or an equivalent per-link mechanism;
- re-read the current link identity/state inside that persistence boundary;
- decide whether a new history row is required;
- insert the history row when required;
- update `ProductCompetitorTable` current state;
- commit only when all required writes succeed;
- rollback on any persistence exception/error.

Do **not** hold DB row locks while performing network requests or HTML parsing. Locking is only for the short persistence phase after the collector result is already available.

Do not reuse the global scheduled-run lock for manual persistence. Manual checks must remain independent of the scheduler lock.

### 7. Concurrency / duplicate suppression

Manual checking and scheduled execution can overlap, so two successful writers for the same link may race.

The implementation must prevent duplicate history rows for the same unchanged transition under concurrent persistence.

Use a short per-link serialized persistence boundary, for example a transaction plus row-level locking of the current `ProductCompetitorTable` row before comparing the latest history/current state.

Equivalent safe approaches are acceptable if they preserve these properties:

- no global module-wide persistence lock;
- no network work while holding the lock;
- two overlapping successful checks that both return the same new value produce one history change row, not two;
- a later genuine `A -> B -> A` sequence still records all three effective states.

If exact Bitrix DB APIs are used for row locking, verify them against the supported runtime and keep SQL strictly internal with integer IDs/table constants, not user-provided fragments.

### 8. Persistence failure semantics

Existing `PriceUpdateOutcome::PERSISTENCE_FAILURE` behavior remains the failure contract.

If history persistence fails during a success operation:

- rollback the corresponding current-state write;
- return `PERSISTENCE_FAILURE` / `PERSISTENCE_ERROR` through the existing service outcome semantics;
- do not convert it to a collector/item error;
- do not write a partial history row;
- do not expose raw SQL/internal exception text to the admin user.

Existing collector errors continue to update only operational error state and preserve stale successful price/history.

### 9. Editing a monitored link must not rewrite history

`ProductCompetitorLinkService` currently clears operational state when competitor or exact URL changes. Preserve that behavior.

Do not update/delete prior history snapshots when:

- `COMPETITOR_ID` changes;
- exact `URL` changes;
- `ACTIVE` changes.

After an identity-changing edit, the next successful collection establishes a new history baseline with the new identity snapshot.

### 10. Read-only admin history page

Add a read-only admin page, conceptually:

```text
/bitrix/admin/kk_pricewatch_price_history.php
```

and its module proxy/source following existing admin lifecycle conventions.

Minimum v1 behavior:

- accessible to module rights `R` and `W`;
- denied for `D`;
- no write/mutation actions;
- link from the existing product competitor list for each monitored link, labelled `История` / `History`;
- support filtering by `PRODUCT_ID` and/or `PRODUCT_COMPETITOR_ID` from the navigation context;
- deterministic newest-first order by `COLLECTED_AT`, then `ID`;
- Bitrix admin pagination;
- columns at minimum: date/time, link ID, competitor, exact URL snapshot, price, currency;
- escape all persisted text/URLs before HTML output;
- preserve existing admin navigation back to the monitored-product links page.

A global dashboard, chart, CSV export and bulk delete are out of scope.

### 11. No charting in Task 019

Do not add JS chart libraries, graph rendering, trend percentages, alerts or thresholds in this task.

The history table and read-only list are the foundation for a later chart/alert task.

### 12. Installer and update package

Bump module version to:

```text
0.10.0
```

Because Task 019 adds a new physical table/indexes, this release requires real update lifecycle work.

Update `SchemaInstaller` so a fresh installation creates the history table and required indexes idempotently.

Add:

```text
install/updates/0.10.0/updater.php
install/updates/0.10.0/description.ru
install/updates/0.10.0/description.en
```

The updater must create/ensure the new schema idempotently for an existing installation without deleting or rewriting current monitoring data.

Reuse the module's schema installer/dedicated idempotent schema logic rather than duplicating divergent raw DDL when practical.

Safe uninstall/reinstall must continue to preserve module-owned data, including history.

### 13. Existing behavior must remain unchanged

Task 019 must not change the semantics of:

```text
CURRENT_PRICE
CURRENCY
STATUS
ERROR_CODE
ERROR_MESSAGE
LAST_CHECK_AT
LAST_SUCCESS_AT
```

Specifically:

- successful collection still updates current price/currency and both success/check timestamps;
- item/collector error still preserves stale successful price/currency/history and only updates the error/check state as already defined;
- skipped links do not alter current state or history;
- exact URLs remain opaque identities;
- mock/http/external factory behavior remains unchanged;
- scheduler batching/locking/CLI exit behavior remains unchanged;
- Bitrix Agent behavior remains unchanged.

## Tests

Add deterministic automated coverage. At minimum prove:

1. `PriceHistoryTable` metadata matches required physical types/validation.
2. Fresh `SchemaInstaller` creates the history table and required indexes idempotently.
3. `0.10.0` update package contains the required updater/descriptions/version metadata.
4. First successful collection for a link identity creates exactly one history row.
5. Repeating the same price/currency success does not create another row.
6. A price change creates a new row.
7. A currency change creates a new row.
8. Sequence `A -> B -> A` records three effective states.
9. Collector/item error creates no history row and preserves existing history.
10. Skipped inactive/missing links create no history row.
11. URL identity change preserves old history and the next success creates a new baseline row with the new exact URL/URL_HASH snapshot.
12. Competitor change preserves old history and the next success creates a new baseline row.
13. Required history insert failure rolls back the current successful-state update and produces `PERSISTENCE_FAILURE`.
14. Concurrent/equivalent overlapping persistence of the same unchanged new value does not create duplicate history transition rows.
15. Manual price check still succeeds and history behavior is identical to scheduled execution because both use the same `PriceUpdateService` path.
16. HTTP collector regression remains green.
17. Mock collector regression remains green.
18. Scheduler/CLI tests remain green.
19. Agent lifecycle tests remain green.
20. Admin history page enforces `R/W` read access, denies `D`, is read-only, escapes output and paginates.
21. Uninstall/reinstall preserves history rows.
22. Full PHPUnit suite and PHP syntax checks pass.

Tests must not depend on live Internet or a live RoyalPC price.

## Real Bitrix smoke

After CI is green, verify on the real Bitrix environment.

Use the existing RoyalPC monitored link as the primary smoke source.

Record before testing:

```text
module version
PHP version
Bitrix main version
link ID
product ID
competitor ID
exact URL
current price/currency
date/time
```

Required smoke sequence:

1. Update module from `0.9.1` to `0.10.0` through the normal update-package path.
2. Confirm `b_kk_pricewatch_price_history` exists with required indexes.
3. Confirm existing competitor/link data is unchanged.
4. Run RoyalPC success once and confirm first history baseline row is created.
5. Run RoyalPC success again at the same price and confirm no duplicate history row is added.
6. Use deterministic Mock configuration or a temporary controlled test link to produce price `A`, then `B`, then `A`; confirm three change rows in order.
7. Produce an item error and confirm history count/value rows do not change while operational error state behaves as before.
8. Restore success and confirm unchanged price does not duplicate the latest history row.
9. Open the admin history page from the monitored-link list; verify newest-first display, exact URL snapshot, price/currency and pagination/navigation.
10. Run scheduled CLI and confirm history rules are identical to manual execution.
11. Run the Bitrix Agent adapter and confirm the same behavior.
12. Verify scheduler lock regression remains correct.
13. Perform safe uninstall/reinstall and confirm history table/data survives and the agent/admin proxies return correctly.
14. Run one final CLI collection after reinstall and confirm current state/history remain valid.

Do not permanently alter the production RoyalPC URL merely to manufacture price changes. Use Mock or another controlled temporary test link for deterministic `A -> B -> A` smoke where needed.

## Acceptance criteria

Task 019 is complete when all of the following are true:

- module version is `0.10.0`;
- a dedicated append-only history ORM/table exists;
- successful collections create compact price-change history;
- unchanged repeated successes do not create duplicate rows;
- legitimate `A -> B -> A` changes are preserved;
- exact link identity snapshots are retained historically;
- errors/skips do not create history;
- current-state + required history persistence is atomic per link;
- overlapping manual/scheduled persistence cannot create duplicate unchanged transitions;
- current operational semantics remain unchanged;
- read-only admin history view works with Bitrix permissions/pagination/escaping;
- fresh install and `0.9.1 -> 0.10.0` update both create the schema safely;
- safe uninstall/reinstall preserves history;
- existing Mock/HTTP/scheduler/Agent behavior remains green;
- deterministic automated tests pass;
- real Bitrix smoke passes.

## Out of scope / follow-up

Defer to later tasks:

- chart/graph UI;
- percent change/trend calculations;
- price-drop alerts and notification channels;
- retention/compaction policies;
- daily/hourly aggregate tables;
- CSV/XLSX export;
- comparison dashboards across competitors;
- queue/workers;
- external/browser collector implementation.
