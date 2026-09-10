# Competitor ORM entity

`KK\PriceWatch\Model\CompetitorTable` maps generic competitor configuration to
`b_kk_pricewatch_competitor`.

The table contains `ID`, required `NAME`, `ACTIVE` (`Y` by default), `SORT` (500 by
default), optional `DOMAIN`, `COLLECTOR_TYPE`, optional `COLLECTOR_HANDLER`,
`COLLECTOR_OPTIONS`, `CREATED_AT`, and `UPDATED_AT`. `COLLECTOR_TYPE` is either
`mock` or `external`; it identifies the generic implementation category. The handler
is an opaque implementation-independent reference and is not executed by the entity.
Options are an opaque JSON object and are never PHP-serialized.

Normal module uninstall unregisters the module but deliberately leaves the table and
its rows in place. Reinstall detects the existing table and does not recreate it.

## Manual Bitrix verification

1. Install the module from a clean state and confirm that
   `b_kk_pricewatch_competitor` exists.
2. Call `CompetitorTable::add(['NAME' => 'Test competitor'])`.
3. Read the row and confirm `ACTIVE=Y`, `SORT=500`, and `COLLECTOR_TYPE=mock`.
4. Confirm both timestamps are populated.
5. Update `NAME`, then confirm that `UPDATED_AT` changed.
6. Uninstall the module and confirm the table and row remain.
7. Reinstall and confirm installation succeeds and the existing row remains.
