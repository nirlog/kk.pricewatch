<?php

declare(strict_types=1);

namespace KK\PriceWatch\Installer;

use Bitrix\Main\Application;
use KK\PriceWatch\Model\CompetitorTable;

final class SchemaInstaller
{
    public function install(): void
    {
        $connection = Application::getConnection();

        if (!$connection->isTableExists(CompetitorTable::getTableName())) {
            CompetitorTable::getEntity()->createDbTable();
        }
    }
}
