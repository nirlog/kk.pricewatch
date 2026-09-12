<?php

declare(strict_types=1);

namespace KK\PriceWatch\Admin;

use DomainException;
use KK\PriceWatch\Model\CollectionStatus;
use KK\PriceWatch\Model\CompetitorTable;
use KK\PriceWatch\Model\ProductCompetitorTable;
use KK\PriceWatch\Model\ProductUrl;

final class ProductCompetitorLinkService
{
    public const ERROR_INVALID_URL = 'INVALID_URL';
    public const ERROR_COMPETITOR_NOT_FOUND = 'COMPETITOR_NOT_FOUND';
    public const ERROR_DUPLICATE = 'DUPLICATE';
    public const ERROR_SAVE_FAILED = 'SAVE_FAILED';

    public static function isAcceptedHttpUrl(string $url): bool
    {
        if (trim($url) === '' || filter_var($url, FILTER_VALIDATE_URL) === false) {
            return false;
        }

        $parts = parse_url($url);
        return is_array($parts)
            && isset($parts['scheme'], $parts['host'])
            && in_array(strtolower((string) $parts['scheme']), ['http', 'https'], true)
            && (string) $parts['host'] !== '';
    }

    public function save(int $productId, int $competitorId, string $exactUrl, string $active, ?int $id = null): int
    {
        if (ProductContext::find($productId) === null) {
            throw new DomainException('PRODUCT_NOT_FOUND');
        }
        if (!self::isAcceptedHttpUrl($exactUrl)) {
            throw new DomainException(self::ERROR_INVALID_URL);
        }
        if ($competitorId <= 0 || !CompetitorTable::getByPrimary($competitorId)->fetch()) {
            throw new DomainException(self::ERROR_COMPETITOR_NOT_FOUND);
        }

        $current = null;
        if ($id !== null) {
            $current = ProductCompetitorTable::getList([
                'filter' => ['=ID' => $id, '=PRODUCT_ID' => $productId],
                'limit' => 1,
            ])->fetch();
            if (!$current) {
                throw new DomainException('LINK_NOT_FOUND');
            }
        }

        $duplicate = ProductCompetitorTable::getList([
            'select' => ['ID'],
            'filter' => [
                '=PRODUCT_ID' => $productId,
                '=COMPETITOR_ID' => $competitorId,
                '=URL_HASH' => ProductUrl::hash($exactUrl),
                $id === null ? '>ID' : '!=ID' => $id ?? 0,
            ],
            'limit' => 1,
        ])->fetch();
        if ($duplicate) {
            throw new DomainException(self::ERROR_DUPLICATE);
        }

        $fields = [
            'PRODUCT_ID' => $productId,
            'COMPETITOR_ID' => $competitorId,
            'URL' => $exactUrl,
            'ACTIVE' => $active === 'Y' ? 'Y' : 'N',
        ];
        if ($current !== null
            && ((int) $current['COMPETITOR_ID'] !== $competitorId || (string) $current['URL'] !== $exactUrl)
        ) {
            $fields += [
                'CURRENT_PRICE' => null,
                'CURRENCY' => null,
                'STATUS' => CollectionStatus::NEW,
                'ERROR_CODE' => null,
                'ERROR_MESSAGE' => null,
                'LAST_CHECK_AT' => null,
                'LAST_SUCCESS_AT' => null,
            ];
        }

        $result = $id === null
            ? ProductCompetitorTable::add($fields)
            : ProductCompetitorTable::update($id, $fields);
        if (!$result->isSuccess()) {
            // The pre-check provides the friendly duplicate path. Other database
            // failures are deliberately not exposed or mislabeled as duplicates.
            throw new DomainException(self::ERROR_SAVE_FAILED);
        }

        return $id ?? (int) $result->getId();
    }

    public function delete(int $productId, int $id): void
    {
        $row = ProductCompetitorTable::getList([
            'select' => ['ID'], 'filter' => ['=ID' => $id, '=PRODUCT_ID' => $productId], 'limit' => 1,
        ])->fetch();
        if (!$row) {
            throw new DomainException('LINK_NOT_FOUND');
        }
        if (!ProductCompetitorTable::delete($id)->isSuccess()) {
            throw new DomainException(self::ERROR_SAVE_FAILED);
        }
    }
}
