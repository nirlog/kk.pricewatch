<?php

declare(strict_types=1);

namespace KK\PriceWatch\Tests\Service;

use KK\PriceWatch\Service\PriceHistoryAnalyticsRepositoryInterface;
use KK\PriceWatch\Service\PriceHistoryAnalyticsService;
use PHPUnit\Framework\TestCase;

final class PriceHistoryAnalyticsServiceTest extends TestCase
{
    public function testEmptyAndSinglePointStates(): void
    {
        self::assertTrue($this->analyze([])->isEmpty());
        $result = $this->analyze([$this->point(7, '9999999999999999.99', '2026-01-01 00:00:00')]);
        self::assertSame(1, $result->pointCount);
        self::assertSame('9999999999999999.99', $result->minimumPrice);
        self::assertSame($result->first, $result->latest);
        self::assertNull($result->previous);
        self::assertCount(1, $result->chartPoints);
    }

    public function testEffectiveAndTimelineOrdersAreIndependentAndDecimalSafe(): void
    {
        $result = $this->analyze([
            $this->point(12, '100.00', '2026-01-01 10:00:00'),
            $this->point(10, '100.00', '2026-01-01 10:00:01'),
            $this->point(11, '9.99', '2026-01-01 10:00:00'),
        ]);
        self::assertSame(10, $result->first['id']);
        self::assertSame(11, $result->previous['id']);
        self::assertSame(12, $result->latest['id']);
        self::assertSame('9.99', $result->minimumPrice);
        self::assertSame('100.00', $result->maximumPrice);
        self::assertSame(PriceHistoryAnalyticsService::DIRECTION_UP, $result->direction);
        self::assertSame([11, 12, 10], array_column($result->timeline, 'id'));
        self::assertGreaterThan(0, PriceHistoryAnalyticsService::comparePrices('1000000000000000.00', '999999999999999.99'));
    }

    public function testReturnToFirstPriceUsesPreviousPointForDirection(): void
    {
        $result = $this->analyze([
            $this->point(1, '100.00', '2026-01-01 00:00:00'),
            $this->point(2, '120.00', '2026-01-02 00:00:00'),
            $this->point(3, '100.00', '2026-01-03 00:00:00'),
        ]);
        self::assertSame(3, $result->pointCount);
        self::assertSame('100.00', $result->first['price']);
        self::assertSame('120.00', $result->previous['price']);
        self::assertSame('100.00', $result->latest['price']);
        self::assertSame(PriceHistoryAnalyticsService::DIRECTION_DOWN, $result->direction);
    }

    public function testMixedCurrenciesCannotBeComparedOrCharted(): void
    {
        $result = $this->analyze([$this->point(1, '10.00', '2026-01-01', 'RUB'), $this->point(2, '1.00', '2026-01-02', 'USD')]);
        self::assertTrue($result->mixedCurrencies);
        self::assertNull($result->minimumPrice);
        self::assertNull($result->maximumPrice);
        self::assertNull($result->currency);
        self::assertSame([], $result->chartPoints);
    }

    public function testChartCapUsesLatestTimelineWindowButSummaryUsesAllPoints(): void
    {
        $rows = [];
        for ($id = 1; $id <= 505; $id++) $rows[] = $this->point($id, $id . '.00', sprintf('2026-01-%02d 00:00:00', (($id - 1) % 28) + 1));
        $result = $this->analyze($rows);
        self::assertSame(505, $result->pointCount);
        self::assertSame('1.00', $result->minimumPrice);
        self::assertSame('505.00', $result->maximumPrice);
        self::assertTrue($result->chartTruncated);
        self::assertCount(500, $result->chartPoints);
        self::assertSame(array_slice(array_column($result->timeline, 'id'), -500), array_column($result->chartPoints, 'id'));
    }

    private function analyze(array $rows): object
    {
        $repository = new class($rows) implements PriceHistoryAnalyticsRepositoryInterface {
            public function __construct(private array $rows) {}
            public function findCurrentIdentityPoints(int $linkId, int $productId, int $competitorId, string $urlHash): array { return $this->rows; }
        };
        return (new PriceHistoryAnalyticsService($repository))->analyze(1, 2, 3, str_repeat('a', 64));
    }

    private function point(int $id, string $price, string $date, string $currency = 'RUB'): array
    {
        return ['ID' => $id, 'PRICE' => $price, 'CURRENCY' => $currency, 'COLLECTED_AT' => $date];
    }
}
