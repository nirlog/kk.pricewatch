<?php
declare(strict_types=1);
namespace KK\PriceWatch\Installer;

use CAgent;
use KK\PriceWatch\Agent\NotificationAgent;

final class NotificationAgentInstaller
{
    public const MODULE_ID = 'kk.pricewatch';
    public function install(): void
    {
        $agents = CAgent::GetList(['ID' => 'ASC'], ['MODULE_ID' => self::MODULE_ID, '=NAME' => NotificationAgent::INVOCATION]);
        if (!$agents->Fetch()) CAgent::AddAgent(NotificationAgent::INVOCATION, self::MODULE_ID, 'N', 3600, '', 'N');
        while ($duplicate = $agents->Fetch()) CAgent::Delete((int) $duplicate['ID']);
    }
    public function uninstall(): void { CAgent::RemoveAgent(NotificationAgent::INVOCATION, self::MODULE_ID); }
}
