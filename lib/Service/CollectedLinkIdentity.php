<?php

declare(strict_types=1);

namespace KK\PriceWatch\Service;

final readonly class CollectedLinkIdentity
{
    public function __construct(
        public int $productId,
        public int $competitorId,
        public string $url,
        public string $urlHash,
    ) {
    }

    /** @param array<string, mixed> $row */
    public static function fromRow(array $row): self
    {
        return new self((int) $row['PRODUCT_ID'], (int) $row['COMPETITOR_ID'], (string) $row['URL'], (string) $row['URL_HASH']);
    }

    /** @param array<string, mixed> $row */
    public function matchesRow(array $row): bool
    {
        return $this->productId === (int) $row['PRODUCT_ID']
            && $this->competitorId === (int) $row['COMPETITOR_ID']
            && $this->url === (string) $row['URL']
            && $this->urlHash === (string) $row['URL_HASH'];
    }
}
