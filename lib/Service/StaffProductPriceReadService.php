<?php

declare(strict_types=1);

namespace KK\PriceWatch\Service;

use DateTimeInterface;
use KK\PriceWatch\Model\ProductUrl;

final class StaffProductPriceReadService
{
    public const DEFAULT_MAX_ROWS = 50;
    public const MAX_ROWS = 200;

    /** @var callable(): int */
    private $now;

    public function __construct(
        private readonly StaffProductPriceRepositoryInterface $repository = new OrmStaffProductPriceRepository(),
        ?callable $now = null,
    ) {
        $this->now = $now ?? static fn(): int => time();
    }

    public function read(
        int $productId,
        int $maxAgeSeconds = 86400,
        int $maxRows = self::DEFAULT_MAX_ROWS,
    ): StaffProductPriceReadResult
    {
        if ($productId <= 0) {
            return new StaffProductPriceReadResult([], false);
        }

        $maxAgeSeconds = max(0, $maxAgeSeconds);
        $maxRows = self::normalizeMaxRows($maxRows);
        $now = ($this->now)();
        $rows = $this->repository->findActiveByExactProductId($productId, $maxRows + 1);
        $hasMore = count($rows) > $maxRows;
        if ($hasMore) {
            $rows = array_slice($rows, 0, $maxRows);
        }

        $rows = array_map(static function (array $row) use ($maxAgeSeconds, $now): array {
            $lastSuccess = $row['LAST_SUCCESS_AT'] ?? null;
            $successTimestamp = self::timestamp($lastSuccess);
            $hasPrice = $row['CURRENT_PRICE'] !== null
                && $row['CURRENCY'] !== null
                && $lastSuccess !== null;

            return [
                'link_id' => (int) $row['ID'],
                'competitor_id' => (int) $row['COMPETITOR_ID'],
                'competitor_name' => (string) $row['COMPETITOR_NAME'],
                'exact_url' => (string) $row['URL'],
                'is_url_safe' => ProductUrl::isAcceptedHttpUrl((string) $row['URL']),
                'current_price' => $hasPrice ? (string) $row['CURRENT_PRICE'] : null,
                'currency' => $hasPrice ? (string) $row['CURRENCY'] : null,
                'status' => (string) $row['STATUS'],
                'last_check_at' => $row['LAST_CHECK_AT'] ?? null,
                'last_success_at' => $lastSuccess,
                'is_stale' => $hasPrice && $maxAgeSeconds > 0
                    && $successTimestamp !== null
                    && $successTimestamp < ($now - $maxAgeSeconds),
            ];
        }, $rows);

        return new StaffProductPriceReadResult($rows, $hasMore);
    }

    public static function normalizeMaxRows(mixed $value): int
    {
        $validated = filter_var($value, FILTER_VALIDATE_INT);
        if ($validated === false || $validated < 1) {
            return self::DEFAULT_MAX_ROWS;
        }

        return min($validated, self::MAX_ROWS);
    }

    private static function timestamp(mixed $value): ?int
    {
        if ($value instanceof DateTimeInterface) {
            return $value->getTimestamp();
        }
        if (is_object($value) && method_exists($value, 'getTimestamp')) {
            return (int) $value->getTimestamp();
        }
        return null;
    }
}
