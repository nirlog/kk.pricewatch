<?php

declare(strict_types=1);

namespace KK\PriceWatch\Tests\Model;

use InvalidArgumentException;
use KK\PriceWatch\Model\CollectorOptions;
use PHPUnit\Framework\TestCase;

final class CollectorOptionsTest extends TestCase
{
    public function testEmptyOptionsRoundTripAsObject(): void
    {
        self::assertSame('{}', CollectorOptions::encode([]));
        self::assertSame([], CollectorOptions::decode('{}'));
        self::assertSame([], CollectorOptions::decode(null));
        self::assertSame([], CollectorOptions::decode(''));
    }

    public function testNestedUnicodeAndOpaqueKeysRoundTrip(): void
    {
        $options = [
            'region' => 'Санкт-Петербург',
            'price_selector' => '.price',
            'site_specific' => ['enabled' => true, 'values' => [1, 'two']],
        ];

        $json = CollectorOptions::encode($options);
        self::assertStringContainsString('Санкт-Петербург', $json);
        self::assertSame($options, CollectorOptions::decode($json));
    }

    public function testInvalidJsonIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        CollectorOptions::decode('{invalid');
    }

    public function testJsonListIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        CollectorOptions::decode('["value"]');
    }

    public function testNonEmptyPhpListIsRejectedOnEncode(): void
    {
        $this->expectException(InvalidArgumentException::class);
        CollectorOptions::encode(['value']);
    }
}
