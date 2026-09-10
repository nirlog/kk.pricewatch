<?php

declare(strict_types=1);

namespace KK\PriceWatch\Model;

use InvalidArgumentException;

final class CollectionStatus
{
    public const NEW = 'new';
    public const SUCCESS = 'success';
    public const ERROR = 'error';

    public static function isValid(string $status): bool
    {
        return in_array($status, [self::NEW, self::SUCCESS, self::ERROR], true);
    }

    public static function assertValid(string $status): void
    {
        if (!self::isValid($status)) {
            throw new InvalidArgumentException('Unknown collection status.');
        }
    }
}
