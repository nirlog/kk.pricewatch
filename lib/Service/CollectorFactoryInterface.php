<?php

declare(strict_types=1);

namespace KK\PriceWatch\Service;

use KK\PriceWatch\Collector\CollectorInterface;

interface CollectorFactoryInterface
{
    /** @param array<string, mixed> $competitor */
    public function create(array $competitor): CollectorInterface;
}
