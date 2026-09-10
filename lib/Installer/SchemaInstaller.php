<?php

declare(strict_types=1);

namespace KK\PriceWatch\Installer;

use Bitrix\Main\Application;
use Bitrix\Main\DB\Connection;
use KK\PriceWatch\Model\CompetitorTable;
use KK\PriceWatch\Model\ProductCompetitorTable;

final class SchemaInstaller
{
    public function install(): void
    {
        $connection = Application::getConnection();

        if (!$connection->isTableExists(CompetitorTable::getTableName())) {
            CompetitorTable::getEntity()->createDbTable();
        }

        $table = ProductCompetitorTable::getTableName();
        if (!$connection->isTableExists($table)) {
            ProductCompetitorTable::getEntity()->createDbTable();
        }

        $this->ensureIndex(
            $table,
            'ux_kk_pw_pc_identity',
            ['PRODUCT_ID', 'COMPETITOR_ID', 'URL_HASH'],
            Connection::INDEX_UNIQUE
        );
        $this->ensureIndex($table, 'ix_kk_pw_pc_product', ['PRODUCT_ID']);
        $this->ensureIndex($table, 'ix_kk_pw_pc_competitor', ['COMPETITOR_ID']);
    }

    private function ensureIndex(string $table, string $name, array $columns, ?string $type = null): void
    {
        $connection = Application::getConnection();
        if (!$connection->isIndexExists($table, $columns)) {
            $connection->createIndex($table, $name, $columns, null, $type);
        }
    }
}
