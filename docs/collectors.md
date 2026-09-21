# Collector developer guide

Collector implementations depend only on the value objects in
`KK\PriceWatch\Collector` and implement `CollectorInterface::collect()`. The
logical contract is transport-independent: accept one `CollectorRequest` and
return one `CollectorResponse`. Implementations must preserve the schema version,
request ID, item IDs, and full URLs (including query parameters), and must return
one item result for every requested item.

Expected item-level failures belong in `CollectorItemResult::failure()`. A failed
item must not prevent other batch items from succeeding. Prices must remain
non-negative decimal strings and currencies uppercase three-letter codes; the
success factory validates both without converting prices to floating point.

Construct a completed, usable batch with `CollectorResponse::success($requestId,
$items)`. Its global `success` remains true even when one or more item results
are failures, and its serialized representation omits the global `error` field.
Use `CollectorResponse::failure($requestId, $code, $message)` when the collector
request itself fails, for example with `COLLECTOR_TIMEOUT`. A global failure has
a structured `CollectorError` and an empty item collection; it is distinct from
an expected error affecting only one item.

## Using the mock

```php
use KK\PriceWatch\Collector\CollectorItem;
use KK\PriceWatch\Collector\CollectorRequest;
use KK\PriceWatch\Collector\Mock\MockCollector;
use KK\PriceWatch\Collector\Mock\MockDefaultResult;

$collector = new MockCollector([
    [
        'match' => [
            'field' => 'url',
            'type' => 'exact',
            'value' => 'https://example.test/product/1?region=msk',
        ],
        'result' => [
            'type' => 'success',
            'price' => '99990.00',
            'currency' => 'RUB',
        ],
    ],
]);

$response = $collector->collect(new CollectorRequest(
    '1.0',
    'request-1',
    [new CollectorItem('link-1', 'https://example.test/product/1?region=msk')],
));
```

`MockCollector` is strict by default and returns the structured item error
`MOCK_NO_MATCH` for an unmatched URL. Pass a `MockDefaultResult` as the second
constructor argument to enable default-success mode:

```php
$collector = new MockCollector([], new MockDefaultResult('100.00', 'RUB'));
```

Scenarios are evaluated in declaration order and support `exact` and `contains`
URL matching. They can produce either a `success` result or an `error` result
with an application-stable code and a human-readable message.

## Using the HTTP HTML collector

Select collector type `http`, set `DOMAIN` to the exact allowed hostname (without
a scheme or path), leave `COLLECTOR_HANDLER` empty, and configure XPath extraction:

```json
{
  "price_selector": {
    "type": "xpath",
    "value": "//REPLACE_WITH_VERIFIED_PRICE_NODE"
  },
  "currency": "RUB"
}
```

The collector accepts only absolute HTTP(S) item URLs on that exact, non-IP
hostname. Redirects and private-network access are disabled. It accepts
`text/html` and `application/xhtml+xml` responses and extracts node text without
executing scripts. Update the XPath in competitor configuration when page markup
changes; no competitor-specific XPath belongs in PHP.

Expected failures remain item-local: `URL_NOT_ALLOWED`, `HTTP_REQUEST_FAILED`,
`HTTP_STATUS`, `INVALID_CONTENT_TYPE`, `HTML_PARSE_ERROR`, `PRICE_NOT_FOUND`,
`PRICE_AMBIGUOUS`, and `PRICE_INVALID`. Transport details and response bodies are
not included in these errors.

## Adding another implementation

1. Implement `CollectorInterface`; do not change callers or special-case a
   competitor in module core.
2. Translate transport data only at the implementation boundary. Validate a
   future remote response before constructing response value objects.
3. Use explicit network timeouts and structured global errors for transport
   failures. Keep expected per-item failures in item results.
4. Add contract tests for batches, mixed results, identity round-tripping, money
   validation, and implementation-specific failure behavior.

## External Collector API

Select `external` for a competitor and set `COLLECTOR_HANDLER` to an absolute path
such as `/api/collectors/browser`. The handler is routing metadata expressed only
in the endpoint path: it cannot contain a URL, authority, query, fragment,
backslash, control character, or `..` segment. It is appended to the globally
configured, normalized service base URL (including any base path).

Configure the service under **KK PriceWatch → External collector**. The subsystem
is disabled by default. Enabling requires a base URL, Bearer API token, connection
timeout (1–30 seconds), and request timeout (5–300 seconds, not shorter than the
connection timeout). Non-loopback services require HTTPS. Plain HTTP is accepted
only for `localhost`, `127.0.0.1`, and `::1`; validation performs no DNS lookup.
Base URLs cannot contain credentials, query strings, or fragments.

The module sends `POST` with `Content-Type` and `Accept: application/json` and an
`Authorization: Bearer` header. Redirects are disabled and responses are limited
to 2 MiB. The body is exactly collector contract 1.0 (`schema_version`,
`request_id`, `items`, and opaque `options`); handler, ORM IDs, and secrets are
never added. Empty options are serialized as the JSON object `{}`, never as a
list. Full item URLs and query parameters are preserved.

The service must return a JSON media type and a strict contract 1.0 response.
Unknown fields are permitted, but required field types, identities, unique item
IDs, decimal-string money, currency, and structured errors are validated before
any result reaches persistence. A protocol-level global error uses HTTP 200 with
`success: false`, empty `items`, and `error`; per-item errors coexist with other
successful items. Non-2xx responses are transport failures. Timeout maps to
`COLLECTOR_TIMEOUT`; network/DNS/connect/TLS and non-2xx failures map to
`COLLECTOR_ERROR`; oversized, non-JSON, malformed JSON, or invalid-contract
responses map to `INVALID_RESPONSE`.
Before a valid remote error is returned to orchestration, any exact occurrence
of the configured bearer token in its message is replaced with `[REDACTED]`.

The token is stored only as a module option and sent only in the Authorization
header. Its saved value is never rendered (even partially); a blank password
field preserves it. It must not be put in URLs, competitor options, request
bodies, logs, history, monitoring records, or error messages. The adapter does
not retry, redirect, execute browsers, or implement competitor-specific logic.
