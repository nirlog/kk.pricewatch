<?php

declare(strict_types=1);

namespace KK\PriceWatch\Tests\Service;

use DateTimeImmutable;
use DateTimeInterface;
use KK\PriceWatch\Service\MonitoringHealth;
use KK\PriceWatch\Service\OrmMonitoringDashboardRepository;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

final class OrmMonitoringDashboardRepositoryTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        if (!class_exists('Bitrix\Main\Type\DateTime')) {
            class_alias(BitrixDateTimeStub::class, 'Bitrix\Main\Type\DateTime');
        }
    }

    public function testBitrixDatesAreNormalizedForMonitoringHealth(): void
    {
        $cutoff = new DateTimeImmutable('2026-09-16 12:00:00 UTC');
        $bitrixDateTime = 'Bitrix\\Main\\Type\\DateTime';
        $lastCheck = new $bitrixDateTime(strtotime('2026-09-15 11:00:00 UTC'));
        $lastSuccess = new $bitrixDateTime(strtotime('2026-09-15 12:00:00 UTC'));
        self::assertInstanceOf($bitrixDateTime, $lastCheck);
        self::assertInstanceOf($bitrixDateTime, $lastSuccess);
        $row = $this->normalize([
            'STATUS' => 'success',
            'CURRENT_PRICE' => '129990.00',
            'CURRENCY' => 'RUB',
            'LAST_CHECK_AT' => $lastCheck,
            'LAST_SUCCESS_AT' => $lastSuccess,
        ]);

        self::assertInstanceOf(DateTimeImmutable::class, $row['LAST_CHECK_AT']);
        self::assertInstanceOf(DateTimeInterface::class, $row['LAST_SUCCESS_AT']);
        self::assertTrue(MonitoringHealth::describe($row, $cutoff)['is_stale']);
    }

    public function testHealthyRowRemainsHealthyAndNullDatesRemainNull(): void
    {
        $cutoff = new DateTimeImmutable('2026-09-16 12:00:00 UTC');
        $bitrixDateTime = 'Bitrix\\Main\\Type\\DateTime';
        $healthy = $this->normalize([
            'STATUS' => 'success',
            'CURRENT_PRICE' => '129990.00',
            'CURRENCY' => 'RUB',
            'LAST_CHECK_AT' => null,
            'LAST_SUCCESS_AT' => new $bitrixDateTime(strtotime('2026-09-16 13:00:00 UTC')),
        ]);

        self::assertFalse(MonitoringHealth::describe($healthy, $cutoff)['is_stale']);
        self::assertNull($healthy['LAST_CHECK_AT']);

        $empty = $this->normalize(['LAST_CHECK_AT' => null, 'LAST_SUCCESS_AT' => null]);
        self::assertNull($empty['LAST_CHECK_AT']);
        self::assertNull($empty['LAST_SUCCESS_AT']);
    }

    private function normalize(array $row): array
    {
        $method = new ReflectionMethod(OrmMonitoringDashboardRepository::class, 'normalizeRow');

        return $method->invoke(null, $row);
    }
}

final class BitrixDateTimeStub
{
    public function __construct(private readonly int $timestamp)
    {
    }

    public function getTimestamp(): int
    {
        return $this->timestamp;
    }
}
