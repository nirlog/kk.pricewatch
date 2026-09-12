# Scheduled price updates

Task 017 provides two thin scheduling adapters over the same
`ScheduledPriceUpdateRunner`. The runner selects active monitored-link IDs in a
stable, ascending snapshot and delegates each chunk to `PriceUpdateService`.

## Choose a scheduler

The installer creates one non-periodic Bitrix Agent with a 3600-second interval,
**inactive by default**. An administrator may activate it after collector
configuration is ready. Alternatively, system cron can invoke the module CLI
directly:

```cron
*/15 * * * * /usr/bin/php /home/bitrix/www/local/modules/kk.pricewatch/bin/pricewatch-run.php --batch-size=100
```

The PHP binary and deployment path are environment-specific. Normally choose one
primary scheduler. If both are enabled accidentally, their shared named database
lock prevents overlap.

The CLI accepts `--batch-size=N` (1 through 1000) and optional
`--document-root=/absolute/bitrix/site/root`. It prints one JSON object containing
aggregate counters only. Exit `0` means a normal run, including lock contention,
skips, and expected item/collector errors. Exit `1` means a global runner or
bootstrap failure; invalid command-line configuration exits `2`. Batch failures
and persistence-failure outcomes remain visible in the JSON but do not alone
change the exit status.

Task 017 does not provide per-competitor intervals, queues, retries, history, or
automatic crontab modification.

## Real-Bitrix 0.8.0 smoke checklist

### Marketplace update package

`install/updates/<version>/updater.php` is the version-controlled source for the
Bitrix update hook; Bitrix does not discover or execute that nested file directly.
Build the Marketplace update archive with:

```bash
php bin/build-update-package.php 0.8.0 build/0.8.0.tar.gz
```

The builder checks that the requested version matches `install/version.php`,
copies the versioned updater and localized `description.*` files to the archive
root where the Bitrix update lifecycle expects them, and includes the module
runtime files at their module-relative paths. Do not publish an archive assembled
by a generic repository archiver: an archive without the root `updater.php` will not
install the scheduled agent during a 0.7.0 to 0.8.0 upgrade. CI opens the built
archive and verifies this layout.

Record the Bitrix/PHP versions, database engine, date, batch size, relevant link
IDs, and adapter. Verify:

1. install/upgrade reports 0.8.0 and creates exactly one inactive, non-periodic agent;
2. reinstall/update neither duplicates nor reactivates that agent;
3. a direct runner call handles current Mock links and reports aggregate counters;
4. batch size 2 with at least three active links creates at least two chunks;
5. success persists price, currency, and timestamps through `PriceUpdateService`;
6. `PRICE_NOT_FOUND` preserves stale successful price state;
7. unsupported external configuration reports generic `COLLECTOR_ERROR` without stopping later chunks;
8. inactive links are not selected; inactive competitors report `INACTIVE_COMPETITOR` without timestamp mutation;
9. the CLI boots Bitrix and returns aggregate JSON without raw errors or secrets;
10. activating/invoking the Agent runs the same runner and leaves it registered;
11. while one DB session holds `kk.pricewatch.scheduled_price_update`, a second run reports `locked=true` and performs no collection; after release, it runs normally;
12. the Task 016 manual admin check still works;
13. uninstall removes only this agent and preserves both monitoring tables/data;
14. reinstall restores one inactive agent and preserved data remains available.
