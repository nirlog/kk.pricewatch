<?php
declare(strict_types=1);
namespace KK\PriceWatch\Notification;

final class MailSendResult
{
    public static function isSuccess(string $result, string $bitrixSuccess): bool
    {
        return $result === $bitrixSuccess || $result === 'Y';
    }
}
