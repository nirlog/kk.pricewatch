<?php

declare(strict_types=1);

namespace KK\PriceWatch\Notification;

final class NotificationAgentIntervalValidator
{
    public const MIN_MINUTES = 5;
    public const MAX_MINUTES = 1440;

    public function validate(mixed $value): ?int
    {
        if (!is_int($value) && (!is_string($value) || preg_match('/^\d+$/D', $value) !== 1)) {
            return null;
        }
        $minutes = (int) $value;
        return $minutes >= self::MIN_MINUTES && $minutes <= self::MAX_MINUTES ? $minutes : null;
    }
}
