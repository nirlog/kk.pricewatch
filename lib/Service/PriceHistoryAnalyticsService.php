<?php

declare(strict_types=1);

namespace KK\PriceWatch\Service;

final class PriceHistoryAnalyticsService
{
    public const CHART_POINT_LIMIT = 500;
    public const DIRECTION_UP = 'up';
    public const DIRECTION_DOWN = 'down';
    public const DIRECTION_UNAVAILABLE = 'unavailable';

    public function __construct(private readonly PriceHistoryAnalyticsRepositoryInterface $repository)
    {
    }

    public function analyze(int $linkId, int $productId, int $competitorId, string $urlHash): PriceHistoryAnalyticsResult
    {
        $rows = $this->repository->findCurrentIdentityPoints($linkId, $productId, $competitorId, $urlHash);
        $effective = array_map(self::normalizePoint(...), $rows);
        usort($effective, static fn(array $a, array $b): int => $a['id'] <=> $b['id']);
        if ($effective === []) {
            return new PriceHistoryAnalyticsResult(0, null, null, null, null, null, null,
                self::DIRECTION_UNAVAILABLE, false, [], [], false);
        }

        $currencies = array_values(array_unique(array_column($effective, 'currency')));
        $mixed = count($currencies) !== 1;
        $count = count($effective);
        $first = $effective[0];
        $latest = $effective[$count - 1];
        $previous = $count > 1 ? $effective[$count - 2] : null;
        $minimum = $maximum = null;
        $direction = self::DIRECTION_UNAVAILABLE;
        if (!$mixed) {
            $minimum = $maximum = $first['price'];
            foreach ($effective as $point) {
                if (self::comparePrices($point['price'], $minimum) < 0) $minimum = $point['price'];
                if (self::comparePrices($point['price'], $maximum) > 0) $maximum = $point['price'];
            }
            if ($previous !== null) {
                $comparison = self::comparePrices($latest['price'], $previous['price']);
                $direction = $comparison > 0 ? self::DIRECTION_UP
                    : ($comparison < 0 ? self::DIRECTION_DOWN : self::DIRECTION_UNAVAILABLE);
            }
        }

        $timeline = $effective;
        usort($timeline, static fn(array $a, array $b): int => [$a['collected_at'], $a['id']] <=> [$b['collected_at'], $b['id']]);
        $chart = $mixed ? [] : array_slice($timeline, -self::CHART_POINT_LIMIT);

        return new PriceHistoryAnalyticsResult($count, $first, $latest, $previous, $minimum, $maximum,
            $mixed ? null : $currencies[0], $direction, $mixed, $timeline, $chart,
            !$mixed && $count > self::CHART_POINT_LIMIT);
    }

    /** Decimal comparison using text only; values come from DECIMAL(18,2). */
    public static function comparePrices(string $left, string $right): int
    {
        $left = PriceHistoryDecision::canonicalPrice($left);
        $right = PriceHistoryDecision::canonicalPrice($right);
        [$li, $lf] = explode('.', $left);
        [$ri, $rf] = explode('.', $right);
        return strlen($li) <=> strlen($ri) ?: strcmp($li, $ri) ?: strcmp($lf, $rf);
    }

    private static function normalizePoint(array $row): array
    {
        $date = $row['COLLECTED_AT'];
        $dateText = is_object($date) && method_exists($date, 'format') ? $date->format('Y-m-d H:i:s') : (string) $date;
        return ['id' => (int) $row['ID'], 'price' => PriceHistoryDecision::canonicalPrice((string) $row['PRICE']),
            'currency' => (string) $row['CURRENCY'], 'collected_at' => $dateText];
    }
}
