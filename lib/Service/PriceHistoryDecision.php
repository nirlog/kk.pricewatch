<?php

declare(strict_types=1);

namespace KK\PriceWatch\Service;

final class PriceHistoryDecision
{
    public static function shouldAppend(?string $previousPrice, ?string $previousCurrency, string $price, string $currency): bool
    {
        return $previousPrice === null || $previousCurrency === null
            || $previousPrice !== $price || $previousCurrency !== $currency;
    }
}
