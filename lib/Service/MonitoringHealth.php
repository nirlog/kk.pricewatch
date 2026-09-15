<?php

declare(strict_types=1);

namespace KK\PriceWatch\Service;

use DateTimeImmutable;
use DateTimeInterface;
use KK\PriceWatch\Model\CollectionStatus;

final class MonitoringHealth
{
    public const STALE_AFTER_SECONDS = 86400;
    public const ALL = '';
    public const PROBLEMS = 'problems';
    public const HEALTHY = 'healthy';
    public const STALE = 'stale';
    public const NO_PRICE = 'no_price';
    public const ERROR = 'error';

    public static function normalize(string $value): string
    {
        return in_array($value, [self::PROBLEMS, self::HEALTHY, self::STALE, self::NO_PRICE, self::ERROR], true)
            ? $value : self::ALL;
    }

    public static function cutoff(DateTimeInterface $now): DateTimeImmutable
    {
        return DateTimeImmutable::createFromInterface($now)->modify('-' . self::STALE_AFTER_SECONDS . ' seconds');
    }

    /** @return array<string|int, mixed> */
    public static function ormFilter(string $health, DateTimeInterface $cutoff): array
    {
        $complete = self::completeValueFilter();
        $noPrice = self::noPriceFilter();
        $stale = ['LOGIC' => 'AND', $complete, '<LAST_SUCCESS_AT' => $cutoff];
        $error = ['=STATUS' => CollectionStatus::ERROR];

        return match (self::normalize($health)) {
            self::HEALTHY => ['LOGIC' => 'AND', '=STATUS' => CollectionStatus::SUCCESS, $complete, '>=LAST_SUCCESS_AT' => $cutoff],
            self::STALE => $stale,
            self::NO_PRICE => $noPrice,
            self::ERROR => $error,
            self::PROBLEMS => ['LOGIC' => 'OR', $error, $stale, $noPrice],
            default => [],
        };
    }

    /** @param array<string, mixed> $row */
    public static function describe(array $row, DateTimeInterface $cutoff): array
    {
        $hasValue = ($row['CURRENT_PRICE'] ?? null) !== null
            && ($row['CURRENCY'] ?? null) !== null
            && ($row['LAST_SUCCESS_AT'] ?? null) !== null;
        $lastSuccess = $row['LAST_SUCCESS_AT'] ?? null;
        $stale = $hasValue && $lastSuccess instanceof DateTimeInterface && $lastSuccess < $cutoff;

        return [
            'has_successful_value' => $hasValue,
            'is_stale' => $stale,
            'is_error' => ($row['STATUS'] ?? null) === CollectionStatus::ERROR,
            'is_healthy' => ($row['STATUS'] ?? null) === CollectionStatus::SUCCESS && $hasValue && !$stale,
        ];
    }

    private static function completeValueFilter(): array
    {
        return ['LOGIC' => 'AND', '!=CURRENT_PRICE' => null, '!=CURRENCY' => null, '!=LAST_SUCCESS_AT' => null];
    }

    private static function noPriceFilter(): array
    {
        return ['LOGIC' => 'OR', '=CURRENT_PRICE' => null, '=CURRENCY' => null, '=LAST_SUCCESS_AT' => null];
    }
}
