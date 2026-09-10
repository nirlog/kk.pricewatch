<?php

declare(strict_types=1);

namespace KK\PriceWatch\Model;

use InvalidArgumentException;

final class ProductUrl
{
    public static function hash(string $url): string
    {
        if (trim($url) === '') {
            throw new InvalidArgumentException('Product URL must not be blank.');
        }

        return hash('sha256', $url);
    }
}
