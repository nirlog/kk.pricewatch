<?php
declare(strict_types=1);
namespace KK\PriceWatch\Collector\External;
final readonly class ExternalCollectorHttpResult
{
    private function __construct(public bool $success, public int $status, public ?string $contentType, public string $body, public ?string $failure) {}
    public static function response(int $status, ?string $contentType, string $body): self { return new self(true, $status, $contentType, $body, null); }
    public static function failure(string $kind = 'network'): self { return new self(false, 0, null, '', $kind); }
}
