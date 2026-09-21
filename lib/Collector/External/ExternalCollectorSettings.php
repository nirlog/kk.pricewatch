<?php

declare(strict_types=1);

namespace KK\PriceWatch\Collector\External;

use KK\PriceWatch\Collector\Exception\InvalidConfigurationException;

final readonly class ExternalCollectorSettings
{
    public const DEFAULT_CONNECT_TIMEOUT = 5;
    public const DEFAULT_REQUEST_TIMEOUT = 60;

    public function __construct(
        public bool $enabled,
        public string $baseUrl,
        private string $token,
        public int $connectTimeout = self::DEFAULT_CONNECT_TIMEOUT,
        public int $requestTimeout = self::DEFAULT_REQUEST_TIMEOUT,
    ) {
        if ($connectTimeout < 1 || $connectTimeout > 30) {
            throw new InvalidConfigurationException('External collector connect timeout must be between 1 and 30 seconds.');
        }
        if ($requestTimeout < 5 || $requestTimeout > 300 || $requestTimeout < $connectTimeout) {
            throw new InvalidConfigurationException('External collector request timeout is invalid.');
        }
        if ($enabled && ($token === '' || $baseUrl === '')) {
            throw new InvalidConfigurationException('Enabled external collector requires a service URL and API token.');
        }
        if ($baseUrl !== '') {
            ExternalCollectorEndpoint::validateBaseUrl($baseUrl);
        }
    }

    public function token(): string
    {
        return $this->token;
    }

    /** @return array{enabled: bool, baseUrl: string, tokenConfigured: bool, connectTimeout: int, requestTimeout: int} */
    public function __debugInfo(): array
    {
        return [
            'enabled' => $this->enabled,
            'baseUrl' => $this->baseUrl,
            'tokenConfigured' => $this->token !== '',
            'connectTimeout' => $this->connectTimeout,
            'requestTimeout' => $this->requestTimeout,
        ];
    }

    /** @return array{enabled: bool, base_url: string, connect_timeout: int, request_timeout: int, token_configured: bool} */
    public function renderableState(): array
    {
        return [
            'enabled' => $this->enabled,
            'base_url' => $this->baseUrl,
            'connect_timeout' => $this->connectTimeout,
            'request_timeout' => $this->requestTimeout,
            'token_configured' => $this->token !== '',
        ];
    }
}
