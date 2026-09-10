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

## Adding another implementation

1. Implement `CollectorInterface`; do not change callers or special-case a
   competitor in module core.
2. Translate transport data only at the implementation boundary. Validate a
   future remote response before constructing response value objects.
3. Use explicit network timeouts and structured global errors for transport
   failures. Keep expected per-item failures in item results.
4. Add contract tests for batches, mixed results, identity round-tripping, money
   validation, and implementation-specific failure behavior.
