<?php

declare(strict_types=1);

namespace KK\PriceWatch\Scheduling;

use KK\PriceWatch\Service\PriceUpdateServiceFactory;

final class ScheduledPriceUpdateRunnerFactory
{
    public static function createDefault(): ScheduledPriceUpdateRunner
    {
        return new ScheduledPriceUpdateRunner(
            PriceUpdateServiceFactory::createDefault(),
            new OrmActiveLinkRepository(),
            new BitrixDbScheduledRunLock(),
        );
    }
}
