<?php

declare(strict_types=1);

namespace KK\PriceWatch\Service;

final class PriceHistoryAnalyticsResult
{
    /**
     * @param list<array{id:int, price:string, currency:string, collected_at:string}> $timeline
     * @param list<array{id:int, price:string, currency:string, collected_at:string}> $chartPoints
     */
    public function __construct(
        public readonly int $pointCount,
        public readonly ?array $first,
        public readonly ?array $latest,
        public readonly ?array $previous,
        public readonly ?string $minimumPrice,
        public readonly ?string $maximumPrice,
        public readonly ?string $currency,
        public readonly string $direction,
        public readonly bool $mixedCurrencies,
        public readonly array $timeline,
        public readonly array $chartPoints,
        public readonly bool $chartTruncated,
    ) {
    }

    public function isEmpty(): bool
    {
        return $this->pointCount === 0;
    }
}
