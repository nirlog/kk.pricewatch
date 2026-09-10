<?php

declare(strict_types=1);

namespace KK\PriceWatch\Collector\Exception;

use InvalidArgumentException;

final class InvalidRequestException extends InvalidArgumentException
{
    public const ERROR_CODE = 'INVALID_REQUEST';

    public function getErrorCode(): string
    {
        return self::ERROR_CODE;
    }
}
