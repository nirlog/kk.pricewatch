<?php

declare(strict_types=1);

namespace KK\PriceWatch\Collector\Http;

final readonly class HttpFetchResult
{
    private function __construct(
        public bool $success,
        public int $status,
        public ?string $contentType,
        public string $body,
        public string $effectiveUrl,
        public ?string $failureType,
    ) {
    }

    public static function success(int $status, ?string $contentType, string $body, string $effectiveUrl): self
    {
        return new self(true, $status, $contentType, $body, $effectiveUrl, null);
    }

    public static function failure(string $failureType = 'network'): self
    {
        return new self(false, 0, null, '', '', $failureType);
    }
}
