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
Consequently, a clean install after this correction creates `COLLECTOR_HANDLER` with
capacity for 512 characters, but it does not alter an existing table. If a local
development installation has the earlier 255-character column, uninstall the module,
delete the preserved development table as appropriate, and reinstall before checking
the schema. Production-safe `ALTER`/migration handling will be introduced only when
the project requires upgrade packages.

## Manual Bitrix verification

1. Install or reinstall the module against a clean competitor table and confirm that
   `b_kk_pricewatch_competitor` exists.
2. Inspect the generated table and confirm `COLLECTOR_HANDLER` is `varchar(512)`, or
   the database-engine equivalent with capacity for at least 512 characters.
3. Call `CompetitorTable::add(['NAME' => 'Test competitor'])` and confirm
   `ACTIVE=Y`, `SORT=500`, and `COLLECTOR_TYPE=mock`.
4. Confirm both timestamps are populated, update `NAME`, and confirm `UPDATED_AT`
   changed.
5. Add or update a competitor with a handler longer than 255 but no longer than 512
   characters and confirm the complete value is stored.
6. Attempt to save a handler longer than 512 characters and confirm ORM validation
   rejects it.
7. Uninstall the module and confirm the table and row remain.
8. Reinstall and confirm installation succeeds and the existing row remains.
