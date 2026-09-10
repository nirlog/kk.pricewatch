<?php

declare(strict_types=1);

namespace KK\PriceWatch\Tests\Collector;

use InvalidArgumentException;
use KK\PriceWatch\Collector\CollectorError;
use KK\PriceWatch\Collector\CollectorItemResult;
use KK\PriceWatch\Collector\CollectorResponse;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class CollectorResponseTest extends TestCase
{
    public function testSuccessFactoryPreservesIdentityAndOmitsGlobalError(): void
    {
        $item = CollectorItemResult::success('item-1', '99990.00', 'RUB');

        $response = CollectorResponse::success('request-1', [$item]);

        self::assertSame('1.0', $response->schemaVersion);
        self::assertSame('request-1', $response->requestId);
        self::assertTrue($response->success);
        self::assertNull($response->error);
        self::assertSame([$item], $response->items);
        self::assertArrayNotHasKey('error', $response->toArray());
    }

    public function testMixedBatchRemainsGloballySuccessful(): void
    {
        $response = CollectorResponse::success('mixed-request', [
            CollectorItemResult::success('found', '12.00', 'RUB'),
            CollectorItemResult::failure('missing', 'PRICE_NOT_FOUND', 'Price was not found'),
        ]);

        self::assertTrue($response->success);
        self::assertTrue($response->items[0]->success);
        self::assertFalse($response->items[1]->success);
    }

    public function testFailureHasStructuredErrorAndSerializesIt(): void
    {
        $response = CollectorResponse::failure(
            'request-timeout',
            'COLLECTOR_TIMEOUT',
            'Collector request timed out',
        );

        self::assertSame('request-timeout', $response->requestId);
        self::assertFalse($response->success);
        self::assertInstanceOf(CollectorError::class, $response->error);
        self::assertSame([], $response->items);
        self::assertSame([
            'schema_version' => '1.0',
            'request_id' => 'request-timeout',
            'success' => false,
            'items' => [],
            'error' => [
                'code' => 'COLLECTOR_TIMEOUT',
                'message' => 'Collector request timed out',
            ],
        ], $response->toArray());
    }

    public function testDuplicateResponseItemIdsAreRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Duplicate collector response item ID "duplicate".');

        CollectorResponse::success('request-1', [
            CollectorItemResult::success('duplicate', '1.00', 'RUB'),
            CollectorItemResult::failure('duplicate', 'PRICE_NOT_FOUND', 'Missing'),
        ]);
    }

    public function testInvalidSuccessErrorCombinationsCannotUseThePublicApi(): void
    {
        $constructor = (new ReflectionClass(CollectorResponse::class))->getConstructor();

        self::assertNotNull($constructor);
        self::assertTrue($constructor->isPrivate());
        self::assertSame(['requestId', 'items'], array_map(
            static fn (\ReflectionParameter $parameter): string => $parameter->getName(),
            (new ReflectionClass(CollectorResponse::class))->getMethod('success')->getParameters(),
        ));
        self::assertSame(['requestId', 'code', 'message'], array_map(
            static fn (\ReflectionParameter $parameter): string => $parameter->getName(),
            (new ReflectionClass(CollectorResponse::class))->getMethod('failure')->getParameters(),
        ));
    }
}
