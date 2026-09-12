<?php

use Bitrix\Main\Context;
use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;
use KK\PriceWatch\Admin\Access;
use KK\PriceWatch\Admin\ProductCompetitorLinkService;
use KK\PriceWatch\Admin\ProductContext;
use KK\PriceWatch\Model\ProductCompetitorTable;

require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_before.php';
if (!Loader::includeModule('kk.pricewatch') || !Access::canRead()) {
    $APPLICATION->AuthForm(Loc::getMessage('ACCESS_DENIED'));
}
Loc::loadMessages(__FILE__);
$productId = (int) Context::getCurrent()->getRequest()->get('PRODUCT_ID');
$product = ProductContext::findCatalogProduct($productId);
if ($product === null) {
    $APPLICATION->AuthForm(Loc::getMessage('KK_PRICEWATCH_PRODUCT_LIST_INVALID_PRODUCT'));
}

$tableId = 'kk_pricewatch_product_competitors';
$sort = new CAdminSorting($tableId, 'ID', 'asc');
$list = new CAdminUiList($tableId, $sort);
$sortFields = ['ID', 'ACTIVE', 'COMPETITOR_ID', 'CURRENT_PRICE', 'CURRENCY', 'STATUS', 'LAST_CHECK_AT', 'LAST_SUCCESS_AT', 'UPDATED_AT'];
$sortField = strtoupper((string) ($by ?? 'ID'));
if (!in_array($sortField, $sortFields, true)) $sortField = 'ID';
$sortDirection = strtolower((string) ($order ?? 'asc')) === 'desc' ? 'DESC' : 'ASC';
$orderFields = [$sortField => $sortDirection];
if ($sortField !== 'ID') $orderFields['ID'] = 'ASC';
$filter = ['=PRODUCT_ID' => $productId];
$navigation = $list->getPageNavigation('nav-kk-pricewatch-product-competitors');
$navigation->allowAllRecords(false);
$navigation->setRecordCount(ProductCompetitorTable::getCount($filter));
$query = ProductCompetitorTable::getList([
    'select' => array_merge($sortFields, ['URL', 'COMPETITOR_NAME' => 'COMPETITOR.NAME']),
    'filter' => $filter,
    'order' => $orderFields,
    'limit' => $navigation->getLimit(),
    'offset' => $navigation->getOffset(),
]);
$data = new CAdminResult($query, $tableId);
$list->setNavigation($navigation, Loc::getMessage('KK_PRICEWATCH_PRODUCT_LIST_NAV'), false);
$headers = [
    ['id' => 'ID', 'content' => 'ID', 'sort' => 'ID', 'default' => true],
    ['id' => 'COMPETITOR_NAME', 'content' => Loc::getMessage('KK_PRICEWATCH_PRODUCT_LIST_COMPETITOR'), 'default' => true],
    ['id' => 'ACTIVE', 'content' => Loc::getMessage('KK_PRICEWATCH_PRODUCT_LIST_ACTIVE'), 'sort' => 'ACTIVE', 'default' => true],
    ['id' => 'URL', 'content' => 'URL', 'default' => true],
    ['id' => 'CURRENT_PRICE', 'content' => Loc::getMessage('KK_PRICEWATCH_PRODUCT_LIST_PRICE'), 'sort' => 'CURRENT_PRICE', 'default' => true],
    ['id' => 'CURRENCY', 'content' => Loc::getMessage('KK_PRICEWATCH_PRODUCT_LIST_CURRENCY'), 'sort' => 'CURRENCY', 'default' => true],
    ['id' => 'STATUS', 'content' => Loc::getMessage('KK_PRICEWATCH_PRODUCT_LIST_STATUS'), 'sort' => 'STATUS', 'default' => true],
    ['id' => 'LAST_CHECK_AT', 'content' => Loc::getMessage('KK_PRICEWATCH_PRODUCT_LIST_LAST_CHECK'), 'sort' => 'LAST_CHECK_AT', 'default' => true],
    ['id' => 'LAST_SUCCESS_AT', 'content' => Loc::getMessage('KK_PRICEWATCH_PRODUCT_LIST_LAST_SUCCESS'), 'sort' => 'LAST_SUCCESS_AT', 'default' => true],
    ['id' => 'UPDATED_AT', 'content' => Loc::getMessage('KK_PRICEWATCH_PRODUCT_LIST_UPDATED'), 'sort' => 'UPDATED_AT', 'default' => true],
];
$list->AddHeaders($headers);
while ($item = $data->Fetch()) {
    $id = (int) $item['ID'];
    $editUrl = 'kk_pricewatch_product_competitor_edit.php?lang=' . LANGUAGE_ID . '&PRODUCT_ID=' . $productId . '&ID=' . $id;
    $row = &$list->AddRow($id, $item, $editUrl, Loc::getMessage('KK_PRICEWATCH_PRODUCT_LIST_OPEN'));
    $row->AddViewField('ID', '<a href="' . htmlspecialcharsbx($editUrl) . '">' . $id . '</a>');
    $row->AddViewField('COMPETITOR_NAME', htmlspecialcharsbx((string) $item['COMPETITOR_NAME']));
    $url = (string) $item['URL'];
    $safeUrl = htmlspecialcharsbx($url);
    $row->AddViewField('URL', ProductCompetitorLinkService::isAcceptedHttpUrl($url)
        ? '<a href="' . $safeUrl . '" target="_blank" rel="noopener noreferrer">' . $safeUrl . '</a>' : $safeUrl);
    foreach (['ACTIVE', 'CURRENT_PRICE', 'CURRENCY', 'STATUS', 'LAST_CHECK_AT', 'LAST_SUCCESS_AT', 'UPDATED_AT'] as $field) {
        $row->AddViewField($field, htmlspecialcharsbx((string) $item[$field]));
    }
}
$menu = [];
if (Access::canWrite()) {
    $menu[] = ['TEXT' => Loc::getMessage('KK_PRICEWATCH_PRODUCT_LIST_ADD'), 'LINK' => 'kk_pricewatch_product_competitor_edit.php?lang=' . LANGUAGE_ID . '&PRODUCT_ID=' . $productId, 'ICON' => 'btn_new'];
}
$list->AddAdminContextMenu($menu);
$list->CheckListMode();
$APPLICATION->SetTitle(Loc::getMessage('KK_PRICEWATCH_PRODUCT_LIST_TITLE', ['#NAME#' => $product['NAME'], '#ID#' => $productId]));
require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_after.php';
?><p><?= htmlspecialcharsbx((string) Loc::getMessage('KK_PRICEWATCH_PRODUCT_LIST_CONTEXT', ['#NAME#' => $product['NAME'], '#ID#' => $productId])) ?></p><?php
$list->DisplayList();
require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_admin.php';
