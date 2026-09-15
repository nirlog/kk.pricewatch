<?php

declare(strict_types=1);

namespace KK\PriceWatch\Service;

use DateTimeImmutable;
use DateTimeInterface;
use KK\PriceWatch\Model\CollectionStatus;

final class MonitoringDashboardService
{
    public const SORT_FIELDS = ['ID', 'PRODUCT_ID', 'COMPETITOR_ID', 'CURRENT_PRICE', 'STATUS', 'LAST_CHECK_AT', 'LAST_SUCCESS_AT'];

    public function __construct(
        private readonly MonitoringDashboardRepositoryInterface $repository,
        private readonly ?DateTimeInterface $now = null
    ) {
    }

    /** @param array<string, mixed> $input */
    public function normalizeFilters(array $input): array
    {
        $filters = [];
        foreach (['PRODUCT_ID' => 'product_id', 'COMPETITOR_ID' => 'competitor_id'] as $field => $key) {
            $value = filter_var($input[$key] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if ($value !== false) $filters[$field] = (int) $value;
        }
        $status = (string) ($input['status'] ?? '');
        if (CollectionStatus::isValid($status)) $filters['STATUS'] = $status;
        $health = MonitoringHealth::normalize((string) ($input['health'] ?? ''));
        if ($health !== MonitoringHealth::ALL) $filters['HEALTH'] = $health;
        return $filters;
    }

    public function order(string $field, string $direction): array
    {
        $field = strtoupper($field);
        if (!in_array($field, self::SORT_FIELDS, true)) $field = 'LAST_CHECK_AT';
        $direction = strtolower($direction) === 'asc' ? 'ASC' : 'DESC';
        $order = [$field => $direction];
        if ($field !== 'ID') $order['ID'] = $direction;
        return $order;
    }

    /** @param array<string, int|string> $filters @param array<string,string> $order */
    public function load(array $filters, array $order, int $limit, int $offset): MonitoringDashboardResult
    {
        $limit = max(1, min(200, $limit));
        $offset = max(0, $offset);
        $cutoff = MonitoringHealth::cutoff($this->now ?? new DateTimeImmutable());
        $summary = $this->repository->summary($cutoff);
        $total = $this->repository->count($filters, $cutoff);
        $rows = $this->repository->page($filters, $cutoff, $order, $limit, $offset);
        foreach ($rows as &$row) $row += MonitoringHealth::describe($row, $cutoff);
        unset($row);
        return new MonitoringDashboardResult($summary, $total, $rows);
    }
}
