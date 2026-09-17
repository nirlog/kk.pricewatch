<?php
declare(strict_types=1);
namespace KK\PriceWatch\Agent;

use KK\PriceWatch\Notification\NotificationRunnerFactory;
use Throwable;

final class NotificationAgent
{
    public const INVOCATION = '\\' . self::class . '::run();';
    public static function run(): string
    {
        try { NotificationRunnerFactory::createDefault()->run(); } catch (Throwable) {}
        return self::INVOCATION;
    }
}
