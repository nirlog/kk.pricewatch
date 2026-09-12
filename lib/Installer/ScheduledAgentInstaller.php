<?php

declare(strict_types=1);

namespace KK\PriceWatch\Installer;

use CAgent;
use KK\PriceWatch\Agent\PriceUpdateAgent;

final class ScheduledAgentInstaller
{
    public const MODULE_ID = 'kk.pricewatch';

    public function install(): void
    {
        $agents = CAgent::GetList(
            ['ID' => 'ASC'],
            ['MODULE_ID' => self::MODULE_ID, '=NAME' => PriceUpdateAgent::INVOCATION]
        );
        $existing = $agents->Fetch();
        if (!$existing) {
            CAgent::AddAgent(
                PriceUpdateAgent::INVOCATION,
                self::MODULE_ID,
                'N',
                3600,
                '',
                'N'
            );
            return;
        }

        // The exact filter above makes duplicate cleanup incapable of touching
        // any other module agent or callback.
        while ($duplicate = $agents->Fetch()) {
            CAgent::Delete((int) $duplicate['ID']);
        }
    }

    public function uninstall(): void
    {
        CAgent::RemoveAgent(PriceUpdateAgent::INVOCATION, self::MODULE_ID);
    }
}
