<?php
declare(strict_types=1);
namespace KK\PriceWatch\Notification;

use Bitrix\Main\Config\Option;

final class BitrixNotificationSettingsProvider implements NotificationSettingsProviderInterface
{
    public const MODULE_ID = 'kk.pricewatch';
    public function get(): NotificationSettings
    {
        return new NotificationSettings(
            Option::get(self::MODULE_ID, 'notifications_enabled', 'N') === 'Y',
            NotificationSettings::normalizeEmails(Option::get(self::MODULE_ID, 'notification_emails', '')),
            trim(Option::get(self::MODULE_ID, 'notification_site_id', '')),
            Option::get(self::MODULE_ID, 'send_recovery', 'Y') !== 'N',
        );
    }
}
