<?php
declare(strict_types=1);
namespace KK\PriceWatch\Notification;

use Bitrix\Main\Application;
use Bitrix\Main\Type\DateTime;
use DateTimeInterface;
use KK\PriceWatch\Model\NotificationStateTable;
use RuntimeException;

final class OrmNotificationStateRepository implements NotificationStateRepositoryInterface
{
    public function findForLinks(array $linkIds): array
    {
        if ($linkIds === []) return [];
        $result = [];
        $query = NotificationStateTable::getList(['select' => ['PRODUCT_COMPETITOR_ID', 'RULE_CODE', 'NOTIFIED_ACTIVE', 'NOTIFIED_FINGERPRINT'], 'filter' => ['@PRODUCT_COMPETITOR_ID' => $linkIds]]);
        while ($row = $query->fetch()) {
            $result[(int) $row['PRODUCT_COMPETITOR_ID']][(string) $row['RULE_CODE']] = new NotificationRuleState(
                $row['NOTIFIED_ACTIVE'] === 'Y', $row['NOTIFIED_FINGERPRINT'] !== null ? (string) $row['NOTIFIED_FINGERPRINT'] : null
            );
        }
        return $result;
    }

    public function saveDelivered(array $transitions, DateTimeInterface $at): void
    {
        $now = DateTime::createFromTimestamp($at->getTimestamp());
        $connection = Application::getConnection();
        $connection->startTransaction();
        try {
            foreach ($transitions as $transition) {
                $existing = NotificationStateTable::getList(['select' => ['ID'], 'filter' => [
                    '=PRODUCT_COMPETITOR_ID' => $transition->linkId, '=RULE_CODE' => $transition->ruleCode,
                ], 'limit' => 1])->fetch();
                $fields = ['NOTIFIED_ACTIVE' => $transition->current->active ? 'Y' : 'N',
                    'NOTIFIED_FINGERPRINT' => $transition->current->fingerprint, 'LAST_NOTIFIED_AT' => $now, 'UPDATED_AT' => $now];
                $save = $existing ? NotificationStateTable::update((int) $existing['ID'], $fields)
                    : NotificationStateTable::add($fields + ['PRODUCT_COMPETITOR_ID' => $transition->linkId, 'RULE_CODE' => $transition->ruleCode, 'CREATED_AT' => $now]);
                if (!$save->isSuccess()) throw new RuntimeException(implode('; ', $save->getErrorMessages()));
            }
            $connection->commitTransaction();
        } catch (\Throwable $error) {
            $connection->rollbackTransaction();
            throw $error;
        }
    }
}
