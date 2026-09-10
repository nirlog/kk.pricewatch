<?php

declare(strict_types=1);

namespace KK\PriceWatch\Collector;

use KK\PriceWatch\Collector\Exception\InvalidRequestException;

final readonly class CollectorItem
{
    public function __construct(public string $id, public string $url)
    {
        if (trim($id) === '') {
            throw new InvalidRequestException('Collector item ID must not be empty.');
        }
        if (trim($url) === '') {
            throw new InvalidRequestException('Collector item URL must not be empty.');
        }
    }

    /** @return array{id: string, url: string} */
    public function toArray(): array
    {
        return ['id' => $this->id, 'url' => $this->url];
    }
}
