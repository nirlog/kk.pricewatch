<?php

use Bitrix\Main\Context;
use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;
use KK\PriceWatch\Admin\Access;
use KK\PriceWatch\Admin\ProductContext;
use KK\PriceWatch\Model\PriceHistoryTable;

require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_before.php';
if (!Loader::includeModule('kk.pricewatch') || !Access::canRead()) {
    $APPLICATION->AuthForm(Loc::getMessage('ACCESS_DENIED'));
}
Loc::loadMessages(__FILE__);
$request = Context::getCurrent()->getRequest();
$productId = max(0, (int) $request->get('PRODUCT_ID'));
$linkId = max(0, (int) $request->get('PRODUCT_COMPETITOR_ID'));
$product = $productId > 0 ? ProductContext::findCatalogProduct($productId) : null;
if ($productId > 0 && $product === null) {
    $APPLICATION->AuthForm(Loc::getMessage('KK_PRICEWATCH_HISTORY_INVALID_PRODUCT'));
}

$tableId = 'kk_pricewatch_price_history';
$sort = new CAdminSorting($tableId, 'COLLECTED_AT', 'desc');
$list = new CAdminUiList($tableId, $sort);
$filter = [];
if ($productId > 0) $filter['=PRODUCT_ID'] = $productId;
if ($linkId > 0) $filter['=PRODUCT_COMPETITOR_ID'] = $linkId;
$navigation = $list->getPageNavigation('nav-kk-pricewatch-price-history');
$navigation->allowAllRecords(false);
$navigation->setRecordCount(PriceHistoryTable::getCount($filter));
$query = PriceHistoryTable::getList([
    'select' => ['ID', 'COLLECTED_AT', 'PRODUCT_COMPETITOR_ID', 'COMPETITOR_ID', 'COMPETITOR_NAME' => 'COMPETITOR.NAME', 'URL', 'PRICE', 'CURRENCY'],
    'runtime' => [
        new \Bitrix\Main\ORM\Fields\Relations\Reference(
            'COMPETITOR',
            \KK\PriceWatch\Model\CompetitorTable::class,
            \Bitrix\Main\ORM\Query\Join::on('this.COMPETITOR_ID', 'ref.ID')
        ),
    ],
    'filter' => $filter,
    'order' => ['COLLECTED_AT' => 'DESC', 'ID' => 'DESC'],
    'limit' => $navigation->getLimit(),
    'offset' => $navigation->getOffset(),
]);
$data = new CAdminResult($query, $tableId);
$list->setNavigation($navigation, Loc::getMessage('KK_PRICEWATCH_HISTORY_NAV'), false);
$list->AddHeaders([
    ['id' => 'COLLECTED_AT', 'content' => Loc::getMessage('KK_PRICEWATCH_HISTORY_DATE'), 'default' => true],
    ['id' => 'PRODUCT_COMPETITOR_ID', 'content' => Loc::getMessage('KK_PRICEWATCH_HISTORY_LINK'), 'default' => true],
    ['id' => 'COMPETITOR_ID', 'content' => Loc::getMessage('KK_PRICEWATCH_HISTORY_COMPETITOR_ID'), 'default' => true],
    ['id' => 'COMPETITOR_NAME', 'content' => Loc::getMessage('KK_PRICEWATCH_HISTORY_COMPETITOR'), 'default' => true],
    ['id' => 'URL', 'content' => 'URL', 'default' => true],
    ['id' => 'PRICE', 'content' => Loc::getMessage('KK_PRICEWATCH_HISTORY_PRICE'), 'default' => true],
    ['id' => 'CURRENCY', 'content' => Loc::getMessage('KK_PRICEWATCH_HISTORY_CURRENCY'), 'default' => true],
]);
while ($item = $data->Fetch()) {
    $row = &$list->AddRow((int) $item['ID'], $item);
    foreach (['COLLECTED_AT', 'PRODUCT_COMPETITOR_ID', 'COMPETITOR_ID', 'COMPETITOR_NAME', 'URL', 'PRICE', 'CURRENCY'] as $field) {
        $row->AddViewField($field, htmlspecialcharsbx((string) $item[$field]));
    }
}
$backUrl = $productId > 0
    ? 'kk_pricewatch_product_competitors.php?lang=' . LANGUAGE_ID . '&PRODUCT_ID=' . $productId
    : 'kk_pricewatch_competitors.php?lang=' . LANGUAGE_ID;
$list->AddAdminContextMenu([['TEXT' => Loc::getMessage('KK_PRICEWATCH_HISTORY_BACK'), 'LINK' => $backUrl]]);
$list->CheckListMode();
$APPLICATION->SetTitle(Loc::getMessage('KK_PRICEWATCH_HISTORY_TITLE'));
require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_after.php';
$list->DisplayList();
require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_admin.php';
