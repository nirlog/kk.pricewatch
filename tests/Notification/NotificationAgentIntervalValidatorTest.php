<?php

declare(strict_types=1);

namespace KK\PriceWatch\Tests\Notification;

use KK\PriceWatch\Notification\NotificationAgentIntervalValidator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class NotificationAgentIntervalValidatorTest extends TestCase
{
    #[DataProvider('values')]
    public function testValidation(mixed $value, ?int $expected): void
    {
        self::assertSame($expected, (new NotificationAgentIntervalValidator())->validate($value));
    }

    public static function values(): array
    {
        return [
            ['5', 5], ['60', 60], ['1440', 1440], ['0', null], ['4', null],
            ['1441', null], ['-1', null], ['1.5', null], ['abc', null],
        ];
    }
}
