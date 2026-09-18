<?php
declare(strict_types=1);
namespace KK\PriceWatch\Notification;

use Bitrix\Main\Type\DateTime as BitrixDateTime;
use DateTimeImmutable;
use KK\PriceWatch\Model\ProductCompetitorTable;

final class OrmNotificationLinkRepository implements NotificationLinkRepositoryInterface
{
    public function maximumActiveId(): ?int
    {
        $row = ProductCompetitorTable::getList(['select' => ['ID'], 'filter' => $this->scope(), 'order' => ['ID' => 'DESC'], 'limit' => 1])->fetch();
        return $row ? (int) $row['ID'] : null;
    }

    public function findActiveAfter(int $lastId, int $maximumId, int $limit): array
    {
        $query = ProductCompetitorTable::getList([
            'select' => ['ID', 'PRODUCT_ID', 'COMPETITOR_ID', 'URL', 'CURRENT_PRICE', 'CURRENCY', 'STATUS',
                'ERROR_CODE', 'LAST_CHECK_AT', 'LAST_SUCCESS_AT', 'COMPETITOR_NAME' => 'COMPETITOR.NAME'],
            'filter' => $this->scope() + ['>ID' => $lastId, '<=ID' => $maximumId],
            'order' => ['ID' => 'ASC'], 'limit' => max(1, $limit),
        ]);
        $rows = [];
        while ($row = $query->fetch()) {
            foreach (['LAST_CHECK_AT', 'LAST_SUCCESS_AT'] as $field) {
                if (($row[$field] ?? null) instanceof BitrixDateTime) $row[$field] = (new DateTimeImmutable())->setTimestamp($row[$field]->getTimestamp());
            }
            $rows[] = $row;
        }
        return $rows;
    }

    private function scope(): array { return ['=ACTIVE' => 'Y', '=COMPETITOR.ACTIVE' => 'Y']; }
}
