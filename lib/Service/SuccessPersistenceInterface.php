<?php

declare(strict_types=1);

namespace KK\PriceWatch\Service;

use Bitrix\Main\Type\DateTime;

interface SuccessPersistenceInterface
{
    public function persist(
        int $linkId,
        CollectedLinkIdentity $collectedIdentity,
        string $price,
        string $currency,
        DateTime $collectedAt,
    ): bool;
}
