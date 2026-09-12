<?php

declare(strict_types=1);

namespace KK\PriceWatch\Scheduling;

interface ScheduledRunLockInterface
{
    public function acquire(): bool;
    public function release(): void;
}
