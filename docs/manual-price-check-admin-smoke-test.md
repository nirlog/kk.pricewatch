# Manual price check admin smoke test

## Verification status

- Module version: `0.7.0`.
- Automated CI: passed.
- Real-Bitrix smoke test: passed.
- Manual admin execution: verified.

Run this checklist after installing module version `0.7.0` on the supported real
Bitrix development instance. Record the Bitrix version, PHP version, database
engine, test date, user/right used for each permission check, and the relevant
link IDs. Compare UI values with the corresponding ORM/database rows where the
check calls for persisted state verification.

## Permissions and side-effect-free GET

1. As a user with module right `W`, open the product monitoring list and verify
   that `Проверить цену` is available for an existing link and
   `Проверить активные цены` is available for the product.
2. As a user with module right `R`, verify that monitoring state remains visible,
   but neither manual-check action nor the POST execution button is rendered.
3. While still using the `R` user, craft and submit a POST directly to the manual
   check page with valid `MODE`, `PRODUCT_ID`, `LINK_ID` (for link mode), and a
   valid `sessid`. Verify that execution is rejected and that `LAST_CHECK_AT` and
   all other monitoring fields remain unchanged.
4. As a user with module right `D`, verify that direct access to the manual-check
   page is denied.
5. Open valid single-link and product-mode confirmation pages with GET. Before
   submitting anything, verify that `LAST_CHECK_AT` does not change.
6. Refresh each GET confirmation page repeatedly. Verify that no collection is
   performed and `LAST_CHECK_AT` remains unchanged for every displayed link.
7. Submit the execution POST once without `sessid` and once with an invalid
   `sessid`. Verify that both requests are rejected and no monitoring field,
   including `LAST_CHECK_AT`, changes.

## Single-link success and PRG

8. Prepare an existing active link to an active Mock competitor with an exact URL
   success scenario. Record its initial persisted monitoring fields.
9. Execute `Проверить цену` for that link and verify that the result summary
   reports `requested=1`, `success=1`, `errors=0`, `skipped=0`, and
   `persistence_failures=0`.
10. In both the monitoring UI and persisted row, verify the expected
    `CURRENT_PRICE` and `CURRENCY`, `STATUS=success`, cleared `ERROR_CODE` and
    `ERROR_MESSAGE`, an updated `LAST_CHECK_AT`, and an updated
    `LAST_SUCCESS_AT`.
11. Record `LAST_CHECK_AT`, then refresh the redirected result page. Verify that
    PRG prevents another collector run and that `LAST_CHECK_AT` remains exactly
    unchanged.

## Single-link item error and stale-price preservation

12. Start with a link that already has a successful `CURRENT_PRICE`, `CURRENCY`,
    and `LAST_SUCCESS_AT`, then configure its Mock scenario to return the item
    error `PRICE_NOT_FOUND`.
13. Execute the single-link check and verify that the result UI safely reports an
    error outcome with code `PRICE_NOT_FOUND`.
14. Verify in the persisted row that `STATUS=error`, `ERROR_CODE` and
    `ERROR_MESSAGE` describe the item error, and `LAST_CHECK_AT` advances.
15. Verify that the previous `CURRENT_PRICE`, `CURRENCY`, and `LAST_SUCCESS_AT`
    are preserved exactly rather than cleared or replaced.

## Product batch and collector-group isolation

16. Prepare one product with at least two active links for the same active Mock
    competitor: one scenario succeeds and one returns an item error.
17. Execute `Проверить активные цены` once. Verify that one aggregate result
    contains both per-link outcomes and that each row is persisted independently
    according to its success/error semantics.
18. Add active links for another active Mock competitor to the same product and
    run the product action again. Verify that outcomes from both competitors are
    present and one competitor group does not suppress or overwrite the other.
19. Configure one competitor in that product batch with unsupported
    `COLLECTOR_TYPE=external`, while retaining a successful Mock group. Execute
    the product action and verify that the external group reports the safe code
    `COLLECTOR_ERROR` while the other group still succeeds and persists its
    successful state.

## Skip, filtering, race, and forged-scope behavior

20. Directly open single-link mode for an inactive link and submit the check.
    Verify a skipped outcome with code `INACTIVE_LINK` and no persisted mutation,
    including no timestamp change.
21. Use an active link whose competitor is inactive. Submit the single-link check
    and verify a skipped outcome with code `INACTIVE_COMPETITOR` and no persisted
    mutation.
22. Give one product both active and inactive links, then run the product-level
    action. Verify that the confirmation list and submitted/result batch exclude
    all inactive links and that their persisted state remains unchanged.
23. Open a valid single-link confirmation page, delete that link before submitting
    the POST, and then submit. Verify that the request fails safely or reports
    `NOT_FOUND`, creates no replacement row, and mutates no unrelated row.
24. Craft both GET and POST requests combining a valid `PRODUCT_ID` with a valid
    `LINK_ID` belonging to another product. Verify that the mismatch is rejected,
    no collector executes, and neither product's rows are mutated.

## Product/SKU tab and lifecycle regression

25. Open the native edit page for a normal catalog product and verify that the
    `Мониторинг цен` tab loads, displays monitoring state, and its manual-check
    entry point is ordinary navigation to the module-owned page.
26. Repeat the preceding check for an SKU/offer and verify that the correct SKU
    element ID is used.
27. Inspect the rendered product and SKU forms and exercise normal product save.
    Verify that the monitoring tab introduced no nested form and did not intercept
    or regress native saving.
28. Uninstall the module normally. Verify that
    `kk_pricewatch_product_price_check.php` is removed from `/bitrix/admin/`, while
    module ORM tables and existing competitor/link data are preserved.
29. Reinstall the module. Verify that the manual-check admin proxy is restored,
    the page opens normally, and the previously preserved ORM data is still
    available unchanged.
