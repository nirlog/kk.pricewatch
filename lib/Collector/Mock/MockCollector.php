<?php

declare(strict_types=1);

namespace KK\PriceWatch\Collector\Mock;

use KK\PriceWatch\Collector\CollectorInterface;
use KK\PriceWatch\Collector\CollectorItem;
use KK\PriceWatch\Collector\CollectorItemResult;
use KK\PriceWatch\Collector\CollectorRequest;
use KK\PriceWatch\Collector\CollectorResponse;
use KK\PriceWatch\Collector\Exception\InvalidConfigurationException;

final class MockCollector implements CollectorInterface
{
    /** @var list<MockScenario> */
    private array $scenarios;

    /**
     * A null default means strict mode. Supplying a default enables
     * default-success mode.
     *
     * @param list<MockScenario|array<string, mixed>> $scenarios
     */
    public function __construct(array $scenarios = [], private readonly ?MockDefaultResult $defaultResult = null)
    {
        $this->scenarios = array_map(
            static function (mixed $scenario): MockScenario {
                if ($scenario instanceof MockScenario) {
                    return $scenario;
                }
                if (!is_array($scenario)) {
                    throw new InvalidConfigurationException('Each mock scenario must be an array or MockScenario.');
                }
                return MockScenario::fromArray($scenario);
            },
            array_values($scenarios),
        );
    }

    public function collect(CollectorRequest $request): CollectorResponse
    {
        $results = array_map(fn (CollectorItem $item): CollectorItemResult => $this->collectItem($item), $request->items);

        return new CollectorResponse($request->schemaVersion, $request->requestId, true, $results);
    }

    private function collectItem(CollectorItem $item): CollectorItemResult
    {
        foreach ($this->scenarios as $scenario) {
            if ($scenario->matches($item->url)) {
                return $scenario->resultFor($item->id);
            }
        }

        if ($this->defaultResult !== null) {
            return CollectorItemResult::success($item->id, $this->defaultResult->price, $this->defaultResult->currency);
        }

        return CollectorItemResult::failure($item->id, 'MOCK_NO_MATCH', 'No mock scenario matched the item URL.');
    }
}
