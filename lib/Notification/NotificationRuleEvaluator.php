<?php
declare(strict_types=1);
namespace KK\PriceWatch\Notification;

use DateTimeInterface;
use KK\PriceWatch\Service\MonitoringHealth;

final class NotificationRuleEvaluator
{
    public const COLLECTION_ERROR = 'collection_error';
    public const STALE = 'stale';
    public const NO_SUCCESS_PRICE = 'no_success_price';

    /** @param array<string,mixed> $row @return array<string,NotificationRuleState> */
    public function evaluate(array $row, DateTimeInterface $now): array
    {
        $health = MonitoringHealth::describe($row, MonitoringHealth::cutoff($now));
        $error = ($health['is_error'] ?? false) === true;
        $code = trim((string) ($row['ERROR_CODE'] ?? ''));
        return [
            self::COLLECTION_ERROR => new NotificationRuleState($error, $error ? ($code !== '' ? $code : 'UNKNOWN_ERROR') : null),
            self::STALE => new NotificationRuleState((bool) $health['is_stale'], ($health['is_stale'] ?? false) ? self::STALE : null),
            self::NO_SUCCESS_PRICE => new NotificationRuleState(
                !($health['has_successful_value'] ?? false) && ($row['LAST_CHECK_AT'] ?? null) !== null,
                !($health['has_successful_value'] ?? false) && ($row['LAST_CHECK_AT'] ?? null) !== null ? self::NO_SUCCESS_PRICE : null,
            ),
        ];
    }

    public function transition(int $linkId, string $rule, NotificationRuleState $current, ?NotificationRuleState $delivered, array $link): ?NotificationTransition
    {
        $delivered ??= new NotificationRuleState(false);
        if ($current->active && !$delivered->active) $type = NotificationTransition::PROBLEM_STARTED;
        elseif ($current->active && $delivered->active && $current->fingerprint !== $delivered->fingerprint) $type = NotificationTransition::PROBLEM_CHANGED;
        elseif (!$current->active && $delivered->active) $type = NotificationTransition::RECOVERED;
        else return null;
        $safeLink = array_intersect_key($link, array_flip([
            'ID', 'PRODUCT_ID', 'COMPETITOR_ID', 'COMPETITOR_NAME', 'URL', 'CURRENT_PRICE', 'CURRENCY',
            'STATUS', 'ERROR_CODE', 'LAST_CHECK_AT', 'LAST_SUCCESS_AT',
        ]));
        return new NotificationTransition($linkId, $rule, $type, $current, $safeLink);
    }
}
