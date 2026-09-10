<?php

declare(strict_types=1);

namespace KK\PriceWatch\Tests\Model;

use InvalidArgumentException;
use KK\PriceWatch\Model\CollectorType;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CollectorTypeTest extends TestCase
{
    #[DataProvider('validTypes')]
    public function testKnownTypeIsAccepted(string $type): void
    {
        CollectorType::validate($type);
        self::assertTrue(CollectorType::isValid($type));
    }

    public static function validTypes(): array
    {
        return [[CollectorType::MOCK], [CollectorType::EXTERNAL]];
    }

    #[DataProvider('invalidTypes')]
    public function testUnknownTypeIsRejected(string $type): void
    {
        self::assertFalse(CollectorType::isValid($type));
        $this->expectException(InvalidArgumentException::class);
        CollectorType::validate($type);
    }

    public static function invalidTypes(): array
    {
        return [[''], ['dns'], ['browser'], ['unknown']];
    }
}
