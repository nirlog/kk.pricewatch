<?php

declare(strict_types=1);

namespace KK\PriceWatch\Collector\Mock;

use KK\PriceWatch\Collector\CollectorItemResult;
use KK\PriceWatch\Collector\Exception\InvalidConfigurationException;

final readonly class MockScenario
{
    private function __construct(
        public string $matchType,
        public string $matchValue,
        private string $resultType,
        private ?string $price,
        private ?string $currency,
        private ?string $errorCode,
        private ?string $errorMessage,
    ) {
    }

    /** @param array<string, mixed> $scenario */
    public static function fromArray(array $scenario): self
    {
        $match = $scenario['match'] ?? null;
        $result = $scenario['result'] ?? null;
        if (!is_array($match) || !is_array($result) || ($match['field'] ?? null) !== 'url') {
            throw new InvalidConfigurationException('A mock scenario must contain a URL match and result.');
        }
        $matchType = $match['type'] ?? null;
        $matchValue = $match['value'] ?? null;
        if (!in_array($matchType, ['exact', 'contains'], true) || !is_string($matchValue) || $matchValue === '') {
            throw new InvalidConfigurationException('Mock match type/value is invalid.');
        }

        $resultType = $result['type'] ?? null;
        if ($resultType === 'success') {
            if (!is_string($result['price'] ?? null) || !is_string($result['currency'] ?? null)) {
                throw new InvalidConfigurationException('Mock success requires string price and currency.');
            }
            try {
                $validated = CollectorItemResult::success('configuration', $result['price'], $result['currency']);
            } catch (\InvalidArgumentException $exception) {
                throw new InvalidConfigurationException($exception->getMessage(), 0, $exception);
            }
            return new self($matchType, $matchValue, 'success', $validated->price, $validated->currency, null, null);
        }
        if ($resultType === 'error') {
            if (!is_string($result['code'] ?? null) || trim($result['code']) === '' || !is_string($result['message'] ?? null) || trim($result['message']) === '') {
                throw new InvalidConfigurationException('Mock error requires a non-empty code and message.');
            }
            return new self($matchType, $matchValue, 'error', null, null, $result['code'], $result['message']);
        }

        throw new InvalidConfigurationException('Mock result type must be success or error.');
    }

    public function matches(string $url): bool
    {
        return $this->matchType === 'exact'
            ? $url === $this->matchValue
            : str_contains($url, $this->matchValue);
    }

    public function resultFor(string $id): CollectorItemResult
    {
        return $this->resultType === 'success'
            ? CollectorItemResult::success($id, (string) $this->price, (string) $this->currency)
            : CollectorItemResult::failure($id, (string) $this->errorCode, (string) $this->errorMessage);
    }
}
