<?php

declare(strict_types=1);

namespace KK\PriceWatch\Service;

use Bitrix\Main\Type\DateTime as BitrixDateTime;
use DateTimeImmutable;
use DateTimeInterface;
use KK\PriceWatch\Model\ProductCompetitorTable;

final class OrmMonitoringDashboardRepository implements MonitoringDashboardRepositoryInterface
{
    public function summary(DateTimeInterface $cutoff): array
    {
        return [
            'total_active' => ProductCompetitorTable::getCount($this->scope()),
            'healthy' => ProductCompetitorTable::getCount($this->filter([], $cutoff, MonitoringHealth::HEALTHY)),
            'errors' => ProductCompetitorTable::getCount($this->filter([], $cutoff, MonitoringHealth::ERROR)),
            'stale' => ProductCompetitorTable::getCount($this->filter([], $cutoff, MonitoringHealth::STALE)),
            'no_success_price' => ProductCompetitorTable::getCount($this->filter([], $cutoff, MonitoringHealth::NO_PRICE)),
        ];
    }

    public function count(array $filters, DateTimeInterface $cutoff): int
    {
        return ProductCompetitorTable::getCount($this->filter($filters, $cutoff));
    }

    public function page(array $filters, DateTimeInterface $cutoff, array $order, int $limit, int $offset): array
    {
        $result = ProductCompetitorTable::getList([
            'select' => ['ID', 'PRODUCT_ID', 'COMPETITOR_ID', 'URL', 'CURRENT_PRICE', 'CURRENCY', 'STATUS',
                'ERROR_CODE', 'LAST_CHECK_AT', 'LAST_SUCCESS_AT', 'COMPETITOR_NAME' => 'COMPETITOR.NAME'],
            'filter' => $this->filter($filters, $cutoff),
            'order' => $order,
            'limit' => max(1, $limit),
            'offset' => max(0, $offset),
        ]);
        $rows = [];
        while ($row = $result->fetch()) $rows[] = self::normalizeRow($row);
        return $rows;
    }

    private static function normalizeRow(array $row): array
    {
        foreach (['LAST_CHECK_AT', 'LAST_SUCCESS_AT'] as $field) {
            if (($row[$field] ?? null) instanceof BitrixDateTime) {
                $row[$field] = (new DateTimeImmutable())->setTimestamp($row[$field]->getTimestamp());
            }
        }

        return $row;
    }

    private function scope(): array
    {
        return ['=ACTIVE' => 'Y', '=COMPETITOR.ACTIVE' => 'Y'];
    }

    private function filter(array $filters, DateTimeInterface $cutoff, ?string $forcedHealth = null): array
    {
        $result = $this->scope();
        foreach (['PRODUCT_ID', 'COMPETITOR_ID', 'STATUS'] as $field) {
            if (isset($filters[$field])) $result['=' . $field] = $filters[$field];
        }
        $health = $forcedHealth ?? (string) ($filters['HEALTH'] ?? '');
        $healthFilter = MonitoringHealth::ormFilter($health, $cutoff);
        if ($healthFilter !== []) $result[] = $healthFilter;

        return self::adaptFilterForBitrix($result);
    }

    private static function adaptFilterForBitrix(mixed $value): mixed
    {
        if ($value instanceof DateTimeInterface) {
            return BitrixDateTime::createFromTimestamp($value->getTimestamp());
        }

        if (is_array($value)) {
            foreach ($value as $key => $item) {
                $value[$key] = self::adaptFilterForBitrix($item);
            }
        }

        return $value;
    }
}
