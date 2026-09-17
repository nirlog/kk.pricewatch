<?php
declare(strict_types=1);
namespace KK\PriceWatch\Notification;

use DateTimeImmutable;

final readonly class NotificationDigest
{
    /** @param list<NotificationTransition> $transitions @param array<int,string> $productNames */
    public function __construct(public DateTimeImmutable $scannedAt, public array $transitions, public array $productNames) {}

    public function problemCount(): int { return count(array_filter($this->transitions, static fn($t) => $t->type !== NotificationTransition::RECOVERED)); }
    public function recoveryCount(): int { return count($this->transitions) - $this->problemCount(); }
}
