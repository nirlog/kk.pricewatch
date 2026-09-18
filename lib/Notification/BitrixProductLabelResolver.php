<?php
declare(strict_types=1);
namespace KK\PriceWatch\Notification;

use Bitrix\Iblock\ElementTable;
use Bitrix\Main\Loader;

final class BitrixProductLabelResolver implements ProductLabelResolverInterface
{
    public function resolve(array $ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static fn(int $id): bool => $id > 0)));
        if ($ids === [] || !Loader::includeModule('iblock')) return [];
        $result = [];
        $query = ElementTable::getList(['select' => ['ID', 'NAME'], 'filter' => ['@ID' => $ids]]);
        while ($row = $query->fetch()) $result[(int) $row['ID']] = (string) $row['NAME'];
        return $result;
    }
}
