<?php

declare(strict_types=1);

namespace KK\PriceWatch\Service;

final class StaffProductPriceReadResult
{
    /** @param list<array<string, mixed>> $rows */
    public function __construct(
        public readonly array $rows,
        public readonly bool $hasMore,
    ) {}
}
