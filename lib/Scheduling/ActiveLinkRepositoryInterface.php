<?php

declare(strict_types=1);

namespace KK\PriceWatch\Scheduling;

interface ActiveLinkRepositoryInterface
{
    public function maximumActiveId(): ?int;

    /** @return list<int> */
    public function findActiveIdsAfter(int $lastId, int $maximumId, int $limit): array;
}
