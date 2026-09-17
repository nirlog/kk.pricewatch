<?php
declare(strict_types=1);
namespace KK\PriceWatch\Notification;

final readonly class NotificationTransition
{
    public const PROBLEM_STARTED = 'problem_started';
    public const PROBLEM_CHANGED = 'problem_changed';
    public const RECOVERED = 'recovered';

    /** @param array<string,mixed> $link */
    public function __construct(
        public int $linkId,
        public string $ruleCode,
        public string $type,
        public NotificationRuleState $current,
        public array $link,
    ) {}
}
