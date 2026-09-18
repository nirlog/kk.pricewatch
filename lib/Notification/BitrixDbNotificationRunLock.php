<?php
declare(strict_types=1);
namespace KK\PriceWatch\Notification;

use Bitrix\Main\Application;
use RuntimeException;

final class BitrixDbNotificationRunLock implements NotificationRunLockInterface
{
    public const NAME = 'kk.pricewatch.notification_scan';
    private bool $acquired = false;
    public function acquire(): bool
    {
        $db = Application::getConnection();
        if (!method_exists($db, 'lock') || !method_exists($db, 'unlock')) throw new RuntimeException('Named database locks are not supported.');
        return $this->acquired = $db->lock(self::NAME, 0);
    }
    public function release(): void { if ($this->acquired) { Application::getConnection()->unlock(self::NAME); $this->acquired = false; } }
}
