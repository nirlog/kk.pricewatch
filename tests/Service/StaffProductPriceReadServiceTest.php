<?php

declare(strict_types=1);

namespace KK\PriceWatch\Tests\Service;

use DateTimeImmutable;
use KK\PriceWatch\Service\StaffProductPriceReadService;
use KK\PriceWatch\Service\StaffProductPriceRepositoryInterface;
use PHPUnit\Framework\TestCase;

final class StaffProductPriceReadServiceTest extends TestCase
{
    public function testInvalidProductNeverQueriesRepository(): void
    {
        $repository = new RecordingRepository([]);
        self::assertSame([], (new StaffProductPriceReadService($repository))->read(0));
        self::assertSame([], $repository->productIds);
    }

    public function testExactDecimalAndOperationalStatesArePreserved(): void
    {
        $repository = new RecordingRepository([
            $this->row(11, '1234567890123456.78', 'RUB', 'success', '2026-09-14 11:59:00'),
            $this->row(12, '99.00', 'RUB', 'error', '2026-09-14 10:00:00'),
            $this->row(13, null, null, 'new', null),
        ]);
        $service = new StaffProductPriceReadService($repository, static fn(): int => strtotime('2026-09-14 12:00:00 UTC'));

        $rows = $service->read(777, 3600);

        self::assertSame([777], $repository->productIds);
        self::assertSame('1234567890123456.78', $rows[0]['current_price']);
        self::assertFalse($rows[0]['is_stale']);
        self::assertTrue($rows[0]['is_url_safe']);
        self::assertTrue($rows[1]['is_stale']);
        self::assertSame('error', $rows[1]['status']);
        self::assertNull($rows[2]['current_price']);
        self::assertNull($rows[2]['currency']);
    }

    public function testZeroMaximumAgeDisablesAgeMarking(): void
    {
        $repository = new RecordingRepository([$this->row(1, '10.00', 'RUB', 'success', '2000-01-01 00:00:00')]);
        $rows = (new StaffProductPriceReadService($repository, static fn(): int => 2_000_000_000))->read(5, 0);
        self::assertFalse($rows[0]['is_stale']);
    }

    private function row(int $id, ?string $price, ?string $currency, string $status, ?string $lastSuccess): array
    {
        return [
            'ID' => $id, 'COMPETITOR_ID' => 2, 'COMPETITOR_NAME' => '<Shop>',
            'URL' => 'https://example.test/p?a=1&b=2', 'CURRENT_PRICE' => $price,
            'CURRENCY' => $currency, 'STATUS' => $status, 'LAST_CHECK_AT' => null,
            'LAST_SUCCESS_AT' => $lastSuccess === null ? null : new DateTimeImmutable($lastSuccess, new \DateTimeZone('UTC')),
        ];
    }
}

final class RecordingRepository implements StaffProductPriceRepositoryInterface
{
    /** @var list<int> */ public array $productIds = [];
    public function __construct(private readonly array $rows) {}
    public function findActiveByExactProductId(int $productId): array
    {
        $this->productIds[] = $productId;
        return $this->rows;
    }
}
