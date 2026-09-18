<?php
declare(strict_types=1);
namespace KK\PriceWatch\Notification;

use CEvent;

final class BitrixMailEventSender implements MailEventSenderInterface
{
    public function send(string $eventName, string $siteId, array $fields, string $duplicate): string
    {
        return (string) CEvent::SendImmediate($eventName, $siteId, $fields, $duplicate);
    }
}
