<?php

declare(strict_types=1);

namespace KK\PriceWatch\Service;

interface PriceHistoryAnalyticsRepositoryInterface
{
    /** @return list<array{ID:int|string, PRICE:string, CURRENCY:string, COLLECTED_AT:mixed}> */
    public function findCurrentIdentityPoints(int $linkId, int $productId, int $competitorId, string $urlHash): array;
}
