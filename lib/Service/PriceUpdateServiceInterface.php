<?php

declare(strict_types=1);

namespace KK\PriceWatch\Service;

interface PriceUpdateServiceInterface
{
    /** @param list<int> $linkIds */
    public function updateLinks(array $linkIds): PriceUpdateBatchResult;
}
