<?php

declare(strict_types=1);

namespace KK\PriceWatch\Collector\Http;

interface HttpTransportInterface
{
    public function fetch(string $url): HttpFetchResult;
}
