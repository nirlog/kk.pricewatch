# Monitoring e-mail notifications

Notifications are disabled by default. A user with module right `W` configures an explicit recipient list,
active Bitrix site context, and recovery delivery on the **Notification settings** administration page.

The notification permission and scheduler are independent. `notifications_enabled` controls whether delivery is
allowed; the Agent's actual `ACTIVE` value controls only automatic execution. Changing either setting never
implicitly changes the other.

## Option A — Bitrix Agent

On **KK PriceWatch → Notification settings**, an administrator enables automatic execution and selects an integer
interval from 5 through 1440 minutes. Bitrix then invokes `NotificationAgent`, which uses the shared
`NotificationRunner`. The page reports the actual Agent state, interval, last execution, and next execution from
`CAgent`. A fresh installation creates exactly one inactive Agent with a 60-minute interval.

## Option B — cron / CLI

Cron may invoke the same runner independently of the Agent's `ACTIVE` state:

```bash
php bin/pricewatch-notify.php
```

Normally, choose one primary scheduler. The existing database lock protects against a concurrent Agent and CLI
run and prevents both runners from processing the same digest simultaneously, but intentionally scheduling both
without a need is not recommended.

The installer creates `KK_PRICEWATCH_ALERT_DIGEST` mail templates only for active sites that exist during
installation/update. Enabling notifications for a site is rejected when its module template is missing; create
or restore the template for that site without changing unrelated mail templates.

Both execution modes use the same `NotificationRunner`. The runner only reads current monitoring state and writes
`b_kk_pricewatch_notification_state`; it never invokes a collector or changes current prices, errors, timestamps,
or history.

The state table records the last successfully delivered rule state. Mail failure therefore leaves transitions
retryable. Delivery is at-least-once: a process crash after the Bitrix mail subsystem accepts a message but before
the delivered state is committed can result in one duplicate digest on retry.
