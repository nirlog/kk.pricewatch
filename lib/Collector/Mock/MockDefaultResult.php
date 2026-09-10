<?php

declare(strict_types=1);

namespace KK\PriceWatch\Collector\Mock;

use InvalidArgumentException;
use KK\PriceWatch\Collector\Exception\InvalidConfigurationException;
use KK\PriceWatch\Collector\Money;

final readonly class MockDefaultResult
{
    public function __construct(public string $price, public string $currency = 'RUB')
    {
        try {
            Money::assertPrice($price);
            Money::assertCurrency($currency);
        } catch (InvalidArgumentException $exception) {
            throw new InvalidConfigurationException($exception->getMessage(), 0, $exception);
        }
    }
}
