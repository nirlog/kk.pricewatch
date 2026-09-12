<?php

declare(strict_types=1);

namespace KK\PriceWatch\Tests\Service;

use KK\PriceWatch\Collector\CollectorItem;
use KK\PriceWatch\Collector\CollectorRequest;
use KK\PriceWatch\Collector\Exception\InvalidConfigurationException;
use KK\PriceWatch\Model\CollectorOptions;
use KK\PriceWatch\Model\CollectorType;
use KK\PriceWatch\Service\DefaultCollectorFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class DefaultCollectorFactoryTest extends TestCase
{
    #[DataProvider('scenarioProvider')]
    public function testConfiguredScenarios(string $url, bool $success, ?string $code): void
    {
        $factory = new DefaultCollectorFactory();
        $collector = $factory->create($this->competitor([
            'scenarios' => [
                ['match' => ['field' => 'url', 'type' => 'exact', 'value' => 'https://example.test/p?a=1&b=2'],
                    'result' => ['type' => 'success', 'price' => '123.45', 'currency' => 'RUB']],
                ['match' => ['field' => 'url', 'type' => 'contains', 'value' => '/missing/'],
                    'result' => ['type' => 'error', 'code' => 'PRICE_NOT_FOUND', 'message' => 'Not found']],
            ],
        ]));
        $response = $collector->collect(new CollectorRequest('1.0', 'request', [new CollectorItem('7', $url)]));
        self::assertSame($success, $response->items[0]->success);
        self::assertSame($code, $response->items[0]->error?->code);
    }

    public static function scenarioProvider(): array
    {
        return [
            'exact success' => ['https://example.test/p?a=1&b=2', true, null],
            'contains error' => ['https://example.test/missing/7', false, 'PRICE_NOT_FOUND'],
            'strict no match' => ['https://example.test/other', false, 'MOCK_NO_MATCH'],
        ];
    }

    public function testDefaultSuccess(): void
    {
        $collector = (new DefaultCollectorFactory())->create($this->competitor(['default' => ['price' => '99.00', 'currency' => 'RUB']]));
        $result = $collector->collect(new CollectorRequest('1.0', 'r', [new CollectorItem('1', 'https://example.test')]))->items[0];
        self::assertTrue($result->success);
        self::assertSame('99.00', $result->price);
    }

    public function testMalformedOptionsAndExternalAreUnavailable(): void
    {
        $factory = new DefaultCollectorFactory();
        foreach ([$this->competitor(['scenarios' => 'bad']), ['COLLECTOR_TYPE' => CollectorType::EXTERNAL, 'COLLECTOR_OPTIONS' => '{}']] as $competitor) {
            try { $factory->create($competitor); self::fail('Configuration should fail.'); } catch (InvalidConfigurationException) { self::assertTrue(true); }
        }
    }

    private function competitor(array $options): array
    {
        return ['COLLECTOR_TYPE' => CollectorType::MOCK, 'COLLECTOR_OPTIONS' => CollectorOptions::encode($options)];
    }
}
