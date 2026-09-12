# Product competitor admin integration smoke test

Run this checklist after deployment to a real Bitrix development installation.

Record Bitrix version, PHP version, database engine, date, operator and pass/fail result.

1. Install/update completes without undefined classes or methods.
2. Existing catalog product and supported SKU/offer edit pages open without a fatal error and show **Мониторинг цен** to `R` and `W`; unrelated non-catalog iblock element pages and `D` do not. Confirm the tab table/layout renders normally inside the native edit form.
3. Direct access to the product-link list and direct create/edit POST requests with a non-catalog iblock element ID are rejected and do not persist a link.
4. `D` cannot access either product-link page. `R` can inspect a link but crafted create/update/delete POSTs fail. `W` can mutate links.
5. Create a URL with significant parameter order, percent encoding and a fragment. Confirm database `URL` is byte-for-byte identical and `URL_HASH` is its SHA-256.
6. Confirm an exact duplicate is rejected and unsafe `javascript:`, `data:` and `file:` URLs are rejected.
7. Seed operational values. Confirm changing only `ACTIVE` preserves them, while changing URL or competitor atomically resets status to `new` and all specified operational values to `NULL`.
8. Confirm inactive competitors remain visible on existing links and operational fields are read-only.
9. Confirm deletion removes only the selected link, not its competitor or product, and the summary updates after reload.
10. Confirm list pagination uses finite `LIMIT`/appropriate `OFFSET` and pages do not duplicate or skip rows.
11. Uninstall: confirm all four owned proxies and the event registration are removed, module tables/data remain, and unrelated admin files remain.
12. Reinstall: confirm proxies/event return and existing competitor/link rows are unchanged.

Any undefined Bitrix admin, event, iblock or navigation API is a blocker; record it rather than adding an unverified compatibility shim.
