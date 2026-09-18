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
        $sites = $this->sites();
        $templates = [];
        foreach (['ru', 'en'] as $languageId) {
            $templates[$languageId] = $this->localizedTemplate($languageId);
        }

        Loc::loadMessages(__FILE__);
        foreach (['ru', 'en'] as $language) {
            $type = new CEventType();
            $type->Add(['LID' => $language, 'EVENT_NAME' => BitrixMailNotificationTransport::EVENT_NAME,
                'NAME' => Loc::getMessage('KK_PRICEWATCH_MAIL_EVENT_NAME', null, $language) ?: 'KK PriceWatch notification digest',
                'DESCRIPTION' => '#EMAIL_TO# - recipients\n#SCAN_AT# - scan time\n#PROBLEM_COUNT# - problems\n#RECOVERY_COUNT# - recoveries\n#DIGEST_HTML# - safe digest']);
        }

        /** @var array<string, array<int, array<string, mixed>>> $messagesBySite */
        $messagesBySite = [];
        $existing = CEventMessage::GetList($by = 'id', $order = 'asc', ['TYPE_ID' => BitrixMailNotificationTransport::EVENT_NAME]);
        while ($row = $existing->Fetch()) {
            foreach ((array) $row['LID'] as $siteId) {
                $messagesBySite[(string) $siteId][] = $row;
            }
        }

        $repairedMessageIds = [];
        foreach ($sites as $siteId => $languageId) {
            $localized = $templates[$languageId];
            if (!isset($messagesBySite[$siteId])) {
                (new CEventMessage())->Add(['ACTIVE' => 'Y', 'EVENT_NAME' => BitrixMailNotificationTransport::EVENT_NAME,
                    'LID' => [$siteId], 'EMAIL_FROM' => '#DEFAULT_EMAIL_FROM#', 'EMAIL_TO' => '#EMAIL_TO#',
                    'SUBJECT' => $localized['KK_PRICEWATCH_MAIL_SUBJECT'],
                    'MESSAGE' => $localized['KK_PRICEWATCH_MAIL_BODY'], 'BODY_TYPE' => 'html']);
                continue;
            }

            foreach ($messagesBySite[$siteId] as $message) {
                $messageId = (int) $message['ID'];
                if (isset($repairedMessageIds[$messageId])) {
                    continue;
                }
                $repairedMessageIds[$messageId] = true;
                $fields = [];
                if (!is_string($message['SUBJECT'] ?? null) || trim($message['SUBJECT']) === '') {
                    $fields['SUBJECT'] = $localized['KK_PRICEWATCH_MAIL_SUBJECT'];
                }
                if (!is_string($message['MESSAGE'] ?? null) || trim($message['MESSAGE']) === '') {
                    $fields['MESSAGE'] = $localized['KK_PRICEWATCH_MAIL_BODY'];
                }
                if ($fields !== []) {
                    (new CEventMessage())->Update($messageId, $fields);
                }
            }
        }
    }

    /** @return array{KK_PRICEWATCH_MAIL_SUBJECT: string, KK_PRICEWATCH_MAIL_BODY: string} */
    private function localizedTemplate(string $languageId): array
    {
        $messages = Loc::loadLanguageFile(__FILE__, $languageId);
        foreach (['KK_PRICEWATCH_MAIL_SUBJECT', 'KK_PRICEWATCH_MAIL_BODY'] as $code) {
            if (!isset($messages[$code]) || !is_string($messages[$code]) || trim($messages[$code]) === '') {
                throw new \RuntimeException(sprintf(
                    'Notification mail localization "%s" is missing or empty for language "%s".',
                    $code,
                    $languageId
                ));
            }
        }

        return [
            'KK_PRICEWATCH_MAIL_SUBJECT' => $messages['KK_PRICEWATCH_MAIL_SUBJECT'],
            'KK_PRICEWATCH_MAIL_BODY' => $messages['KK_PRICEWATCH_MAIL_BODY'],
        ];
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
