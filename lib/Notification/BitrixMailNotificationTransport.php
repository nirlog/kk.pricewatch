<?php
declare(strict_types=1);
namespace KK\PriceWatch\Notification;

use Bitrix\Main\SiteTable;
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\Mail\Event;

final class BitrixMailNotificationTransport implements NotificationTransportInterface
{
    public const EVENT_NAME = 'KK_PRICEWATCH_ALERT_DIGEST';

    public function __construct(private readonly MailEventSenderInterface $sender = new BitrixMailEventSender()) {}

    public function send(NotificationDigest $digest, array $recipients, string $siteId): bool
    {
        $site = SiteTable::getList(['select' => ['LID', 'LANGUAGE_ID'], 'filter' => ['=LID' => $siteId, '=ACTIVE' => 'Y'], 'limit' => 1])->fetch();
        if (!$site) return false;
        $languageId = in_array(($site['LANGUAGE_ID'] ?? ''), ['ru', 'en'], true) ? (string) $site['LANGUAGE_ID'] : 'en';
        $messages = Loc::loadLanguageFile(__FILE__, $languageId);
        $fields = ['EMAIL_TO' => implode(', ', $recipients), 'SCAN_AT' => $digest->scannedAt->format('Y-m-d H:i:s'),
            'PROBLEM_COUNT' => (string) $digest->problemCount(), 'RECOVERY_COUNT' => (string) $digest->recoveryCount(),
            'DIGEST_HTML' => $this->render($digest, $messages)];
        $result = $this->sender->send(self::EVENT_NAME, $siteId, $fields, 'N');
        return MailSendResult::isSuccess($result, Event::SEND_RESULT_SUCCESS);
    }

    /** @param array<string,string> $messages */
    private function render(NotificationDigest $digest, array $messages): string
    {
        $e = static fn(mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $m = static fn(string $code): string => $messages[$code] ?? $code;
        $columns = ['PRODUCT', 'COMPETITOR', 'LINK', 'RULE', 'TRANSITION', 'ERROR_CODE', 'PRICE', 'LAST_CHECK', 'LAST_SUCCESS', 'SOURCE'];
        $html = '<table border="1" cellpadding="5"><tr>' . implode('', array_map(static fn(string $column): string => '<th>' . $e($m('KK_PRICEWATCH_DIGEST_' . $column)) . '</th>', $columns)) . '</tr>';
        foreach ($digest->transitions as $transition) {
            $row = $transition->link; $productId = (int) $row['PRODUCT_ID'];
            $label = ($digest->productNames[$productId] ?? '') !== '' ? $digest->productNames[$productId] . ' (#' . $productId . ')' : '#' . $productId;
            $date = static fn(mixed $v): string => $v instanceof \DateTimeInterface ? $v->format('Y-m-d H:i:s') : '—';
            $price = ($row['CURRENT_PRICE'] ?? null) !== null && ($row['CURRENCY'] ?? null) !== null ? $row['CURRENT_PRICE'] . ' ' . $row['CURRENCY'] : '—';
            $error = $transition->ruleCode === NotificationRuleEvaluator::COLLECTION_ERROR && $transition->current->active ? $transition->current->fingerprint : '—';
            $url = (string) ($row['URL'] ?? '');
            $source = \KK\PriceWatch\Model\ProductUrl::isAcceptedHttpUrl($url) ? '<a href="' . $e($url) . '">' . $e($url) . '</a>' : '—';
            $rule = $m('KK_PRICEWATCH_RULE_' . strtoupper($transition->ruleCode));
            $transitionLabel = $m('KK_PRICEWATCH_TRANSITION_' . strtoupper($transition->type));
            $html .= '<tr><td>' . $e($label) . '</td><td>' . $e($row['COMPETITOR_NAME'] ?? ('#' . $row['COMPETITOR_ID'])) . '</td><td>#' . $transition->linkId . '</td><td>' . $e($rule) . '</td><td>' . $e($transitionLabel) . '</td><td>' . $e($error) . '</td><td>' . $e($price) . '</td><td>' . $e($date($row['LAST_CHECK_AT'] ?? null)) . '</td><td>' . $e($date($row['LAST_SUCCESS_AT'] ?? null)) . '</td><td>' . $source . '</td></tr>';
        }
        return $html . '</table>';
    }
}
