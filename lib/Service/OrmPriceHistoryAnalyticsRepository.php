<?php

declare(strict_types=1);

namespace KK\PriceWatch\Service;

use KK\PriceWatch\Model\PriceHistoryTable;

final class OrmPriceHistoryAnalyticsRepository implements PriceHistoryAnalyticsRepositoryInterface
{
    public function findCurrentIdentityPoints(int $linkId, int $productId, int $competitorId, string $urlHash): array
    {
        return PriceHistoryTable::getList([
            'select' => ['ID', 'PRICE', 'CURRENCY', 'COLLECTED_AT'],
            'filter' => [
                '=PRODUCT_COMPETITOR_ID' => $linkId,
                '=PRODUCT_ID' => $productId,
                '=COMPETITOR_ID' => $competitorId,
                '=URL_HASH' => $urlHash,
            ],
            // ID is the effective commit order established by Task 019.
            'order' => ['ID' => 'ASC'],
        ])->fetchAll();
    }
}
