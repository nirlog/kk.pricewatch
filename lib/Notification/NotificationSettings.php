<?php
declare(strict_types=1);
namespace KK\PriceWatch\Notification;

final readonly class NotificationSettings
{
    /** @param list<string> $recipients */
    public function __construct(public bool $enabled, public array $recipients, public string $siteId, public bool $sendRecovery) {}

    /** @return list<string> */
    public static function normalizeEmails(string $value): array
    {
        $result = [];
        foreach (preg_split('/[,;\s]+/', trim($value), -1, PREG_SPLIT_NO_EMPTY) ?: [] as $email) {
            $email = strtolower(trim($email));
            if (filter_var($email, FILTER_VALIDATE_EMAIL) !== false) $result[$email] = $email;
        }
        return array_values($result);
    }
}
