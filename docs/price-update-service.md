# Price update orchestration

Create the default service and pass a finite batch of monitored-link IDs (not product IDs):

```php
$result = \KK\PriceWatch\Service\PriceUpdateServiceFactory::createDefault()
    ->updateLinks([$linkId1, $linkId2]);
```

The service removes duplicate IDs, loads links and competitors in two batch queries, and makes one collector request per active competitor. Future agents should select and chunk IDs themselves, then use this same entry point. Scheduling is not part of this service.

## Mock options

Store a JSON object in the competitor's `COLLECTOR_OPTIONS`:

```json
{
  "scenarios": [
    {
      "match": {"field": "url", "type": "exact", "value": "https://example.test/product/1?a=1&b=2"},
      "result": {"type": "success", "price": "129990.00", "currency": "RUB"}
    },
    {
      "match": {"field": "url", "type": "contains", "value": "/unavailable/"},
      "result": {"type": "error", "code": "PRICE_NOT_FOUND", "message": "Price was not found"}
    }
  ],
  "default": {"price": "99999.00", "currency": "RUB"}
}
```

Both keys are optional. Without `default`, an unmatched URL produces `MOCK_NO_MATCH`. Other option keys are passed unchanged in the collector request. Exact matching receives the stored link URL without normalization.

Success replaces price/currency, clears errors, and updates both attempt timestamps. Item, global, configuration, and correlation errors update status/error and `LAST_CHECK_AT` while preserving the last successful price, currency, and `LAST_SUCCESS_AT`. Inactive links and competitors are skipped without mutation. The `external` type is stored but no transport exists yet; attempting it performs no network access and records a safe `COLLECTOR_ERROR`.

## Real-Bitrix smoke test

On a development installation, record Bitrix/PHP/database versions and the test date. Verify module 0.6.0 loads; exact success (including query parameters), item error, strict no-match, mixed same-competitor results, separate competitors, inactive link and inactive competitor behavior. Confirm stale values survive errors and timestamps follow the rules above. Finally set a competitor to `external`, invoke it, and confirm a safe error with no network call, then reload the existing product monitoring tab to inspect the persisted state.
