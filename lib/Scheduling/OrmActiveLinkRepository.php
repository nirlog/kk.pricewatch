<?php

declare(strict_types=1);

namespace KK\PriceWatch\Scheduling;

use KK\PriceWatch\Model\ProductCompetitorTable;

final class OrmActiveLinkRepository implements ActiveLinkRepositoryInterface
{
    public function maximumActiveId(): ?int
    {
        $row = ProductCompetitorTable::getList([
            'filter' => ['=ACTIVE' => 'Y'],
            'select' => ['ID'],
            'order' => ['ID' => 'DESC'],
            'limit' => 1,
        ])->fetch();

        return $row === false ? null : (int) $row['ID'];
    }

    public function findActiveIdsAfter(int $lastId, int $maximumId, int $limit): array
    {
        $result = ProductCompetitorTable::getList([
            'filter' => ['=ACTIVE' => 'Y', '>ID' => $lastId, '<=ID' => $maximumId],
            'select' => ['ID'],
            'order' => ['ID' => 'ASC'],
            'limit' => $limit,
        ]);
        $ids = [];
        while ($row = $result->fetch()) {
            $ids[] = (int) $row['ID'];
        }
        return $ids;
    }
}
