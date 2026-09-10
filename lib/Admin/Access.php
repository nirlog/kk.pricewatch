<?php

declare(strict_types=1);

namespace KK\PriceWatch\Admin;

final class Access
{
    public const MODULE_ID = 'kk.pricewatch';

    public static function level(): string
    {
        return (string) \CMain::GetUserRight(self::MODULE_ID);
    }

    public static function canRead(): bool
    {
        return self::level() >= 'R';
    }

    public static function canWrite(): bool
    {
        return self::level() >= 'W';
    }
}
