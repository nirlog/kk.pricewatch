<?php

declare(strict_types=1);

namespace KK\PriceWatch\Model;

use InvalidArgumentException;

final class CollectorType
{
    public const MOCK = 'mock';
    public const EXTERNAL = 'external';

    private const KNOWN_TYPES = [self::MOCK, self::EXTERNAL];

    public static function isValid(string $type): bool
    {
        return in_array($type, self::KNOWN_TYPES, true);
    }

    public static function validate(string $type): void
    {
        if (!self::isValid($type)) {
            throw new InvalidArgumentException(sprintf('Unknown collector type "%s".', $type));
        }
    }
}
