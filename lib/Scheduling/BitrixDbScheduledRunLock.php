<?php

declare(strict_types=1);

namespace KK\PriceWatch\Scheduling;

use Bitrix\Main\Application;
use RuntimeException;

final class BitrixDbScheduledRunLock implements ScheduledRunLockInterface
{
    public const NAME = 'kk.pricewatch.scheduled_price_update';

    private bool $acquired = false;

    public function acquire(): bool
    {
        $connection = Application::getConnection();
        if (!method_exists($connection, 'lock') || !method_exists($connection, 'unlock')) {
            throw new RuntimeException('Named database locks are not supported.');
        }

        $this->acquired = $connection->lock(self::NAME, 0);
        return $this->acquired;
    }

    public function release(): void
    {
        if ($this->acquired) {
            Application::getConnection()->unlock(self::NAME);
            $this->acquired = false;
        }
    }
}
