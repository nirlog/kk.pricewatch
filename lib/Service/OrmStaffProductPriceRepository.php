<?php

declare(strict_types=1);

namespace KK\PriceWatch\Service;

use KK\PriceWatch\Model\CompetitorTable;
use KK\PriceWatch\Model\ProductCompetitorTable;

final class OrmStaffProductPriceRepository implements StaffProductPriceRepositoryInterface
{
    private const DEFAULT_QUERY_LIMIT = 51;
    private const MAX_QUERY_LIMIT = 201;

    public function findActiveByExactProductId(int $productId, int $limit): array
    {
        $limit = $limit > 0 ? min($limit, self::MAX_QUERY_LIMIT) : self::DEFAULT_QUERY_LIMIT;

        return ProductCompetitorTable::getList([
            'select' => [
                'ID', 'COMPETITOR_ID', 'URL', 'CURRENT_PRICE', 'CURRENCY', 'STATUS',
                'LAST_CHECK_AT', 'LAST_SUCCESS_AT',
                'COMPETITOR_NAME' => 'COMPETITOR.NAME',
                'COMPETITOR_SORT' => 'COMPETITOR.SORT',
            ],
            'filter' => [
                '=PRODUCT_ID' => $productId,
                '=ACTIVE' => 'Y',
                '=COMPETITOR.ACTIVE' => 'Y',
            ],
            'order' => [
                'COMPETITOR_SORT' => 'ASC',
                'COMPETITOR_NAME' => 'ASC',
                'ID' => 'ASC',
            ],
            'limit' => $limit,
        ])->fetchAll();
    }
}
