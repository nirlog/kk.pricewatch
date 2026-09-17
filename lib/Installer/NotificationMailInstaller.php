<?php
declare(strict_types=1);
namespace KK\PriceWatch\Installer;

use Bitrix\Main\Localization\Loc;
use CEventMessage;
use CEventType;
use KK\PriceWatch\Notification\BitrixMailNotificationTransport;

final class NotificationMailInstaller
{
    public function install(): void
    {
        Loc::loadMessages(__FILE__);
        foreach (['ru', 'en'] as $language) {
            $type = new CEventType();
            $type->Add(['LID' => $language, 'EVENT_NAME' => BitrixMailNotificationTransport::EVENT_NAME,
                'NAME' => Loc::getMessage('KK_PRICEWATCH_MAIL_EVENT_NAME', null, $language) ?: 'KK PriceWatch notification digest',
                'DESCRIPTION' => '#EMAIL_TO# - recipients\n#SCAN_AT# - scan time\n#PROBLEM_COUNT# - problems\n#RECOVERY_COUNT# - recoveries\n#DIGEST_HTML# - safe digest']);
        }
        $existingSites = [];
        $existing = CEventMessage::GetList($by = 'id', $order = 'asc', ['TYPE_ID' => BitrixMailNotificationTransport::EVENT_NAME]);
        while ($row = $existing->Fetch()) foreach ((array) $row['LID'] as $siteId) $existingSites[(string) $siteId] = true;
        foreach ($this->sites() as $siteId => $languageId) {
            if (isset($existingSites[$siteId])) continue;
            $messages = Loc::loadLanguageFile(__FILE__, $languageId);
            (new CEventMessage())->Add(['ACTIVE' => 'Y', 'EVENT_NAME' => BitrixMailNotificationTransport::EVENT_NAME,
                'LID' => [$siteId], 'EMAIL_FROM' => '#DEFAULT_EMAIL_FROM#', 'EMAIL_TO' => '#EMAIL_TO#',
                'SUBJECT' => $messages['KK_PRICEWATCH_MAIL_SUBJECT'],
                'MESSAGE' => $messages['KK_PRICEWATCH_MAIL_BODY'], 'BODY_TYPE' => 'html']);
        }
    }
    public function uninstall(): void
    {
        $query = CEventMessage::GetList($by = 'id', $order = 'asc', ['TYPE_ID' => BitrixMailNotificationTransport::EVENT_NAME]);
        while ($row = $query->Fetch()) CEventMessage::Delete((int) $row['ID']);
        CEventType::Delete(BitrixMailNotificationTransport::EVENT_NAME);
    }
    private function sites(): array
    {
        $sites = []; $query = \CSite::GetList($by = 'sort', $order = 'asc', ['ACTIVE' => 'Y']);
        while ($row = $query->Fetch()) {
            $sites[(string) $row['LID']] = in_array(($row['LANGUAGE_ID'] ?? ''), ['ru', 'en'], true)
                ? (string) $row['LANGUAGE_ID'] : 'en';
        }
        return $sites;
    }
}
