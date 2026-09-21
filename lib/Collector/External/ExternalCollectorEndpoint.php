<?php

declare(strict_types=1);

namespace KK\PriceWatch\Collector\External;

use KK\PriceWatch\Collector\Exception\InvalidConfigurationException;

final class ExternalCollectorEndpoint
{
    public static function validateBaseUrl(string $url): string
    {
        if ($url !== trim($url) || preg_match('/[\x00-\x1F\x7F]/', $url)) {
            throw new InvalidConfigurationException('External collector base URL is invalid.');
        }
        $parts = parse_url($url);
        if (!is_array($parts) || !isset($parts['scheme'], $parts['host']) || $parts['host'] === ''
            || filter_var($url, FILTER_VALIDATE_URL) === false || isset($parts['user']) || isset($parts['pass'])
            || isset($parts['query']) || isset($parts['fragment'])) {
            throw new InvalidConfigurationException('External collector base URL is invalid.');
        }
        $scheme = strtolower($parts['scheme']);
        $host = strtolower($parts['host']);
        $loopback = in_array($host, ['localhost', '127.0.0.1', '::1', '[::1]'], true);
        if ($scheme !== 'https' && !($scheme === 'http' && $loopback)) {
            throw new InvalidConfigurationException('External collector requires HTTPS except for loopback development endpoints.');
        }
        return rtrim($url, '/');
    }

    public static function validateHandler(string $handler): string
    {
        $decoded = rawurldecode($handler);
        if ($handler === '' || strlen($handler) > 512 || $handler[0] !== '/' || str_starts_with($handler, '//')
            || str_contains($handler, '\\') || preg_match('/[\x00-\x1F\x7F?#]/', $handler)
            || preg_match('/%(?![0-9A-Fa-f]{2})/', $handler) || str_contains($decoded, '\\')
            || preg_match('/[\x00-\x1F\x7F?#]/', $decoded)
            || preg_match('~(^|/)\.\.(/|$)~', $decoded) || str_starts_with($decoded, '//')) {
            throw new InvalidConfigurationException('External collector handler must be a safe absolute path.');
        }
        $parts = parse_url($handler);
        if (!is_array($parts) || isset($parts['scheme'], $parts['host'], $parts['user'], $parts['pass'], $parts['query'], $parts['fragment'])) {
            throw new InvalidConfigurationException('External collector handler must be a path only.');
        }
        return $handler;
    }

    public static function resolve(string $baseUrl, string $handler): string
    {
        return self::validateBaseUrl($baseUrl) . self::validateHandler($handler);
    }
}
