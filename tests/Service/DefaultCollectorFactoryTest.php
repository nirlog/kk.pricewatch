<?php

declare(strict_types=1);

namespace KK\PriceWatch\Tests\Service;

use KK\PriceWatch\Collector\CollectorItem;
use KK\PriceWatch\Collector\CollectorRequest;
use KK\PriceWatch\Collector\Exception\InvalidConfigurationException;
use KK\PriceWatch\Collector\Http\HttpFetchResult;
use KK\PriceWatch\Collector\Http\HttpHtmlCollector;
use KK\PriceWatch\Collector\Http\HttpTransportInterface;
use KK\PriceWatch\Collector\External\ExternalCollector;
use KK\PriceWatch\Collector\External\ExternalCollectorHttpResult;
use KK\PriceWatch\Collector\External\ExternalCollectorSettings;
use KK\PriceWatch\Collector\External\ExternalCollectorSettingsProviderInterface;
use KK\PriceWatch\Collector\External\ExternalCollectorTransportInterface;
use KK\PriceWatch\Collector\Mock\MockCollector;
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

    public function testMalformedMockOptionsAreUnavailable(): void
    {
        $factory = new DefaultCollectorFactory();
        foreach ([$this->competitor(['scenarios' => 'bad'])] as $competitor) {
            try { $factory->create($competitor); self::fail('Configuration should fail.'); } catch (InvalidConfigurationException) { self::assertTrue(true); }
        }
    }

    public function testFactoryCreatesMockHttpAndExternal(): void
    {
        $transport = new class implements HttpTransportInterface {
            public function fetch(string $url): HttpFetchResult { return HttpFetchResult::failure(); }
        };
        self::assertInstanceOf(MockCollector::class, (new DefaultCollectorFactory())->create($this->competitor([])));
        self::assertInstanceOf(HttpHtmlCollector::class, (new DefaultCollectorFactory($transport))->create([
            'COLLECTOR_TYPE' => CollectorType::HTTP,
            'DOMAIN' => 'example.test',
            'COLLECTOR_OPTIONS' => '{"price_selector":{"type":"xpath","value":"//b"},"currency":"RUB"}',
        ]));
        $settings = new class implements ExternalCollectorSettingsProviderInterface {
            public function get(): ExternalCollectorSettings { return new ExternalCollectorSettings(true, 'https://collector.test', 'secret'); }
        };
        $externalTransport = new class implements ExternalCollectorTransportInterface {
            public int $calls = 0;
            public function post(string $endpoint, string $body, array $headers, int $connectTimeout, int $requestTimeout): ExternalCollectorHttpResult { ++$this->calls; return ExternalCollectorHttpResult::failure(); }
        };
        self::assertInstanceOf(ExternalCollector::class, (new DefaultCollectorFactory($transport, null, $settings, $externalTransport))->create([
            'COLLECTOR_TYPE' => CollectorType::EXTERNAL, 'COLLECTOR_HANDLER' => '/api/collect',
        ]));
        self::assertSame(0, $externalTransport->calls, 'Factory must not perform network I/O.');
    }

    #[DataProvider('invalidHttpConfigurations')]
    public function testInvalidHttpConfigurationFailsSafely(array $competitor): void
    {
        $this->expectException(InvalidConfigurationException::class);
        (new DefaultCollectorFactory())->create($competitor + ['COLLECTOR_TYPE' => CollectorType::HTTP]);
    }

    public static function invalidHttpConfigurations(): array
    {
        return [
            [['DOMAIN' => '127.0.0.1', 'COLLECTOR_OPTIONS' => '{}']],
            [['DOMAIN' => 'example.test', 'COLLECTOR_OPTIONS' => '{}']],
            [['DOMAIN' => 'example.test', 'COLLECTOR_OPTIONS' => '{bad']],
            [['DOMAIN' => 'example.test', 'COLLECTOR_OPTIONS' => '{"price_selector":{"type":"css","value":"b"},"currency":"RUB"}']],
            [['DOMAIN' => 'example.test', 'COLLECTOR_OPTIONS' => '{"price_selector":{"type":"xpath","value":"//b"},"currency":"rub"}']],
        ];
    }

    private function competitor(array $options): array
    {
        return ['COLLECTOR_TYPE' => CollectorType::MOCK, 'COLLECTOR_OPTIONS' => CollectorOptions::encode($options)];
    }
}
