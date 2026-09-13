<?php

declare(strict_types=1);

namespace KK\PriceWatch\Collector\Http;

use Bitrix\Main\Web\HttpClient;
use Throwable;

final class BitrixHttpTransport implements HttpTransportInterface
{
    public const SOCKET_TIMEOUT = 10;
    public const STREAM_TIMEOUT = 15;
    public const BODY_LIMIT = 2 * 1024 * 1024;

    public function fetch(string $url): HttpFetchResult
    {
        $parts = parse_url($url);
        if (!is_array($parts) || !in_array(strtolower((string) ($parts['scheme'] ?? '')), ['http', 'https'], true)
            || !isset($parts['host']) || $parts['host'] === '' || isset($parts['user']) || isset($parts['pass'])) {
            return HttpFetchResult::failure('invalid_url');
        }
        try {
            $client = new HttpClient([
                'socketTimeout' => self::SOCKET_TIMEOUT,
                'streamTimeout' => self::STREAM_TIMEOUT,
                'redirect' => false,
                'redirectMax' => 0,
            ]);
            $client->setPrivateIp(false);
            $client->setBodyLength(self::BODY_LIMIT);
            $client->setHeader('User-Agent', 'kk.pricewatch/0.9.0');
            $client->setHeader('Accept', 'text/html, application/xhtml+xml');

            $body = $client->get($url);
            if (!is_string($body)) {
                return HttpFetchResult::failure('network');
            }

            $headers = $client->getHeaders();
            $contentType = $headers->get('Content-Type');
            return HttpFetchResult::success(
                (int) $client->getStatus(),
                is_string($contentType) ? $contentType : null,
                $body,
                $url,
            );
        } catch (Throwable) {
            return HttpFetchResult::failure('network');
        }
    }
}
