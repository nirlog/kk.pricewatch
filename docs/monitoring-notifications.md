# Monitoring e-mail notifications

Notifications are disabled by default. A user with module right `W` configures an explicit recipient list,
active Bitrix site context, and recovery delivery on the **Notification settings** administration page.

The separate notification Agent and `bin/pricewatch-notify.php` use the same `NotificationRunner`. The runner
only reads current monitoring state and writes `b_kk_pricewatch_notification_state`; it never invokes a collector
or changes current prices, errors, timestamps, or history.

The state table records the last successfully delivered rule state. Mail failure therefore leaves transitions
retryable. Delivery is at-least-once: a process crash after the Bitrix mail subsystem accepts a message but before
the delivered state is committed can result in one duplicate digest on retry.
