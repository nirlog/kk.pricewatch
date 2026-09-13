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
    public function persist(
        int $linkId,
        CollectedLinkIdentity $collectedIdentity,
        string $price,
        string $currency,
        DateTime $collectedAt,
    ): bool {
        $connection = Application::getConnection();
        $connection->startTransaction();

        try {
            // query() is Bitrix's SELECT API. Only a fixed table name and an integer
            // identifier are interpolated into this narrowly scoped locking query.
            $link = $connection->query(
                'SELECT ID, PRODUCT_ID, COMPETITOR_ID, URL, URL_HASH FROM '
                . ProductCompetitorTable::getTableName() . ' WHERE ID = ' . (int) $linkId . ' FOR UPDATE'
            )->fetch();
            if ($link === false) {
                throw new RuntimeException('The monitored link disappeared during persistence.');
            }
            if (!$collectedIdentity->matchesRow($link)) {
                throw new RuntimeException('The monitored link identity changed during collection.');
            }

            $identity = [
                '=PRODUCT_COMPETITOR_ID' => $linkId,
                '=PRODUCT_ID' => (int) $link['PRODUCT_ID'],
                '=COMPETITOR_ID' => (int) $link['COMPETITOR_ID'],
                '=URL' => (string) $link['URL'],
                '=URL_HASH' => (string) $link['URL_HASH'],
            ];
            $latestIdentity = PriceHistoryTable::getList([
                'select' => ['ID'],
                'filter' => $identity,
                // ID is append/commit order under the per-link row lock. A writer can
                // wait with an older COLLECTED_AT, so timestamps are not effective order.
                'order' => ['ID' => 'DESC'],
                'limit' => 1,
            ])->fetch();
            $latest = $latestIdentity === false ? false : $connection->query(
                'SELECT PRICE, CURRENCY FROM ' . PriceHistoryTable::getTableName()
                . ' WHERE ID = ' . (int) $latestIdentity['ID']
            )->fetch();

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
