<?php

declare(strict_types=1);

namespace KK\PriceWatch\Tests\Collector\External;

use KK\PriceWatch\Collector\Exception\InvalidConfigurationException;
use KK\PriceWatch\Collector\External\ExternalCollectorTimeouts;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ExternalCollectorTimeoutsTest extends TestCase
{
    public function testCanonicalTimeoutsAreNormalized(): void
    {
        $timeouts = ExternalCollectorTimeouts::fromStrings('5', '60');
        self::assertSame(5, $timeouts->connect);
        self::assertSame(60, $timeouts->request);
    }

    #[DataProvider('invalidTimeouts')]
    public function testNonCanonicalOrOutOfRangeTimeoutsAreRejected(string $connect, string $request): void
    {
        $this->expectException(InvalidConfigurationException::class);
        ExternalCollectorTimeouts::fromStrings($connect, $request);
    }

    public static function invalidTimeouts(): array
    {
        return [
            ['5abc', '60'], ['1.5', '60'], ['5e1', '60'], ['', '60'], ['05', '60'],
            ['0', '60'], ['31', '60'], ['5', '4'], ['5', '301'], ['20', '10'],
        ];
    }
}
