<?php
declare(strict_types=1);
namespace KK\PriceWatch\Notification;

interface NotificationLinkRepositoryInterface
{
    public function maximumActiveId(): ?int;
    /** @return list<array<string,mixed>> */
    public function findActiveAfter(int $lastId, int $maximumId, int $limit): array;
}
