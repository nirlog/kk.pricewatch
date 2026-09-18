<?php
declare(strict_types=1);
namespace KK\PriceWatch\Notification;

final class NotificationSettingsValidator
{
    public const RECIPIENTS_REQUIRED = 'recipients_required';
    public const SITE_REQUIRED = 'site_required';
    public const SITE_INACTIVE = 'site_inactive';
    public const TEMPLATE_MISSING = 'template_missing';

    /** @param list<string> $recipients @return list<string> */
    public function validate(bool $enabled, array $recipients, string $siteId, bool $siteActive, bool $templateExists): array
    {
        if (!$enabled) return [];

        $errors = [];
        if ($recipients === []) $errors[] = self::RECIPIENTS_REQUIRED;
        if ($siteId === '') {
            $errors[] = self::SITE_REQUIRED;
        } elseif (!$siteActive) {
            $errors[] = self::SITE_INACTIVE;
        } elseif (!$templateExists) {
            $errors[] = self::TEMPLATE_MISSING;
        }
        return $errors;
    }
}
