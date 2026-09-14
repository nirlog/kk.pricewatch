<?php

declare(strict_types=1);

namespace KK\PriceWatch\Service;

use KK\PriceWatch\Model\CompetitorTable;
use KK\PriceWatch\Model\ProductCompetitorTable;

final class OrmStaffProductPriceRepository implements StaffProductPriceRepositoryInterface
{
    public function findActiveByExactProductId(int $productId): array
    {
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
        ])->fetchAll();
    }
}
