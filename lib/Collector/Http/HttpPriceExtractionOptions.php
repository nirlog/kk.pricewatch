<?php

declare(strict_types=1);

namespace KK\PriceWatch\Collector\Http;

use KK\PriceWatch\Collector\Exception\InvalidConfigurationException;
use KK\PriceWatch\Collector\Money;
use Throwable;

final readonly class HttpPriceExtractionOptions
{
    public function __construct(public string $xpath, public string $currency)
    {
        if (trim($xpath) === '' || $currency === '') {
            throw new InvalidConfigurationException('HTTP price selector and currency are required.');
        }
        try {
            Money::assertCurrency($currency);
        } catch (Throwable $exception) {
            throw new InvalidConfigurationException('HTTP currency must be an uppercase three-letter code.', 0, $exception);
        }
    }

    /** @param array<string, mixed> $options */
    public static function fromArray(array $options): self
    {
        $allowed = ['price_selector', 'currency'];
        if (array_diff(array_keys($options), $allowed) !== []) {
            throw new InvalidConfigurationException('Unsupported HTTP collector option.');
        }
        $selector = $options['price_selector'] ?? null;
        if (!is_array($selector) || ($selector['type'] ?? null) !== 'xpath'
            || !is_string($selector['value'] ?? null) || array_diff(array_keys($selector), ['type', 'value']) !== []) {
            throw new InvalidConfigurationException('HTTP price_selector must contain only an XPath type and value.');
        }
        if (!is_string($options['currency'] ?? null)) {
            throw new InvalidConfigurationException('HTTP currency is required.');
        }
        return new self($selector['value'], $options['currency']);
    }
}
