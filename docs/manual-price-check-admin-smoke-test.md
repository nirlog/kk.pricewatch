# Manual price check admin smoke test

Run after installing module version `0.7.0` on the supported Bitrix development instance.

1. Verify `D`, `R`, and `W` module rights: only `W` sees execution links/buttons; `D` cannot open the page.
2. Open and refresh both confirmation scopes with GET; confirm `LAST_CHECK_AT` remains unchanged.
3. Submit without a valid `sessid`; confirm no monitoring state changes.
4. Run a single active Mock link success, then refresh the PRG result page; confirm the collector is not run again.
5. Run a single item error after a prior success; confirm stale price, currency, and last-success time remain.
6. Run a product batch containing success/error links for one competitor and a second competitor; compare aggregate/per-link outcomes with persisted rows.
7. Confirm product mode excludes inactive links, while direct single-link mode surfaces `INACTIVE_LINK`; verify inactive competitor surfaces `INACTIVE_COMPETITOR`.
8. Delete a link between GET and POST and try a mismatched `PRODUCT_ID`/`LINK_ID`; both must fail without creating or changing rows.
9. Confirm normal product and SKU edit tabs contain navigation links only and native save still works.
10. Uninstall and reinstall; confirm the new admin proxy is removed/restored and ORM data is preserved.
