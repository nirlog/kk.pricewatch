<?php
declare(strict_types=1);
namespace KK\PriceWatch\Notification;

use Bitrix\Main\SiteTable;
use CEvent;

final class BitrixMailNotificationTransport implements NotificationTransportInterface
{
    public const EVENT_NAME = 'KK_PRICEWATCH_ALERT_DIGEST';

    public function send(NotificationDigest $digest, array $recipients, string $siteId): bool
    {
        $site = SiteTable::getList(['select' => ['LID'], 'filter' => ['=LID' => $siteId, '=ACTIVE' => 'Y'], 'limit' => 1])->fetch();
        if (!$site) return false;
        $fields = ['EMAIL_TO' => implode(', ', $recipients), 'SCAN_AT' => $digest->scannedAt->format('Y-m-d H:i:s'),
            'PROBLEM_COUNT' => (string) $digest->problemCount(), 'RECOVERY_COUNT' => (string) $digest->recoveryCount(),
            'DIGEST_HTML' => $this->render($digest)];
        return (int) CEvent::SendImmediate(self::EVENT_NAME, $siteId, $fields, 'Y') > 0;
    }

    private function render(NotificationDigest $digest): string
    {
        $e = static fn(mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $html = '<table border="1" cellpadding="5"><tr><th>Product</th><th>Competitor</th><th>Link</th><th>Rule</th><th>Transition</th><th>Error code</th><th>Price</th><th>Last check</th><th>Last success</th><th>Source</th></tr>';
        foreach ($digest->transitions as $transition) {
            $row = $transition->link; $productId = (int) $row['PRODUCT_ID'];
            $label = ($digest->productNames[$productId] ?? '') !== '' ? $digest->productNames[$productId] . ' (#' . $productId . ')' : '#' . $productId;
            $date = static fn(mixed $v): string => $v instanceof \DateTimeInterface ? $v->format('Y-m-d H:i:s') : '—';
            $price = ($row['CURRENT_PRICE'] ?? null) !== null && ($row['CURRENCY'] ?? null) !== null ? $row['CURRENT_PRICE'] . ' ' . $row['CURRENCY'] : '—';
            $error = $transition->ruleCode === NotificationRuleEvaluator::COLLECTION_ERROR && $transition->current->active ? $transition->current->fingerprint : '—';
            $url = (string) ($row['URL'] ?? '');
            $source = \KK\PriceWatch\Model\ProductUrl::isAcceptedHttpUrl($url) ? '<a href="' . $e($url) . '">' . $e($url) . '</a>' : '—';
            $html .= '<tr><td>' . $e($label) . '</td><td>' . $e($row['COMPETITOR_NAME'] ?? ('#' . $row['COMPETITOR_ID'])) . '</td><td>#' . $transition->linkId . '</td><td>' . $e($transition->ruleCode) . '</td><td>' . $e($transition->type) . '</td><td>' . $e($error) . '</td><td>' . $e($price) . '</td><td>' . $e($date($row['LAST_CHECK_AT'] ?? null)) . '</td><td>' . $e($date($row['LAST_SUCCESS_AT'] ?? null)) . '</td><td>' . $source . '</td></tr>';
        }
        return $html . '</table>';
    }
}
