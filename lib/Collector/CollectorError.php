<?php

declare(strict_types=1);

namespace KK\PriceWatch\Collector;

use InvalidArgumentException;

final readonly class CollectorError
{
    public function __construct(public string $code, public string $message)
    {
        if (trim($code) === '' || trim($message) === '') {
            throw new InvalidArgumentException('Error code and message must not be empty.');
        }
    }

    /** @return array{code: string, message: string} */
    public function toArray(): array
    {
        return ['code' => $this->code, 'message' => $this->message];
    }
}
