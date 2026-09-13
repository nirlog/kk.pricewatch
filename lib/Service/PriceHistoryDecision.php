<?php

declare(strict_types=1);

namespace KK\PriceWatch\Service;

final class PriceHistoryDecision
{
    public static function shouldAppend(?string $previousPrice, ?string $previousCurrency, string $price, string $currency): bool
    {
        return $previousPrice === null || $previousCurrency === null
            || self::canonicalPrice($previousPrice) !== self::canonicalPrice($price)
            || $previousCurrency !== $currency;
    }

    /** Canonicalize decimal text without ever converting it to float. */
    public static function canonicalPrice(string $price): string
    {
        if (preg_match('/^(\d+)(?:\.(\d{1,2}))?$/D', $price, $parts) !== 1) {
            throw new \InvalidArgumentException('Price must be a non-negative decimal with at most two fractional digits.');
        }

        $integer = ltrim($parts[1], '0');
        $integer = $integer === '' ? '0' : $integer;
        $fraction = str_pad($parts[2] ?? '', 2, '0');
        return $integer . '.' . $fraction;
    }
}
