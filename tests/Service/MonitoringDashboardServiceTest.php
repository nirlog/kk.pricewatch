<?php

declare(strict_types=1);

namespace KK\PriceWatch\Tests\Service;

use DateTimeImmutable;
use DateTimeInterface;
use KK\PriceWatch\Service\MonitoringDashboardRepositoryInterface;
use KK\PriceWatch\Service\MonitoringDashboardService;
use KK\PriceWatch\Service\MonitoringHealth;
use PHPUnit\Framework\TestCase;

final class MonitoringDashboardServiceTest extends TestCase
{
    public function testCentralHealthSemanticsAndOverlap(): void
    {
        $cutoff = new DateTimeImmutable('2026-09-14 12:00:00 UTC');
        $errorStale = ['STATUS' => 'error', 'CURRENT_PRICE' => '10.00', 'CURRENCY' => 'RUB', 'LAST_SUCCESS_AT' => new DateTimeImmutable('2026-09-13 UTC')];
        $state = MonitoringHealth::describe($errorStale, $cutoff);
        self::assertTrue($state['is_error']);
        self::assertTrue($state['is_stale']);
        self::assertFalse($state['is_healthy']);

        $noPriceError = MonitoringHealth::describe(['STATUS' => 'error', 'CURRENT_PRICE' => null, 'CURRENCY' => null, 'LAST_SUCCESS_AT' => null], $cutoff);
        self::assertTrue($noPriceError['is_error']);
        self::assertFalse($noPriceError['has_successful_value']);
        self::assertSame(86400, MonitoringHealth::STALE_AFTER_SECONDS);
        $problemFilter = MonitoringHealth::ormFilter('problems', $cutoff);
        self::assertSame('OR', $problemFilter['LOGIC']);
    }

    public function testFiltersAndSortingAreStrictlyNormalized(): void
    {
        $service = new MonitoringDashboardService(new MonitoringRepositoryStub());
        self::assertSame(
            ['PRODUCT_ID' => 12, 'COMPETITOR_ID' => 4, 'STATUS' => 'error', 'HEALTH' => 'problems'],
            $service->normalizeFilters(['product_id' => '12', 'competitor_id' => '4', 'status' => 'error', 'health' => 'problems'])
        );
        self::assertSame([], $service->normalizeFilters(['product_id' => '-1 OR 1=1', 'competitor_id' => 0, 'status' => 'bad', 'health' => 'bad']));
        self::assertSame(['LAST_CHECK_AT' => 'DESC', 'ID' => 'DESC'], $service->order('raw sql', 'raw'));
        self::assertSame(['PRODUCT_ID' => 'ASC', 'ID' => 'ASC'], $service->order('product_id', 'asc'));
    }

    public function testLoadKeepsFinitePaginationAndDecoratesRows(): void
    {
        $repository = new MonitoringRepositoryStub();
        $service = new MonitoringDashboardService($repository, new DateTimeImmutable('2026-09-15 12:00:00 UTC'));
        $result = $service->load([], ['ID' => 'ASC'], 500, -10);
        self::assertSame(200, $repository->limit);
        self::assertSame(0, $repository->offset);
        self::assertSame(1, $result->totalRows);
        self::assertTrue($result->rows[0]['is_healthy']);
    }
}

final class MonitoringRepositoryStub implements MonitoringDashboardRepositoryInterface
{
    public int $limit = 0;
    public int $offset = 0;
    public function summary(DateTimeInterface $cutoff): array { return ['total_active' => 1, 'healthy' => 1, 'errors' => 0, 'stale' => 0, 'no_success_price' => 0]; }
    public function count(array $filters, DateTimeInterface $cutoff): int { return 1; }
    public function page(array $filters, DateTimeInterface $cutoff, array $order, int $limit, int $offset): array
    {
        $this->limit = $limit; $this->offset = $offset;
        return [['STATUS' => 'success', 'CURRENT_PRICE' => '100.00', 'CURRENCY' => 'RUB', 'LAST_SUCCESS_AT' => new DateTimeImmutable('2026-09-15 11:00:00 UTC')]];
    }
}
