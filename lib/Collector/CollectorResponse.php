<?php

declare(strict_types=1);

namespace KK\PriceWatch\Collector;

use InvalidArgumentException;

final readonly class CollectorResponse
{
    /** @var list<CollectorItemResult> */
    public array $items;

    /** @param list<CollectorItemResult> $items */
    private function __construct(
        public string $schemaVersion,
        public string $requestId,
        public bool $success,
        array $items,
        public ?CollectorError $error,
    ) {
        if ($schemaVersion !== CollectorRequest::SCHEMA_VERSION || trim($requestId) === '') {
            throw new InvalidArgumentException('Invalid collector response identity.');
        }

        if ($success && $error !== null) {
            throw new InvalidArgumentException('A successful collector response must not contain a global error.');
        }
        if (!$success && $error === null) {
            throw new InvalidArgumentException('A failed collector response must contain a global error.');
        }
        if (!$success && $items !== []) {
            throw new InvalidArgumentException('A failed collector response must not contain item results.');
        }

        $seen = [];
        foreach ($items as $item) {
            if (!$item instanceof CollectorItemResult) {
                throw new InvalidArgumentException('Collector response contains an invalid item.');
            }
            if (isset($seen[$item->id])) {
                throw new InvalidArgumentException(sprintf('Duplicate collector response item ID "%s".', $item->id));
            }
            $seen[$item->id] = true;
        }
        $this->items = array_values($items);
    }

    /** @param list<CollectorItemResult> $items */
    public static function success(string $requestId, array $items): self
    {
        return new self(CollectorRequest::SCHEMA_VERSION, $requestId, true, $items, null);
    }

    public static function failure(string $requestId, string $code, string $message): self
    {
        return new self(
            CollectorRequest::SCHEMA_VERSION,
            $requestId,
            false,
            [],
            new CollectorError($code, $message),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $response = [
            'schema_version' => $this->schemaVersion,
            'request_id' => $this->requestId,
            'success' => $this->success,
            'items' => array_map(static fn (CollectorItemResult $item): array => $item->toArray(), $this->items),
        ];

        if ($this->error !== null) {
            $response['error'] = $this->error->toArray();
        }

        return $response;
    }
}
