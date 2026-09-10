# ADR-0001: Collector boundary and MockCollector

## Context

`kk.pricewatch` must collect prices from heterogeneous sites. Some can be fetched through ordinary HTTP; others require JavaScript/browser execution; some may need a specialized collector.

The Bitrix module must be testable before the external Python Collector exists.

## Decision

The Bitrix module owns orchestration and state. Price acquisition is accessed through a stable `CollectorInterface`.

Initial implementation:
- `MockCollector` runs in-process.
- It implements the same logical contract as future collectors.
- No internal HTTP endpoint is required for the mock.

Future implementation:
- an HTTP-backed implementation sends the same logical request to an external service;
- a competitor may reference a handler such as `/api/collectors/dns`;
- the external Python service may expose generic and specialized collectors.

Module core must not know how a handler is implemented.

## Contract

Request contains schema version, request ID, batch item IDs/full URLs, and free-form options.

Response contains schema version, the same request ID, global status, and per-item results.

Successful price values use decimal strings such as `"129990.00"`.

## Mock behavior

Scenario URL match types:
- `exact`
- `contains`

A scenario returns either success or a defined item error.

No match supports:
- strict `MOCK_NO_MATCH`;
- configured default success.

Tests default to strict mode.

## Failure behavior

Global collector failures differ from item failures. A mixed batch must preserve successful items.

## Security

The mock performs no network access.

A future HTTP collector requires authentication, TLS for non-local deployment, explicit timeout, response validation, secret-safe logging, and safe endpoint handling.

## Alternatives

### Selenium/Python inside Bitrix
Rejected due to operational coupling.

### Mock through a local HTTP endpoint
Rejected for milestone 1 because it adds transport complexity without testing a real external service.

### Competitor-specific logic in Bitrix
Rejected because it couples module and collector release cycles.

## Consequences

The module can be built and tested before Python Collector exists, and externalization later becomes an implementation swap rather than a business-logic rewrite.
