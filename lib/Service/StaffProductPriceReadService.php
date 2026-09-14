<?php

declare(strict_types=1);

namespace KK\PriceWatch\Service;

use DateTimeInterface;

final class StaffProductPriceReadService
{
    /** @var callable(): int */
    private $now;

    public function __construct(
        private readonly StaffProductPriceRepositoryInterface $repository = new OrmStaffProductPriceRepository(),
        ?callable $now = null,
    ) {
        $this->now = $now ?? static fn(): int => time();
    }

    /** @return list<array<string, mixed>> */
    public function read(int $productId, int $maxAgeSeconds = 86400): array
    {
        if ($productId <= 0) {
            return [];
        }

        $maxAgeSeconds = max(0, $maxAgeSeconds);
        $now = ($this->now)();

        return array_map(static function (array $row) use ($maxAgeSeconds, $now): array {
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
                'current_price' => $hasPrice ? (string) $row['CURRENT_PRICE'] : null,
                'currency' => $hasPrice ? (string) $row['CURRENCY'] : null,
                'status' => (string) $row['STATUS'],
                'last_check_at' => $row['LAST_CHECK_AT'] ?? null,
                'last_success_at' => $lastSuccess,
                'is_stale' => $hasPrice && $maxAgeSeconds > 0
                    && $successTimestamp !== null
                    && $successTimestamp < ($now - $maxAgeSeconds),
            ];
        }, $this->repository->findActiveByExactProductId($productId));
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
