<?php

declare(strict_types=1);

namespace KK\PriceWatch\Model;

use InvalidArgumentException;

final class ProductUrl
{
    public static function isAcceptedHttpUrl(string $url): bool
    {
        if (trim($url) === '' || filter_var($url, FILTER_VALIDATE_URL) === false) {
            return false;
        }

        $parts = parse_url($url);
        return is_array($parts)
            && isset($parts['scheme'], $parts['host'])
            && in_array(strtolower((string) $parts['scheme']), ['http', 'https'], true)
            && (string) $parts['host'] !== '';
    }

    public static function hash(string $url): string
    {
        if (trim($url) === '') {
            throw new InvalidArgumentException('Product URL must not be blank.');
        }

        return hash('sha256', $url);
    }
}
