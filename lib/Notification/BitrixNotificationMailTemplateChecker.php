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
            if ($this->isValidForSite($row, $siteId)) return true;
        }
        return false;
    }

    /** @param array<string, mixed> $row */
    private function isValidForSite(array $row, string $siteId): bool
    {
        if (($row['ACTIVE'] ?? null) !== 'Y') return false;
        if (!is_string($row['SUBJECT'] ?? null) || trim($row['SUBJECT']) === '') return false;
        if (!is_string($row['MESSAGE'] ?? null) || trim($row['MESSAGE']) === '') return false;

        $siteIds = is_array($row['LID'] ?? null)
            ? $row['LID']
            : preg_split('/[,\s]+/', (string) ($row['LID'] ?? ''), -1, PREG_SPLIT_NO_EMPTY);
        return in_array($siteId, $siteIds ?: [], true);
    }
}
