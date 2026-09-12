# Competitor admin real-Bitrix smoke test

Run this checklist on the supported Bitrix/PHP 8.2 development installation. Source-only PHPUnit guards do not verify the admin runtime APIs.

1. Install or update `kk.pricewatch`; repeat installation and confirm existing ORM rows remain unchanged and both `/bitrix/admin/kk_pricewatch_competitors.php` and `/bitrix/admin/kk_pricewatch_competitor_edit.php` work.
2. Assign `D`, `R`, and `W` module rights to separate test groups. Confirm the menu is hidden and both direct URLs are denied for `D`; `R` can list/view but crafted save/delete POST requests fail; `W` sees create/edit/delete controls.
3. As `W`, create a competitor with defaults and `{}` options, then edit every supported field. Confirm `UPDATED_AT` changes.
4. Submit blank `NAME`, unknown `COLLECTOR_TYPE`, a handler longer than 512 characters, and malformed/non-object JSON. Confirm readable errors and no partial persistence.
5. Store allowed text containing `<`, `>`, `&`, single and double quotes. Confirm list and form output is escaped.
6. Delete an unreferenced competitor after confirmation. Then create a product-competitor link and confirm deletion of its competitor is blocked without deleting or orphaning the link.
7. Populate enough competitors for at least two pages (or temporarily select a small page-size preference). Confirm pages contain different expected rows; filters keep the correct total, sorting remains deterministic, and no rows are duplicated or skipped. Where practical, inspect the SQL trace and confirm the page query has a finite `LIMIT` and the expected `OFFSET`.
8. Uninstall normally. Confirm it completes without an exception, the module is no longer registered, only the two module-owned admin proxies are removed, and both ORM data tables and their rows remain preserved. Reinstall and confirm the proxies return and existing data is unchanged.

Record the Bitrix version, PHP version, database engine, tester, date, and result for each step. Any undefined admin class/method or runtime fatal error is a release blocker.
