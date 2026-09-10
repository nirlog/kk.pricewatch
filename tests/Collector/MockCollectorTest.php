<?php

declare(strict_types=1);

namespace KK\PriceWatch\Tests\Collector;

use KK\PriceWatch\Collector\CollectorInterface;
use KK\PriceWatch\Collector\CollectorItem;
use KK\PriceWatch\Collector\CollectorRequest;
use KK\PriceWatch\Collector\Exception\InvalidConfigurationException;
use KK\PriceWatch\Collector\Exception\InvalidRequestException;
use KK\PriceWatch\Collector\Mock\MockCollector;
use KK\PriceWatch\Collector\Mock\MockDefaultResult;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class MockCollectorTest extends TestCase
{
    public function testExactMatchPreservesRequestItemAndCompleteUrl(): void
    {
        $url = 'https://example.test/product/1?region=msk&sort=price';
        $request = $this->request([new CollectorItem('product-link-1', $url)], 'request-exact');
        $collector = new MockCollector([$this->successScenario('exact', $url, '99990.00')]);

        $response = $collector->collect($request);

        self::assertInstanceOf(CollectorInterface::class, $collector);
        self::assertSame('request-exact', $response->requestId);
        self::assertSame('product-link-1', $response->items[0]->id);
        self::assertSame('99990.00', $response->items[0]->price);
        self::assertSame($url, $request->items[0]->url);
        self::assertSame($url, $request->toArray()['items'][0]['url']);
    }

    public function testContainsMatchSucceeds(): void
    {
        $collector = new MockCollector([$this->successScenario('contains', '/product/', '12.30')]);
        $response = $collector->collect($this->request([new CollectorItem('one', 'https://shop.test/product/42')]));

        self::assertTrue($response->items[0]->success);
        self::assertSame('12.30', $response->items[0]->price);
    }

    public function testExplicitItemErrorIsStructured(): void
    {
        $collector = new MockCollector([[
            'match' => ['field' => 'url', 'type' => 'contains', 'value' => '/missing'],
            'result' => ['type' => 'error', 'code' => 'PRICE_NOT_FOUND', 'message' => 'Price was not found'],
        ]]);

        $result = $collector->collect($this->request([new CollectorItem('missing', 'https://shop.test/missing')]))->items[0];

        self::assertFalse($result->success);
        self::assertSame('PRICE_NOT_FOUND', $result->error?->code);
        self::assertSame('Price was not found', $result->error?->message);
    }

    public function testStrictModeIsDefaultAndReturnsNoMatchError(): void
    {
        $result = (new MockCollector())->collect($this->request([new CollectorItem('one', 'https://shop.test/one')]))->items[0];

        self::assertFalse($result->success);
        self::assertSame('MOCK_NO_MATCH', $result->error?->code);
    }

    public function testConfiguredDefaultSuccessHandlesNoMatch(): void
    {
        $collector = new MockCollector([], new MockDefaultResult('123.45', 'RUB'));
        $result = $collector->collect($this->request([new CollectorItem('one', 'https://shop.test/one')]))->items[0];

        self::assertTrue($result->success);
        self::assertSame('123.45', $result->price);
        self::assertSame('RUB', $result->currency);
    }

    public function testMixedBatchEvaluatesEveryItemInOrder(): void
    {
        $collector = new MockCollector([
            $this->successScenario('exact', 'https://shop.test/ok', '7.00'),
            [
                'match' => ['field' => 'url', 'type' => 'exact', 'value' => 'https://shop.test/missing'],
                'result' => ['type' => 'error', 'code' => 'PRICE_NOT_FOUND', 'message' => 'Missing'],
            ],
        ]);

        $response = $collector->collect($this->request([
            new CollectorItem('ok', 'https://shop.test/ok'),
            new CollectorItem('missing', 'https://shop.test/missing'),
            new CollectorItem('unmatched', 'https://shop.test/other'),
        ], 'batch-id'));

        self::assertTrue($response->success);
        self::assertSame(['ok', 'missing', 'unmatched'], array_column($response->toArray()['items'], 'id'));
        self::assertTrue($response->items[0]->success);
        self::assertSame('PRICE_NOT_FOUND', $response->items[1]->error?->code);
        self::assertSame('MOCK_NO_MATCH', $response->items[2]->error?->code);
    }

    #[DataProvider('invalidMoneyProvider')]
    public function testInvalidScenarioMoneyIsRejected(mixed $price): void
    {
        $this->expectException(InvalidConfigurationException::class);
        new MockCollector([$this->successScenario('exact', 'https://shop.test/one', $price)]);
    }

    /** @return iterable<string, array{mixed}> */
    public static function invalidMoneyProvider(): iterable
    {
        yield 'empty' => [''];
        yield 'text' => ['arbitrary'];
        yield 'NaN' => ['NaN'];
        yield 'negative' => ['-1.00'];
        yield 'float' => [1.2];
    }

    public function testDuplicateItemIdsAreRejected(): void
    {
        $this->expectException(InvalidRequestException::class);
        $this->request([
            new CollectorItem('duplicate', 'https://shop.test/one'),
            new CollectorItem('duplicate', 'https://shop.test/two'),
        ]);
    }

    public function testEmptyItemListIsRejected(): void
    {
        $this->expectException(InvalidRequestException::class);
        $this->request([]);
    }

    /** @param list<CollectorItem> $items */
    private function request(array $items, string $id = 'request-id'): CollectorRequest
    {
        return new CollectorRequest('1.0', $id, $items);
    }

    /** @return array<string, mixed> */
    private function successScenario(string $type, string $value, mixed $price): array
    {
        return [
            'match' => ['field' => 'url', 'type' => $type, 'value' => $value],
            'result' => ['type' => 'success', 'price' => $price, 'currency' => 'RUB'],
        ];
    }
}
