<?php

declare(strict_types=1);

namespace KK\PriceWatch\Service;

use KK\PriceWatch\Collector\Money;

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
        Money::assertPrice($price);
        [$integerPart, $fractionPart] = array_pad(explode('.', $price, 2), 2, '');

        $integer = ltrim($integerPart, '0');
        $integer = $integer === '' ? '0' : $integer;
        $fraction = str_pad(substr($fractionPart, 0, 2), 2, '0');

        // MySQL DECIMAL rounds positive values half up when reducing scale.
        // Increment the combined decimal digits as text to avoid float/integer limits.
        if (isset($fractionPart[2]) && $fractionPart[2] >= '5') {
            $rounded = self::incrementDigits($integer . $fraction);
            $integer = substr($rounded, 0, -2);
            $fraction = substr($rounded, -2);
        }

        return $integer . '.' . $fraction;
    }

    private static function incrementDigits(string $digits): string
    {
        $nextDigit = ['0' => '1', '1' => '2', '2' => '3', '3' => '4', '4' => '5',
            '5' => '6', '6' => '7', '7' => '8', '8' => '9'];
        for ($offset = strlen($digits) - 1; $offset >= 0; $offset--) {
            if ($digits[$offset] !== '9') {
                $digits[$offset] = $nextDigit[$digits[$offset]];
                return $digits;
            }
            $digits[$offset] = '0';
        }

        return '1' . $digits;
    }
}
