# Task 024 — Monitoring notifications foundation + e-mail digest v1

## Goal

Add a production-safe notification subsystem for operational monitoring problems.

The subsystem must notify authorized staff by e-mail when monitored competitor links **enter a problem state, materially change the problem, or recover**, without sending the same unchanged alert every scheduler cycle.

Task 024 is **not** automatic repricing and does not change collector, price-history, dashboard or product-card semantics.

Target module version: `0.15.0`.

## Baseline

The production baseline is module `0.14.5` with:

- competitor/link ORM model;
- manual price check;
- common scheduled/CLI collection path;
- current price state + error preservation;
- price history + analytics;
- staff-only product-card prices;
- monitoring dashboard and health definitions;
- native Bitrix dashboard filtering;
- module rights `D/R/W`.

Monitoring operates at **product level**. Do not add SKU/offer aggregation in this task.

## Core principles

1. Notifications are operational side effects layered on top of current monitoring state.
2. Current monitoring tables remain the source of truth for whether a link is healthy/problematic.
3. Notification delivery state is persistent and separate from monitoring business state/history.
4. An unchanged problem must not generate an e-mail every run.
5. A changed error code may generate a new problem notification.
6. Recovery must be detectable and optionally delivered.
7. Failed e-mail delivery must remain retryable; do not mark a transition as delivered before transport success.
8. Dashboard rendering, product-card rendering and ordinary reads must never send notifications.
9. Notifications are disabled by default after install/update.
10. No customer/public recipient or frontend endpoint is introduced.

## Security invariant

Module rights remain:

```text
D -> no notification administration
R -> may view monitoring data but cannot change notification settings or trigger delivery
W -> may configure notification settings
```

Requirements:

- do not hard-code Bitrix group IDs/names;
- settings page requires `Access::canWrite()`;
- notification runner itself is server-side and does not depend on an authenticated web user;
- do not include raw `ERROR_MESSAGE`, SQL, stack traces, filesystem paths, collector secrets/options, cookies, auth data or HTTP response bodies in e-mail;
- use only safe operational fields (`ERROR_CODE`, link/product/competitor identity, timestamps, current price when present, exact safe source URL);
- exact source URL may be included only when accepted by shared `ProductUrl::isAcceptedHttpUrl()`;
- HTML e-mail output must be escaped.

## Required context

Before implementation read:

- `AGENTS.md`
- `.agents/skills/implement-task/SKILL.md`
- `.agents/skills/code-review/SKILL.md`
- `docs/tasks/017-automatic-price-update.md` (or the current scheduler task file)
- `docs/tasks/019-price-history.md`
- `docs/tasks/020-price-history-analytics.md`
- `docs/tasks/021-staff-only-product-prices.md`
- `docs/tasks/022-staff-product-price-read-hardening.md`
- `docs/tasks/023-monitoring-dashboard.md`
- `lib/Service/PriceUpdateService.php`
- `lib/Scheduling/*`
- `lib/Service/MonitoringHealth.php`
- `lib/Model/ProductCompetitorTable.php`
- `lib/Model/CompetitorTable.php`
- `lib/Model/ProductUrl.php`
- `lib/Admin/Access.php`
- `admin/menu.php`
- installer/update/package-builder files.

Use `$implement-task`.

## Notification rules v1

Reuse current monitoring semantics where applicable. Do not create a second conflicting health model.

### Rule A — collection error

Rule code:

```text
collection_error
```

Active when:

```text
STATUS = error
```

Fingerprint:

```text
ERROR_CODE
```

Use a stable fallback such as `UNKNOWN_ERROR` when the code is missing.

Behavior:

- first delivered error -> problem transition;
- same error code on later scans -> no duplicate notification;
- different `ERROR_CODE` while still in error -> changed-problem transition and may notify again;
- leaving `STATUS=error` after a previously delivered error -> recovery transition.

Never use raw `ERROR_MESSAGE` as a fingerprint or e-mail body field.

### Rule B — stale successful price

Rule code:

```text
stale
```

Use the shared dashboard stale threshold/definition. In v1 this is currently:

```text
MonitoringHealth::STALE_AFTER_SECONDS = 86400
```

Active when a successful current value exists and `LAST_SUCCESS_AT` is older than the cutoff.

Fingerprint is stable for the active stale state; do not generate a new notification every hour simply because the age increased.

A fresh successful update after a delivered stale alert is a recovery.

### Rule C — no successful price after an attempted check

Rule code:

```text
no_success_price
```

Active when:

```text
successful current value invariant = false
AND LAST_CHECK_AT != null
```

This deliberately avoids alerting immediately for a brand-new `new` link that has never been attempted.

A first successful value after a delivered no-price alert is a recovery.

### Rule overlap

Rules are independent. One link may simultaneously have:

```text
collection_error + no_success_price
collection_error + stale
```

Do not create duplicate rows for the same rule, but do preserve all active rule facts.

The e-mail layer should **consolidate multiple transitions into one digest per notification scan**, grouped by link where practical, rather than sending one message per rule/link.

## Persistent delivery-state model

Add a dedicated ORM table for **last successfully delivered notification state**, conceptually:

```text
b_kk_pricewatch_notification_state
```

Suggested fields:

```text
ID                         bigint/int PK autoincrement
PRODUCT_COMPETITOR_ID      required positive int
RULE_CODE                  required string(32)
NOTIFIED_ACTIVE            Y/N required
NOTIFIED_FINGERPRINT       nullable string(64)
LAST_NOTIFIED_AT           nullable datetime
CREATED_AT                 datetime
UPDATED_AT                 datetime
```

Required unique identity:

```text
(PRODUCT_COMPETITOR_ID, RULE_CODE)
```

The table stores the **last successfully delivered state**, not merely the last observed state.

This is essential for retry safety:

- current problem differs from delivered state -> pending transition;
- transport succeeds -> advance notification state;
- transport fails -> do not advance it, so a later scan retries.

Do not store raw error messages or mail body content in this table.

No DB foreign key is required. Orphan state after a deleted monitoring link may remain harmlessly in v1; the evaluator scopes to existing active links.

## Transition model

Introduce narrow value objects/services, conceptually:

```text
NotificationRuleEvaluator
NotificationTransition
NotificationStateRepositoryInterface
OrmNotificationStateRepository
NotificationScanService
```

Exact names may follow repository conventions.

Transition types:

```text
problem_started
problem_changed
recovered
```

A scan compares **current derived state** with **last successfully delivered state**.

Examples:

```text
notified inactive -> current error(E1)       => problem_started
notified error(E1) -> current error(E1)      => no transition
notified error(E1) -> current error(E2)      => problem_changed
notified error(E1) -> current healthy        => recovered
```

For stale/no-price use stable fingerprints so an unchanged active condition does not repeatedly notify.

## Scope of evaluated links

Evaluate only operational monitoring scope:

```text
ProductCompetitor.ACTIVE = Y
Competitor.ACTIVE = Y
```

Inactive configuration is not an alert condition in v1.

The scan must be bounded/batched. Do not load an arbitrarily large table into memory.

Suggested batch size: `100` with keyset pagination by link `ID`.

Do not use OFFSET over the full table for the background scan when keyset iteration is straightforward.

## Product and competitor labels

Digest messages should contain useful labels without N+1 queries.

For all transitions in one digest:

- competitor names should come from the already joined/batched monitoring read;
- resolve unique product IDs with one `Bitrix\Iblock\ElementTable` query (reuse/extract `ProductNameResolver` behavior if appropriate);
- missing/deleted products fall back to `#PRODUCT_ID`.

Do not query one product per transition.

## Notification transport abstraction

Add a transport boundary, conceptually:

```text
NotificationTransportInterface
BitrixMailNotificationTransport
```

The rule/scan layer must not depend directly on Telegram/Bitrix24-specific APIs.

Transport input should be a safe structured digest/value object, not prebuilt untrusted HTML from persistence.

Task 024 implements **e-mail only**. Future transports are out of scope but the boundary must allow adding them later.

## E-mail delivery

Use the standard Bitrix mail subsystem. Prefer a module-owned mail event type/template rather than direct raw PHP `mail()`.

Conceptual event name:

```text
KK_PRICEWATCH_ALERT_DIGEST
```

Provide RU/EN event metadata/template lifecycle as appropriate for Bitrix.

Digest should include at minimum:

```text
scan timestamp
number of new/changed problems
number of recoveries
product label / ID
competitor name
link ID
rule(s)
stable error code when relevant
current price/currency when a successful value exists
last check
last success
safe exact source URL when allowed
```

Do not include `ERROR_MESSAGE`.

One scan with multiple transitions should normally produce **one e-mail digest per configured recipient set/site context**, not dozens of independent messages.

If the transport reports failure, do not advance notification delivery state.

If transport succeeds, persist all included transition states only after success.

Perfect exactly-once semantics are not required across an unavoidable crash between external mail acceptance and DB acknowledgement; document delivery as at-least-once under that narrow crash window.

## Configuration

Add module notification options with safe defaults.

At minimum:

```text
notifications_enabled = N
notification_emails = ""
notification_site_id = ""
send_recovery = Y
```

Rules:

- disabled by default on fresh install and upgrade;
- normalize recipient list from comma/semicolon/whitespace input;
- validate e-mail addresses;
- deduplicate recipients;
- do not run/send when enabled but recipient list is empty;
- `notification_site_id` must be an existing active Bitrix site used for the mail event context;
- do not silently send to arbitrary administrator accounts when recipients are not configured.

Do not add Telegram tokens, webhooks or Bitrix24 credentials in Task 024.

## Notification settings admin page

Add a normal module admin page, conceptually:

```text
admin/notification_settings.php
/bitrix/admin/kk_pricewatch_notification_settings.php
```

Menu:

```text
KK PriceWatch
  Monitoring
  Notification settings
  Competitors
```

Permission:

```text
W only
```

The page should let a `W` user configure:

- enabled Y/N;
- recipient e-mails;
- Bitrix site context;
- send recovery Y/N.

Use PRG/session checks for writes and normal Bitrix admin UI conventions.

Do not put secrets in GET parameters.

`R`/`D` must not be able to save or view writable notification configuration.

No public/frontend settings endpoint.

## Background execution

Add a notification scan runner separate from dashboard rendering and collection business logic, conceptually:

```text
NotificationRunner
NotificationAgent
```

Use the same architectural principles as the existing scheduled collection runner:

- common service callable by Agent and CLI;
- dedicated named DB lock;
- no overlapping scans;
- finite batches/keyset iteration;
- deterministic result summary;
- exceptions must not leak through the Agent boundary.

Suggested lock name:

```text
kk.pricewatch.notification_scan
```

### Agent

Install a separate notification Agent, inactive by default:

```text
\KK\PriceWatch\Agent\NotificationAgent::run();
```

Suggested interval:

```text
3600
```

Fresh install/update must not automatically activate notification sending.

If notifications are disabled, the runner should return quickly without scanning/sending.

### CLI

Add a CLI entry point, conceptually:

```text
bin/pricewatch-notify.php
```

It must invoke the exact same runner/service as the Agent.

Useful deterministic exit semantics:

```text
0 = completed/no transitions/sent successfully
non-zero = lock/config/runtime/transport failure
```

Do not duplicate notification logic in the CLI script.

## Concurrency and state update discipline

A dedicated notification scan lock is required to prevent two normal scans from evaluating and delivering the same transitions simultaneously.

Within one scan:

1. acquire lock;
2. read normalized settings;
3. if disabled -> clean no-op;
4. evaluate current state in bounded batches;
5. compare against last delivered state;
6. build one digest for pending transitions;
7. if no transitions -> no mail/no state writes;
8. deliver digest;
9. only after successful delivery, persist the corresponding delivered states;
10. release lock in `finally`.

Do not hold a DB transaction open while performing external mail delivery.

## Recovery delivery setting

When `send_recovery = N`:

- do not send recovery e-mails;
- nevertheless advance the delivered-state baseline for recovered rules during a successful scan/no-op transition handling so the same old recovery is not reconsidered forever;
- future re-entry into the problem must still generate a new problem notification.

Design this carefully and test it deterministically.

## Read/write boundaries

Notification scans may write only notification-delivery state and may invoke the configured e-mail transport.

They must never modify:

```text
CURRENT_PRICE
CURRENCY
STATUS
ERROR_CODE
ERROR_MESSAGE
LAST_CHECK_AT
LAST_SUCCESS_AT
price history
competitor/link ACTIVE
collector configuration
product-card data
```

They must never invoke:

```text
PriceUpdateService
collectors
outbound competitor HTTP
```

The notification subsystem observes monitoring state; it does not collect prices.

## Dashboard integration

Keep Task 023 dashboard behavior unchanged in Task 024.

Optional small, read-only additions are allowed only if simple, e.g. a link from Monitoring to notification settings for `W` users.

Do not add inline mail sending, polling or notification-state writes to dashboard render/filter/sort actions.

## Schema/install/uninstall lifecycle

Add the notification-state table and unique index through `SchemaInstaller`.

Fresh install:

- creates table/index idempotently;
- installs notification admin proxy;
- registers module-owned mail event type/template;
- installs notification Agent inactive;
- keeps notifications disabled by default.

Upgrade `0.14.5 -> 0.15.0`:

- additive schema only;
- install/register notification admin entry point;
- install/register mail event/template idempotently;
- install notification Agent inactive if missing;
- initialize no option in a way that accidentally enables sending;
- do not rewrite monitoring/history business rows.

Uninstall:

- remove module-owned notification Agent;
- remove module-owned notification admin proxy;
- unregister/remove module-owned mail event/template if safe and clearly owned;
- preserve module DB data consistent with current safe-uninstall policy, including notification-state rows unless the existing module policy explicitly removes all module tables (do not introduce a destructive special case).

Reinstall must restore runtime/proxy/Agent/mail artifacts idempotently.

## Versioning

Target:

```text
0.15.0
```

Add normal updater metadata:

```text
install/updates/0.15.0/updater.php
install/updates/0.15.0/description.ru
install/updates/0.15.0/description.en
```

Update package must include all new model/service/agent/admin/lang/installer/CLI/version/update files.

## Localization

Provide RU/EN localization for:

- menu/settings page;
- option labels/help;
- save success/error messages;
- mail event/template subject/body labels;
- rule names;
- transition names;
- safe digest labels;
- runner/configuration errors exposed to admin/CLI where appropriate.

Do not hard-code Russian business labels in service code.

## Out of scope

Do not add in Task 024:

- automatic repricing;
- Telegram;
- Bitrix24 notifications;
- Slack/webhooks;
- SMS/push;
- customer/public notifications;
- per-user notification preferences;
- per-competitor recipient routing;
- price-change threshold alerts;
- competitor cheaper-than-us alerts;
- daily/weekly analytical reports;
- browser polling/AJAX alerts;
- notification charts;
- SKU/offer aggregation;
- new collection logic;
- modification of price-history semantics.

These can be separate tasks after the foundation is production-verified.

## Tests

Add deterministic automated coverage. At minimum prove:

1. notification-state table metadata and unique `(PRODUCT_COMPETITOR_ID, RULE_CODE)` identity;
2. fresh schema install is idempotent;
3. `collection_error` starts only for `STATUS=error`;
4. error fingerprint uses stable `ERROR_CODE`, never `ERROR_MESSAGE`;
5. unchanged error does not create another transition after delivered state matches;
6. changed error code creates `problem_changed`;
7. error recovery creates `recovered` after a delivered active error;
8. stale rule uses shared `MonitoringHealth` stale semantics/threshold;
9. unchanged stale condition deduplicates;
10. stale recovery works;
11. no-success-price requires missing successful value **and** `LAST_CHECK_AT != null`;
12. never-attempted `new` link does not immediately alert as no-price;
13. no-price recovery works;
14. overlapping rules remain distinct but are consolidated into one digest;
15. inactive link is excluded;
16. inactive competitor is excluded;
17. batch scan is finite and keyset-based;
18. product labels are batch-resolved, not N+1;
19. missing product falls back to `#ID`;
20. unsafe source URL is not emitted as clickable/usable link;
21. exact safe URL/query string is preserved;
22. raw `ERROR_MESSAGE` is absent from transport payload/template output;
23. notifications disabled -> runner performs no scan/mail/state write;
24. enabled + empty recipients -> no send and safe deterministic result;
25. recipient normalization validates/deduplicates addresses;
26. transport failure does not advance notification state;
27. transport success advances all delivered transitions;
28. next identical scan after success sends nothing;
29. `send_recovery=N` suppresses recovery delivery while correctly advancing baseline;
30. future problem after suppressed recovery notifies again;
31. one scan with multiple transitions results in one e-mail digest call;
32. dedicated DB lock prevents overlapping normal runs;
33. lock always releases in `finally`;
34. Agent and CLI invoke the same runner;
35. notification Agent is installed inactive with interval 3600;
36. settings page requires `Access::canWrite()`;
37. `R` cannot mutate notification settings;
38. options are disabled by default;
39. mail event/template installer is idempotent;
40. updater `0.15.0` is additive and does not rewrite monitoring/history rows;
41. dashboard read path does not invoke notification runner/transport;
42. product-card read path does not invoke notification runner/transport;
43. notification runner does not invoke `PriceUpdateService` or collectors;
44. existing Task 019–023 regressions remain green;
45. scheduler/CLI/Agent collection regressions remain green;
46. full PHPUnit passes;
47. full PHP syntax checks pass.

Tests must not depend on live Internet or real e-mail delivery.

Use a fake/in-memory notification transport for deterministic service tests.

## Real Bitrix smoke

After CI is green, verify on a real installation.

### Baseline before update

Capture immediately before update:

```text
module version = 0.14.5
monitored current-state rows
history total/per-link counts
collection Agent state
admin proxies
existing schema/index list
```

### Update lifecycle

1. Update `0.14.5 -> 0.15.0` through normal module update.
2. Confirm version `0.15.0`.
3. Confirm notification-state table + unique index exist.
4. Confirm existing monitoring/history schema/data unchanged.
5. Confirm current price/status/timestamps unchanged.
6. Confirm collection Agent unchanged.
7. Confirm notification Agent exists and is inactive.
8. Confirm notification settings default to disabled.
9. Confirm notification settings admin proxy/menu entry exists.

### Permission smoke

10. `W` user can open/save notification settings.
11. `R` user cannot mutate/open writable settings.
12. `D` user denied.

### Delivery-state smoke

Use a dedicated test recipient/address and only temporary controlled monitoring rows if needed.

13. Enable notifications and configure site + recipient.
14. Run notification CLI with no pending transition -> no mail/state mutation beyond necessary baseline handling.
15. Produce/use one current `error` state and run notification scan -> one digest delivered.
16. Run again unchanged -> no duplicate mail.
17. Change stable error code -> one changed-problem digest.
18. Recover to success -> one recovery digest when enabled.
19. Run again unchanged -> no duplicate recovery.
20. Verify no raw `ERROR_MESSAGE` appears in e-mail.
21. Verify exact safe source URL remains exact.
22. Verify notification-state rows reflect only successfully delivered baseline.

### Failure/retry smoke

23. Configure a controlled transport/mail failure where practical, or temporarily invalid delivery context without corrupting settings.
24. Confirm failed delivery does not advance notification state.
25. Restore valid delivery and rerun -> same pending transition is delivered and state advances.

### Read-only proof

Before and after several notification scans compare:

```text
CURRENT_PRICE
CURRENCY
STATUS
ERROR_CODE
ERROR_MESSAGE
LAST_CHECK_AT
LAST_SUCCESS_AT
history counts
```

They must remain unchanged by notification scans.

## Final acceptance

Task 024 passes when:

```text
0.15.0 update is additive and safe
notifications disabled by default
W-only settings work
notification Agent exists inactive
CLI and Agent share one runner
collection_error/stale/no_price rules are deterministic
problem_changed/recovery transitions work
unchanged states deduplicate
failed delivery remains retryable
one scan produces one digest
no raw error/secrets leak
notification state is persistent
notification scans never collect or mutate prices/history
existing dashboard/product-card behavior remains unchanged
full CI green
real Bitrix smoke green
```
