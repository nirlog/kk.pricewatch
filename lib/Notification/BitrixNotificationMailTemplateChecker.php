<?php
declare(strict_types=1);
namespace KK\PriceWatch\Notification;

use CEventMessage;

final class BitrixNotificationMailTemplateChecker
{
    public function existsForSite(string $siteId): bool
    {
        if ($siteId === '') return false;
        $query = CEventMessage::GetList($by = 'id', $order = 'asc', [
            'TYPE_ID' => BitrixMailNotificationTransport::EVENT_NAME,
            'ACTIVE' => 'Y',
        ]);
        while ($row = $query->Fetch()) {
            $siteIds = is_array($row['LID'] ?? null)
                ? $row['LID']
                : preg_split('/[,\s]+/', (string) ($row['LID'] ?? ''), -1, PREG_SPLIT_NO_EMPTY);
            if (in_array($siteId, $siteIds ?: [], true)) return true;
        }
        return false;
    }
}
