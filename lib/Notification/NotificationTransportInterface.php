<?php
declare(strict_types=1);
namespace KK\PriceWatch\Notification;

interface NotificationTransportInterface
{
    /** @param list<string> $recipients */
    public function send(NotificationDigest $digest, array $recipients, string $siteId): bool;
}
