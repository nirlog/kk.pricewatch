<?php

declare(strict_types=1);

namespace KK\PriceWatch\Service;

use DateTimeInterface;

interface MonitoringDashboardRepositoryInterface
{
    /** @return array{total_active:int,healthy:int,errors:int,stale:int,no_success_price:int} */
    public function summary(DateTimeInterface $cutoff): array;

    /** @param array<string, int|string> $filters */
    public function count(array $filters, DateTimeInterface $cutoff): int;

    /** @param array<string, int|string> $filters @param array<string, string> $order @return list<array<string, mixed>> */
    public function page(array $filters, DateTimeInterface $cutoff, array $order, int $limit, int $offset): array;
}
