<?php

declare(strict_types=1);

namespace KK\PriceWatch\Service;

use KK\PriceWatch\Collector\CollectorInterface;
use KK\PriceWatch\Collector\Exception\InvalidConfigurationException;
use KK\PriceWatch\Collector\Http\BitrixHttpTransport;
use KK\PriceWatch\Collector\Http\DomXPathPriceExtractor;
use KK\PriceWatch\Collector\Http\HtmlPriceExtractorInterface;
use KK\PriceWatch\Collector\Http\HttpHtmlCollector;
use KK\PriceWatch\Collector\Http\HttpPriceExtractionOptions;
use KK\PriceWatch\Collector\Http\HttpTransportInterface;
use KK\PriceWatch\Collector\Mock\MockCollector;
use KK\PriceWatch\Collector\Mock\MockDefaultResult;
use KK\PriceWatch\Model\CollectorOptions;
use KK\PriceWatch\Model\CollectorType;

final class DefaultCollectorFactory implements CollectorFactoryInterface
{
    public function __construct(
        private readonly ?HttpTransportInterface $httpTransport = null,
        private readonly ?HtmlPriceExtractorInterface $htmlExtractor = null,
    ) {
    }

    public function create(array $competitor): CollectorInterface
    {
        $type = $competitor['COLLECTOR_TYPE'] ?? null;
        if ($type === CollectorType::HTTP) {
            return $this->createHttp($competitor);
        }
        if ($type !== CollectorType::MOCK) {
            throw new InvalidConfigurationException('The configured collector type is not available.');
        }

        $options = CollectorOptions::decode(is_string($competitor['COLLECTOR_OPTIONS'] ?? null)
            ? $competitor['COLLECTOR_OPTIONS'] : null);
        $scenarios = $options['scenarios'] ?? [];
        if (!is_array($scenarios) || !array_is_list($scenarios)) {
            throw new InvalidConfigurationException('Mock scenarios must be a list.');
        }

        $default = null;
        if (array_key_exists('default', $options)) {
            if (!is_array($options['default'])
                || !is_string($options['default']['price'] ?? null)
                || !is_string($options['default']['currency'] ?? null)) {
                throw new InvalidConfigurationException('Mock default requires string price and currency.');
            }
            $default = new MockDefaultResult($options['default']['price'], $options['default']['currency']);
        }

        return new MockCollector($scenarios, $default);
    }

    /** @param array<string, mixed> $competitor */
    private function createHttp(array $competitor): CollectorInterface
    {
        $domain = $competitor['DOMAIN'] ?? null;
        if (!is_string($domain) || trim($domain) !== $domain || $domain === ''
            || filter_var($domain, FILTER_VALIDATE_IP) !== false
            || preg_match('/^(?=.{1,253}$)(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)*[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?$/Di', $domain) !== 1) {
            throw new InvalidConfigurationException('HTTP collector requires a valid non-IP competitor domain.');
        }
        try {
            $options = CollectorOptions::decode(is_string($competitor['COLLECTOR_OPTIONS'] ?? null)
                ? $competitor['COLLECTOR_OPTIONS'] : null);
        } catch (\InvalidArgumentException $exception) {
            throw new InvalidConfigurationException('HTTP collector options are invalid.', 0, $exception);
        }
        return new HttpHtmlCollector(
            strtolower($domain),
            HttpPriceExtractionOptions::fromArray($options),
            $this->httpTransport ?? new BitrixHttpTransport(),
            $this->htmlExtractor ?? new DomXPathPriceExtractor(),
        );
    }
}
