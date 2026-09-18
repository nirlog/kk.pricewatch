# Monitoring e-mail notifications

Notifications are disabled by default. A user with module right `W` configures an explicit recipient list,
active Bitrix site context, and recovery delivery on the **Notification settings** administration page.

Enabling the subsystem does not schedule it. The bundled Bitrix notification Agent is installed inactive by
default and must be explicitly activated/configured by an administrator. Alternatively, cron can invoke:

```bash
php bin/pricewatch-notify.php
```

The installer creates `KK_PRICEWATCH_ALERT_DIGEST` mail templates only for active sites that exist during
installation/update. Enabling notifications for a site is rejected when its module template is missing; create
or restore the template for that site without changing unrelated mail templates.

The separate notification Agent and `bin/pricewatch-notify.php` use the same `NotificationRunner`. The runner
only reads current monitoring state and writes `b_kk_pricewatch_notification_state`; it never invokes a collector
or changes current prices, errors, timestamps, or history.

The state table records the last successfully delivered rule state. Mail failure therefore leaves transitions
retryable. Delivery is at-least-once: a process crash after the Bitrix mail subsystem accepts a message but before
the delivered state is committed can result in one duplicate digest on retry.
