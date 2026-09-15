# Staff-only product prices component

Include the component in a catalog detail template only with the exact entity currently being displayed:

```php
$APPLICATION->IncludeComponent('kk.pricewatch:product.prices', '', [
    'PRODUCT_ID' => (int) $effectiveProductId,
    'MAX_AGE_SECONDS' => 86400,
    'MAX_ROWS' => 50,
]);
```

`PRODUCT_ID` is exact. The component never finds a parent, selects an offer, or combines sibling offers. The current king-komp/air-msk production integration deliberately passes the parent product ID. Automatic browser-side switching is not supported in v1.

Only users whose `kk.pricewatch` module right is `R` or `W` receive data. The permission check precedes the current-state ORM read. The component uses no result cache. It always creates a composite dynamic area with an empty, data-free stub—even for a denied request—so every dynamic render repeats authorization and neither visit order can leak or suppress staff output. It performs no collection and no writes.

`MAX_AGE_SECONDS` defaults to 86400. A non-negative value is used; `0` disables age-based stale marking. An operational error remains marked as an update failure even when a previous successful price is preserved.

`MAX_ROWS` defaults to 50. Values below 1 or otherwise invalid fall back to 50; values above the hard maximum are capped at 200. The read uses one bounded `N + 1` query, allowing a truncation notice without a count query. Read failures are isolated to a neutral staff-only state and cannot interrupt the product page.

The exact stored URL is used unchanged for valid absolute `http` and `https` destinations. A legacy or otherwise invalid persisted URL (including `javascript:` and `data:` schemes) is not rendered as a clickable link.
