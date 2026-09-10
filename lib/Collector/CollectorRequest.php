<?php

declare(strict_types=1);

namespace KK\PriceWatch\Collector;

use KK\PriceWatch\Collector\Exception\InvalidRequestException;

final readonly class CollectorRequest
{
    public const SCHEMA_VERSION = '1.0';

    /** @var list<CollectorItem> */
    public array $items;

    /**
     * @param list<CollectorItem> $items
     * @param array<string, mixed> $options
     */
    public function __construct(
        public string $schemaVersion,
        public string $requestId,
        array $items,
        public array $options = [],
    ) {
        if ($schemaVersion !== self::SCHEMA_VERSION) {
            throw new InvalidRequestException('Unsupported collector schema version.');
        }
        if (trim($requestId) === '') {
            throw new InvalidRequestException('Collector request ID must not be empty.');
        }
        if ($items === []) {
            throw new InvalidRequestException('Collector request must contain at least one item.');
        }

        $seen = [];
        foreach ($items as $item) {
            if (!$item instanceof CollectorItem) {
                throw new InvalidRequestException('Collector request contains an invalid item.');
            }
            if (isset($seen[$item->id])) {
                throw new InvalidRequestException(sprintf('Duplicate collector item ID "%s".', $item->id));
            }
            $seen[$item->id] = true;
        }
        $this->items = array_values($items);
    }

    /** @return array{schema_version: string, request_id: string, items: list<array{id: string, url: string}>, options: array<string, mixed>} */
    public function toArray(): array
    {
        return [
            'schema_version' => $this->schemaVersion,
            'request_id' => $this->requestId,
            'items' => array_map(static fn (CollectorItem $item): array => $item->toArray(), $this->items),
            'options' => $this->options,
        ];
    }
}
