<?php

declare(strict_types=1);

namespace KK\PriceWatch\Service;

final class PriceUpdateServiceFactory
{
    public static function createDefault(): PriceUpdateService
    {
        return new PriceUpdateService(new DefaultCollectorFactory(), new RandomRequestIdGenerator());
    }
}
