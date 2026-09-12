<?php

declare(strict_types=1);

namespace KK\PriceWatch\Admin;

use Bitrix\Catalog\ProductTable;
use Bitrix\Iblock\ElementTable;
use Bitrix\Main\Loader;

final class ProductContext
{
    /** @return array{ID:int, NAME:string}|null */
    public static function find(int $productId): ?array
    {
        if ($productId <= 0 || !Loader::includeModule('iblock')) {
            return null;
        }

        $row = ElementTable::getList([
            'select' => ['ID', 'NAME'],
            'filter' => ['=ID' => $productId],
            'limit' => 1,
        ])->fetch();

        return $row ? ['ID' => (int) $row['ID'], 'NAME' => (string) $row['NAME']] : null;
    }

    /** @return array{ID:int, NAME:string}|null */
    public static function findCatalogProduct(int $productId): ?array
    {
        $product = self::find($productId);
        if ($product === null || !Loader::includeModule('catalog')) {
            return null;
        }

        return ProductTable::getByPrimary($productId, ['select' => ['ID']])->fetch() ? $product : null;
    }
}
