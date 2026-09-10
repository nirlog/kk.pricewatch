<?php

declare(strict_types=1);

namespace KK\PriceWatch\Tests\Model;

use InvalidArgumentException;
use KK\PriceWatch\Model\CollectionStatus;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CollectionStatusTest extends TestCase
{
    public static function validStatuses(): array
    {
        return [['new'], ['success'], ['error']];
    }

    #[DataProvider('validStatuses')]
    public function testKnownStatusIsAccepted(string $status): void
    {
        CollectionStatus::assertValid($status);
        self::assertTrue(CollectionStatus::isValid($status));
    }

    #[DataProvider('invalidStatuses')]
    public function testUnknownStatusIsRejected(string $status): void
    {
        self::assertFalse(CollectionStatus::isValid($status));
        $this->expectException(InvalidArgumentException::class);
        CollectionStatus::assertValid($status);
    }

    public static function invalidStatuses(): array
    {
        return [[''], ['pending'], ['running'], ['unknown']];
    }
}
