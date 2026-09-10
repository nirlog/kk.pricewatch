<?php

declare(strict_types=1);

namespace KK\PriceWatch\Collector;

interface CollectorInterface
{
    public function collect(CollectorRequest $request): CollectorResponse;
}
