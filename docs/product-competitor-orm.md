# Product ↔ competitor persistence

`b_kk_pricewatch_product_competitor` stores one monitored Bitrix element, competitor, and exact competitor product/configuration URL. `URL` is retained byte-for-byte; `URL_HASH` is derived as lowercase SHA-256 solely to enforce the unique physical index on `PRODUCT_ID, COMPETITOR_ID, URL_HASH`. It is not the canonical identity and URL query parameters are never normalized.

`STATUS` is `new`, `success`, or `error`. The nullable current price is fixed precision `(18,2)` and its nullable currency is an uppercase three-letter code. A row may retain its last successful price and currency while its latest status is `error`; collection transition rules are intentionally outside the ORM. `LAST_CHECK_AT` and `LAST_SUCCESS_AT` are caller-managed, while creation/update timestamps are automatic.

Normal uninstall preserves both module tables and their rows.

## Manual Bitrix verification

On a development Bitrix installation, use a clean recreation of the module-owned tables. The installer intentionally preserves existing tables and does not alter their columns, so an existing `varchar(255)` column will not be corrected automatically. Uninstall the module if needed, remove the preserved tables only when safe in this development environment, and install the module again.

1. Confirm module installation completes without errors and both module tables exist.
2. Run `SHOW CREATE TABLE b_kk_pricewatch_product_competitor`. Confirm `CURRENCY` is `varchar(3) DEFAULT NULL`, `STATUS` is `varchar(16) NOT NULL`, `URL` and `ERROR_MESSAGE` are text-capable, `URL_HASH` is `varchar(64)`, and `CURRENT_PRICE` is physically equivalent to `DECIMAL(18,2)`.
3. Confirm the deterministically named `ux_kk_pw_pc_identity` index is **unique** and contains `PRODUCT_ID, COMPETITOR_ID, URL_HASH`; also confirm the `ix_kk_pw_pc_product` and `ix_kk_pw_pc_competitor` lookup indexes exist. Bitrix's cross-database `isIndexExists()` check establishes that an index covers the requested columns but does not establish uniqueness, so the installer does not destructively replace an existing same-column index. A clean install creates `ux_kk_pw_pc_identity` as unique through the Bitrix connection API.
4. Add a row with a long URL/query string and confirm the URL round-trips exactly, `URL_HASH` equals SHA-256 of those exact bytes, and defaults are `ACTIVE=Y`, `STATUS=new`, with both timestamps populated.
5. Confirm the exact duplicate is rejected, while the same product and competitor with a different exact URL succeeds.
6. Update the URL and confirm the hash and `UPDATED_AT` change. Supply a conflicting hash alongside a URL, and then try changing only the hash; confirm the stored hash remains consistent in both cases.
7. Store a last successful price/currency with `STATUS=error` and confirm the stale-price state remains representable.
8. Uninstall and reinstall, then confirm the rows remain.

These checks require a real Bitrix runtime and supported database; the source-level tests do not claim to prove generated physical DDL.
