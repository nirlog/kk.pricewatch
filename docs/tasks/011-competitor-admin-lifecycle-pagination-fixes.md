# Task 011 — Competitor admin lifecycle and pagination fixes

## Goal

Correct two issues discovered during review of Task 010 / PR #9 before real-Bitrix admin smoke testing:

1. uninstall currently treats `DeleteDirFiles()` as a boolean-returning API even though standard Bitrix `DeleteDirFiles()` does not provide a success boolean, which can make uninstall throw after deleting admin proxy files and before unregistering the module;
2. the competitor list uses `CAdminResult::NavStart()` around an already-executed unbounded D7 ORM query, so visual pagination is not guaranteed to be true database-side `LIMIT/OFFSET` pagination.

This is a corrective task only. Do not add new product functionality.

## Required context

Before implementation read:

- `AGENTS.md`
- `.agents/skills/implement-task/SKILL.md`
- `.agents/skills/code-review/SKILL.md`
- `docs/tasks/010-competitor-admin-crud.md`
- `docs/competitor-admin-smoke-test.md`
- `install/index.php`
- `admin/competitors.php`
- `tests/Admin/CompetitorAdminSourceTest.php`
- current target Bitrix source for `DeleteDirFiles()`, `CAdminList`/navigation and `Bitrix\Main\UI\PageNavigation` or whatever equivalent API is selected.

Use `$implement-task`.

Because previous source-only checks missed real Bitrix API semantics, verify the actual function/method signatures against supported Bitrix source. Do not infer return values or method behavior from names.

## Scope

Implement only:

1. safe uninstall admin-proxy cleanup without relying on a nonexistent boolean return from `DeleteDirFiles()`;
2. true DB-side pagination for the competitor ORM list;
3. focused regression/source tests for both fixes;
4. update manual smoke-test documentation if needed;
5. keep the module version at `0.4.0`.

Do not implement:

- product edit integration;
- custom iblock properties;
- product-to-competitor UI;
- collector execution;
- `PriceUpdateService`;
- external HTTP/Python collectors;
- agents/cron/queue;
- history;
- dashboard/statistics;
- bulk editing/deleting;
- schema changes/migrations;
- custom ACL;
- unrelated refactoring.

## 1. Fix uninstall lifecycle

Current problematic logic conceptually does this:

```php
if (!DeleteDirFiles($from, $to)) {
    throw new RuntimeException(...);
}

ModuleManager::unRegisterModule($this->MODULE_ID);
```

Standard Bitrix `DeleteDirFiles()` performs deletion but must not be treated as a boolean-success API.

Required behavior:

- call the supported Bitrix cleanup function without boolean-negating its return value;
- remove only the admin proxy files owned by this module;
- continue to `ModuleManager::unRegisterModule($this->MODULE_ID)` after normal cleanup;
- preserve ORM tables/data exactly as before;
- do not delete unrelated `/bitrix/admin` files;
- do not silently claim cleanup succeeded if the module-owned proxy files still exist.

Preferred hardening:

After `DeleteDirFiles()` returns, explicitly verify the expected module-owned proxy destinations do not still exist, for example:

```text
/bitrix/admin/kk_pricewatch_competitors.php
/bitrix/admin/kk_pricewatch_competitor_edit.php
```

If explicit post-delete verification is implemented, use normal filesystem checks and throw a clear installer exception only when an owned proxy actually remains. Do not depend on `DeleteDirFiles()` return semantics.

The lifecycle must not leave the module registered merely because a void/null-returning cleanup helper was misinterpreted as failure.

### Installation side

Do not change working install behavior unless a directly related compatibility issue is found and verified. `CopyDirFiles()` handling is outside this fix unless the actual target Bitrix source proves the current check is invalid.

## 2. True DB-side pagination

Current code executes the D7 query without `limit`/`offset`, then wraps the resulting DB result in `CAdminResult` and calls `NavStart()`.

Task 010 explicitly requires the list not to issue an unbounded full-table ORM query.

Required end state:

- determine total matching row count separately using the supported D7 API, e.g. `CompetitorTable::getCount($filter)` or an equivalent verified count query;
- obtain the requested page size/current page through a supported Bitrix navigation API;
- pass the calculated pagination to `CompetitorTable::getList()` as actual ORM `limit` and `offset`;
- retain the existing filter and sort whitelist behavior;
- preserve default ordering `SORT ASC, NAME ASC, ID ASC`;
- preserve normal Bitrix page-size preference/navigation UX;
- list rendering must iterate only the current page rows returned by the ORM query.

A preferred modern pattern, if verified against the target Bitrix runtime, is based on:

```php
use Bitrix\Main\UI\PageNavigation;
```

with:

```text
navigation page size/current page
    ↓
getCount(filter)
    ↓
setRecordCount(total)
    ↓
ORM getList(limit = nav->getLimit(), offset = nav->getOffset())
    ↓
admin list navigation rendering
```

`CAdminList` may be kept for the table UI. If it exposes a supported helper such as `getPageNavigation()` / `setNavigation()` in the target runtime, that is acceptable and may be preferable. Otherwise use `Bitrix\Main\UI\PageNavigation` directly with a compatible admin-list navigation rendering path.

Do not invent a method name. Verify every selected navigation method/signature in Bitrix source before using it.

### Show-all behavior

Do not accidentally reintroduce an unbounded query through a “show all” mode. For this module list, it is acceptable to disable show-all and keep finite page sizes if that is simpler and safer with the chosen Bitrix API.

### Count/query behavior

The page query must contain an actual finite `limit` for normal paginated mode. The offset must reflect the selected page. Filters must be applied identically to both count and page queries.

Do not fetch the complete ORM result and then `array_slice()` it in PHP.

## 3. Preserve Task 010 security and behavior

Do not regress:

- `D/R/W` checks;
- `check_bitrix_sessid()` on mutations;
- read-only behavior for `R`;
- ORM-only persistence;
- deletion reference check through `ProductCompetitorTable`;
- escaping of competitor values;
- sort whitelist;
- filters;
- PRG redirects;
- localization;
- admin proxy fallback `/local/modules/...` then `/bitrix/modules/...`.

## 4. Tests / regression guards

Update or extend source-level tests so they no longer merely assert `NavStart()` exists.

At minimum assert as practical:

### Uninstall

- `DoUninstall()` does not use a boolean-negated `DeleteDirFiles()` condition;
- module-owned proxy cleanup is still present;
- `ModuleManager::unRegisterModule()` remains reachable in the normal uninstall flow;
- if explicit file-existence post-checks are added, tests guard the two owned proxy names.

### Pagination

- competitor list has an explicit total-count query;
- page query includes real ORM `limit` and `offset` derived from navigation;
- existing sort whitelist remains present;
- no source pattern documents `CAdminResult::NavStart()` alone as proof of DB-side pagination.

Source guards remain regression checks only; do not claim they prove real Bitrix runtime behavior.

Run:

- `composer validate`;
- PHP syntax checks;
- PHPUnit;
- `git diff --check`.

`composer.lock` must remain unchanged unless there is an explicit dependency reason, which this task does not require.

## 5. Manual real-Bitrix verification

After merge, perform on the real Bitrix development installation.

### Uninstall lifecycle

1. confirm both admin proxy files currently exist;
2. uninstall `kk.pricewatch` normally;
3. confirm uninstall completes without a runtime exception;
4. confirm module is no longer registered;
5. confirm only the two module-owned admin proxies are removed;
6. confirm both ORM tables and their rows remain intact;
7. reinstall module and confirm admin proxies return and existing data is preserved.

### Pagination

Populate enough competitors to span multiple pages, or temporarily use a small page-size preference.

Confirm:

1. page 1 and page 2 show different expected rows;
2. filters preserve correct total/page navigation;
3. sort changes remain whitelisted and deterministic;
4. navigation does not produce duplicates or skipped records under the default tie-breakers;
5. no undefined Bitrix navigation class/method fatal occurs.

If practical, inspect the generated SQL/debug trace and confirm the competitor page query contains finite `LIMIT` and appropriate `OFFSET`.

Any new undefined Bitrix function/class/method is a blocker; report it instead of adding speculative compatibility shims.

## 6. Version

Keep:

```text
0.4.0
```

This task corrects the 0.4.0 admin milestone and does not constitute a new feature release.

## Acceptance criteria

Task is complete when:

- uninstall no longer interprets `DeleteDirFiles()` as a boolean-success API;
- normal uninstall reaches module unregistration after proxy cleanup;
- owned admin proxies are removed and ORM data is preserved;
- competitor list pagination is true DB-side D7 pagination with finite `limit` and calculated `offset`;
- count and page query use the same filter semantics;
- normal Bitrix page navigation works;
- Task 010 rights, CSRF, escaping, safe deletion and sorting remain intact;
- version remains `0.4.0`;
- Composer/syntax/PHPUnit CI is green;
- real Bitrix uninstall and multi-page list smoke tests are performed after deployment.

## Codex invocation

```text
$implement-task
Implement docs/tasks/011-competitor-admin-lifecycle-pagination-fixes.md.
Do not implement anything outside the task scope.
```

After implementation:

```text
$code-review
Review docs/tasks/011-competitor-admin-lifecycle-pagination-fixes.md against AGENTS.md and Task 010.
Pay particular attention to actual DeleteDirFiles() return semantics, whether uninstall always reaches module unregistration on normal cleanup, and whether competitor pagination is genuinely enforced in the D7 ORM query through finite limit/offset rather than only through CAdminResult post-processing.
```
