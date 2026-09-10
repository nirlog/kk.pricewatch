# ADR-0002: Competitor ORM entity

## Context

`kk.pricewatch` needs a persistent competitor entity before product-to-competitor links, admin UI, scheduling, and external collector integration can be implemented.

A competitor must describe the business/source identity and how its prices should be collected, without leaking site-specific details into the module schema.

The design must support the current in-process `MockCollector` and a future external collector service where a competitor can point to a handler such as `/api/collectors/dns` or `/api/collectors/browser`.

## Decision

Create D7 ORM entity `CompetitorTable` backed by table:

```text
b_kk_pricewatch_competitor
```

The entity stores only generic competitor and collector configuration.

### Fields

- `ID` — integer primary key, autoincrement.
- `NAME` — required human-readable competitor name, max 255 characters.
- `ACTIVE` — boolean stored as `Y`/`N`, default `Y`.
- `SORT` — integer, default `500`.
- `DOMAIN` — optional host/domain reference, max 255 characters.
- `COLLECTOR_TYPE` — required string, initially supports `mock` and `external`; default `mock`.
- `COLLECTOR_HANDLER` — optional string, max 512 characters. For a future external collector this is the handler path or endpoint reference, for example `/api/collectors/dns`.
- `COLLECTOR_OPTIONS` — JSON object stored in a text field. Default logical value is an empty object.
- `CREATED_AT` — creation timestamp.
- `UPDATED_AT` — last update timestamp.

Do not create competitor-specific columns such as `DNS_REGION`, `PRICE_SELECTOR`, `WAIT_SELECTOR`, `CITY`, or `BROWSER_MODE`.

Such values belong in `COLLECTOR_OPTIONS` unless a future cross-collector requirement proves that a setting deserves a first-class column.

## Collector type

Initial logical values:

```text
mock
external
```

`mock` is functional now.

`external` is persisted now so the schema does not need to change when the external Python Collector is introduced, but no network calls are implemented by this ADR/task.

Do not use a database-native enum. Use a normal string field plus application-level validation/constants so new collector types can be added without a database type migration.

## Collector handler semantics

`COLLECTOR_HANDLER` is intentionally generic.

Examples:

```text
/api/collectors/browser
/api/collectors/dns
```

The module must not branch on these values.

The future external collector client will resolve the handler against module-level collector connection settings or, if explicitly supported later, a full endpoint override.

For `mock`, the handler may be empty. Mock scenario configuration belongs in `COLLECTOR_OPTIONS` or a future dedicated configuration layer.

## Collector options

`COLLECTOR_OPTIONS` stores JSON, never PHP serialized data.

Logical examples:

```json
{}
```

or:

```json
{
  "region": "Санкт-Петербург",
  "price_selector": ".price"
}
```

The ORM/database layer must preserve arbitrary JSON object keys and must not understand competitor-specific keys.

The application should provide a small encoding/decoding helper that:

- accepts/returns PHP arrays representing JSON objects;
- encodes with exceptions enabled;
- rejects invalid JSON on decode;
- treats an empty database value as an empty options object only if that behavior is explicitly documented and tested;
- never uses `serialize()`/`unserialize()`.

## Domain

`DOMAIN` is metadata, not a routing key.

It must not be used to select a collector implementation and does not need to be unique in the first version.

Do not automatically rewrite competitor product URLs based on `DOMAIN`.

## Timestamps

`CREATED_AT` and `UPDATED_AT` are initialized on insert.

`UPDATED_AT` must change on update without callers having to remember to set it manually.

## Installation

Table creation must be idempotent:

- clean install creates the table;
- repeated schema installation does not fail if the table already exists;
- existing data must not be overwritten by repeated installation.

Use D7 database/ORM facilities rather than raw vendor-specific SQL where practical.

Module uninstall must preserve competitor data by default. Explicit delete-data uninstall behavior can be added in a later installer UX task.

The schema installation logic should be separated from the `CModule` entrypoint enough that future tables can reuse the same pattern.

## Versioning

The first persistent schema change should bump module version from `0.1.0` to `0.2.0`.

No Marketplace update package is required yet, but schema creation code must be reusable/idempotent so a future updater can invoke the same logic.

## Validation

At the application boundary:

- `NAME` must not be empty;
- `COLLECTOR_TYPE` must be a known type;
- `COLLECTOR_OPTIONS` must be valid JSON object data;
- `DOMAIN` and `COLLECTOR_HANDLER` may be empty.

Do not require `COLLECTOR_HANDLER` for `external` at the raw table level in this task. That cross-field configuration rule belongs to the future service/admin validation layer, where error feedback can be clearer.

## Security

This entity stores configuration only.

No URL or handler is executed/fetched in this task.

Do not place secrets/API tokens in `COLLECTOR_OPTIONS`; future collector credentials belong in module-level protected settings.

## Alternatives considered

### Dedicated columns for selectors and region

Rejected. Specialized collectors will require different configuration shapes and would cause schema churn.

### One serialized PHP configuration column

Rejected. PHP serialization creates unnecessary coupling and is unsuitable for a future Python service boundary.

### Store only competitor name and domain now

Rejected. Adding collector type/handler/options now is cheap and avoids a guaranteed schema migration when the external collector is introduced.

### Database enum for collector type

Rejected. Collector implementations are expected to evolve, so a normal string field is easier to extend.

## Consequences

Positive:

- generic competitor schema;
- no DNS/Kometa/RoyalPC coupling;
- external collector support can be added without altering the competitor table;
- arbitrary collector-specific options remain possible;
- schema is ready for the next product-link task.

Cost:

- validation of some cross-field rules is deferred until a competitor service/admin layer exists;
- `COLLECTOR_OPTIONS` requires careful JSON validation and UI mapping later.
