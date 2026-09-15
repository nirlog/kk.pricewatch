<?php

declare(strict_types=1);

namespace KK\PriceWatch\Service;

final class MonitoringDashboardResult
{
    /** @param array{total_active:int,healthy:int,errors:int,stale:int,no_success_price:int} $summary @param list<array<string,mixed>> $rows */
    public function __construct(public readonly array $summary, public readonly int $totalRows, public readonly array $rows)
    {
    }
}
