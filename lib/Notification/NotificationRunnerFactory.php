<?php
declare(strict_types=1);
namespace KK\PriceWatch\Notification;

final class NotificationRunnerFactory
{
    public static function createDefault(): NotificationRunner
    {
        return new NotificationRunner(new BitrixNotificationSettingsProvider(), new OrmNotificationLinkRepository(),
            new OrmNotificationStateRepository(), new NotificationRuleEvaluator(), new BitrixProductLabelResolver(),
            new BitrixMailNotificationTransport(), new BitrixDbNotificationRunLock());
    }
}
