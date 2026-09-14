<?php

declare(strict_types=1);

namespace KK\PriceWatch\Service;

interface StaffProductPriceRepositoryInterface
{
    /** @return list<array<string, mixed>> */
    public function findActiveByExactProductId(int $productId): array;
}
