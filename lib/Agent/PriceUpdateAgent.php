<?php

declare(strict_types=1);

namespace KK\PriceWatch\Agent;

use KK\PriceWatch\Scheduling\ScheduledPriceUpdateRunnerFactory;
use Throwable;

final class PriceUpdateAgent
{
    public const INVOCATION = '\\' . self::class . '::run();';

    public static function run(): string
    {
        try {
            ScheduledPriceUpdateRunnerFactory::createDefault()->run();
        } catch (Throwable) {
            // An adapter failure must not unregister the recurring agent.
        }
        return self::INVOCATION;
    }
}
