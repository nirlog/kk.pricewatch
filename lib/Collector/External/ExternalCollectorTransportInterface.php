<?php
declare(strict_types=1);
namespace KK\PriceWatch\Collector\External;
interface ExternalCollectorTransportInterface
{
    /** @param array<string, string> $headers */
    public function post(string $endpoint, string $body, array $headers, int $connectTimeout, int $requestTimeout): ExternalCollectorHttpResult;
}
