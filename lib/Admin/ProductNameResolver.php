<?php

declare(strict_types=1);

namespace KK\PriceWatch\Admin;

use Bitrix\Iblock\ElementTable;
use Bitrix\Main\Loader;

final class ProductNameResolver
{
    /** @param list<int> $productIds @return array<int,string> */
    public function resolve(array $productIds): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $productIds), static fn(int $id): bool => $id > 0)));
        if ($ids === [] || !Loader::includeModule('iblock')) return [];
        $names = [];
        $query = ElementTable::getList(['select' => ['ID', 'NAME'], 'filter' => ['@ID' => $ids]]);
        while ($row = $query->fetch()) $names[(int) $row['ID']] = (string) $row['NAME'];
        return $names;
    }
}
