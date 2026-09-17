<?php
declare(strict_types=1);
namespace KK\PriceWatch\Notification;

use DateTimeInterface;

interface NotificationStateRepositoryInterface
{
    /** @param list<int> $linkIds @return array<int,array<string,NotificationRuleState>> */
    public function findForLinks(array $linkIds): array;
    /** @param list<NotificationTransition> $transitions */
    public function saveDelivered(array $transitions, DateTimeInterface $at): void;
}
