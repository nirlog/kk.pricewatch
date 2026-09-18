<?php
declare(strict_types=1);
namespace KK\PriceWatch\Notification;
interface NotificationSettingsProviderInterface { public function get(): NotificationSettings; }
