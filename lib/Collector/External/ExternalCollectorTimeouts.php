<?php

declare(strict_types=1);

namespace KK\PriceWatch\Collector\External;

use KK\PriceWatch\Collector\Exception\InvalidConfigurationException;

final readonly class ExternalCollectorTimeouts
{
    private function __construct(public int $connect, public int $request) {}

    public static function fromStrings(string $connect, string $request): self
    {
        if (preg_match('/^(?:0|[1-9][0-9]*)$/D', $connect) !== 1
            || preg_match('/^(?:0|[1-9][0-9]*)$/D', $request) !== 1) {
            throw new InvalidConfigurationException('External collector timeouts must be integer seconds.');
        }

        $connectValue = (int) $connect;
        $requestValue = (int) $request;
        if ($connectValue < 1 || $connectValue > 30 || $requestValue < 5 || $requestValue > 300
            || $requestValue < $connectValue) {
            throw new InvalidConfigurationException('External collector timeout values are invalid.');
        }

        return new self($connectValue, $requestValue);
    }
}
