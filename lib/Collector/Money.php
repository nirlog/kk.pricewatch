<?php

declare(strict_types=1);

namespace KK\PriceWatch\Collector;

use InvalidArgumentException;

final class Money
{
    public static function assertPrice(string $price): void
    {
        if (preg_match('/^[0-9]+(?:\.[0-9]+)?$/D', $price) !== 1) {
            throw new InvalidArgumentException('Price must be a non-negative decimal string.');
        }
    }

    public static function assertCurrency(string $currency): void
    {
        if (preg_match('/^[A-Z]{3}$/D', $currency) !== 1) {
            throw new InvalidArgumentException('Currency must be an uppercase three-letter code.');
        }
    }
}
