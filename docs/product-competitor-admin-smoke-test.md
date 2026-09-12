# Product competitor admin integration smoke test

Run this checklist after deployment to a real Bitrix development installation.

Record Bitrix version, PHP version, database engine, date, operator and pass/fail result.

1. Install/update completes without undefined classes or methods.
2. Existing catalog product and supported SKU/offer edit pages show **Мониторинг цен** to `R` and `W`; unrelated pages and `D` do not.
3. `D` cannot access either product-link page. `R` can inspect a link but crafted create/update/delete POSTs fail. `W` can mutate links.
4. Create a URL with significant parameter order, percent encoding and a fragment. Confirm database `URL` is byte-for-byte identical and `URL_HASH` is its SHA-256.
5. Confirm an exact duplicate is rejected and unsafe `javascript:`, `data:` and `file:` URLs are rejected.
6. Seed operational values. Confirm changing only `ACTIVE` preserves them, while changing URL or competitor atomically resets status to `new` and all specified operational values to `NULL`.
7. Confirm inactive competitors remain visible on existing links and operational fields are read-only.
8. Confirm deletion removes only the selected link, not its competitor or product, and the summary updates after reload.
9. Confirm list pagination uses finite `LIMIT`/appropriate `OFFSET` and pages do not duplicate or skip rows.
10. Uninstall: confirm all four owned proxies and the event registration are removed, module tables/data remain, and unrelated admin files remain.
11. Reinstall: confirm proxies/event return and existing competitor/link rows are unchanged.

Any undefined Bitrix admin, event, iblock or navigation API is a blocker; record it rather than adding an unverified compatibility shim.
