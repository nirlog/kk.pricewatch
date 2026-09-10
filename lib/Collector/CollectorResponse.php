<?php

declare(strict_types=1);

namespace KK\PriceWatch\Collector;

use InvalidArgumentException;

final readonly class CollectorResponse
{
    /** @var list<CollectorItemResult> */
    public array $items;

    /** @param list<CollectorItemResult> $items */
    public function __construct(
        public string $schemaVersion,
        public string $requestId,
        public bool $success,
        array $items,
    ) {
        if ($schemaVersion !== CollectorRequest::SCHEMA_VERSION || trim($requestId) === '') {
            throw new InvalidArgumentException('Invalid collector response identity.');
        }
        foreach ($items as $item) {
            if (!$item instanceof CollectorItemResult) {
                throw new InvalidArgumentException('Collector response contains an invalid item.');
            }
        }
        $this->items = array_values($items);
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'schema_version' => $this->schemaVersion,
            'request_id' => $this->requestId,
            'success' => $this->success,
            'items' => array_map(static fn (CollectorItemResult $item): array => $item->toArray(), $this->items),
        ];
    }
}
