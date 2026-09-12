<?php

declare(strict_types=1);

namespace KK\PriceWatch\Service;

use KK\PriceWatch\Collector\CollectorInterface;
use KK\PriceWatch\Collector\Exception\InvalidConfigurationException;
use KK\PriceWatch\Collector\Mock\MockCollector;
use KK\PriceWatch\Collector\Mock\MockDefaultResult;
use KK\PriceWatch\Model\CollectorOptions;
use KK\PriceWatch\Model\CollectorType;

final class DefaultCollectorFactory implements CollectorFactoryInterface
{
    public function create(array $competitor): CollectorInterface
    {
        if (($competitor['COLLECTOR_TYPE'] ?? null) !== CollectorType::MOCK) {
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
}
