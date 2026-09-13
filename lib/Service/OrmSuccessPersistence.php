<?php

declare(strict_types=1);

namespace KK\PriceWatch\Service;

use Bitrix\Main\Application;
use Bitrix\Main\Type\DateTime;
use KK\PriceWatch\Model\CollectionStatus;
use KK\PriceWatch\Model\PriceHistoryTable;
use KK\PriceWatch\Model\ProductCompetitorTable;
use RuntimeException;
use Throwable;

/** Atomic, per-link success persistence. Collection has completed before this is called. */
final class OrmSuccessPersistence implements SuccessPersistenceInterface
{
    public function persist(int $linkId, string $price, string $currency, DateTime $collectedAt): bool
    {
        $connection = Application::getConnection();
        $connection->startTransaction();

        try {
            // Bitrix Connection exposes queryExecute(); the integer cast and fixed ORM table name
            // keep this narrowly-scoped locking statement free of user-controlled SQL fragments.
            $connection->queryExecute(
                'SELECT ID FROM ' . ProductCompetitorTable::getTableName() . ' WHERE ID = ' . (int) $linkId . ' FOR UPDATE'
            );
            $link = ProductCompetitorTable::getByPrimary($linkId, [
                'select' => ['ID', 'PRODUCT_ID', 'COMPETITOR_ID', 'URL', 'URL_HASH'],
            ])->fetch();
            if ($link === false) {
                throw new RuntimeException('The monitored link disappeared during persistence.');
            }

            $identity = [
                '=PRODUCT_COMPETITOR_ID' => $linkId,
                '=PRODUCT_ID' => (int) $link['PRODUCT_ID'],
                '=COMPETITOR_ID' => (int) $link['COMPETITOR_ID'],
                '=URL' => (string) $link['URL'],
                '=URL_HASH' => (string) $link['URL_HASH'],
            ];
            $latest = PriceHistoryTable::getList([
                'select' => ['PRICE', 'CURRENCY'],
                'filter' => $identity,
                'order' => ['COLLECTED_AT' => 'DESC', 'ID' => 'DESC'],
                'limit' => 1,
            ])->fetch();

            if (PriceHistoryDecision::shouldAppend(
                $latest === false ? null : (string) $latest['PRICE'],
                $latest === false ? null : (string) $latest['CURRENCY'],
                $price,
                $currency,
            )) {
                $history = PriceHistoryTable::add([
                    'PRODUCT_COMPETITOR_ID' => $linkId,
                    'PRODUCT_ID' => (int) $link['PRODUCT_ID'],
                    'COMPETITOR_ID' => (int) $link['COMPETITOR_ID'],
                    'URL' => (string) $link['URL'],
                    'URL_HASH' => (string) $link['URL_HASH'],
                    'PRICE' => $price,
                    'CURRENCY' => $currency,
                    'COLLECTED_AT' => $collectedAt,
                ]);
                if (!$history->isSuccess()) {
                    throw new RuntimeException('Could not append price history.');
                }
            }

            $current = ProductCompetitorTable::update($linkId, [
                'CURRENT_PRICE' => $price,
                'CURRENCY' => $currency,
                'STATUS' => CollectionStatus::SUCCESS,
                'ERROR_CODE' => null,
                'ERROR_MESSAGE' => null,
                'LAST_CHECK_AT' => $collectedAt,
                'LAST_SUCCESS_AT' => $collectedAt,
            ]);
            if (!$current->isSuccess()) {
                throw new RuntimeException('Could not update collection state.');
            }

            $connection->commitTransaction();
            return true;
        } catch (Throwable) {
            $connection->rollbackTransaction();
            return false;
        }
    }
}
