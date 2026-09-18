<?php
declare(strict_types=1);
namespace KK\PriceWatch\Notification;

interface MailEventSenderInterface
{
    /** @param array<string,string> $fields */
    public function send(string $eventName, string $siteId, array $fields, string $duplicate): string;
}
