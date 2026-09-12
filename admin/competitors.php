<?php

use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;
use KK\PriceWatch\Admin\Access;
use KK\PriceWatch\Model\CollectorType;
use KK\PriceWatch\Model\CompetitorTable;

require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_before.php';

if (!Loader::includeModule('kk.pricewatch') || !Access::canRead()) {
    $APPLICATION->AuthForm(Loc::getMessage('ACCESS_DENIED'));
}

Loc::loadMessages(__FILE__);
$tableId = 'kk_pricewatch_competitors';
$sort = new CAdminSorting($tableId, 'SORT', 'asc');
$list = new CAdminUiList($tableId, $sort);

$sortFields = ['ID', 'ACTIVE', 'SORT', 'NAME', 'DOMAIN', 'COLLECTOR_TYPE', 'COLLECTOR_HANDLER', 'UPDATED_AT'];
$sortField = strtoupper((string) ($by ?? 'SORT'));
if (!in_array($sortField, $sortFields, true)) {
    $sortField = 'SORT';
}
$sortDirection = strtolower((string) ($order ?? 'asc')) === 'desc' ? 'desc' : 'asc';
$request = Bitrix\Main\Context::getCurrent()->getRequest();
$filter = [];
$filterValues = [
    'find_name' => trim((string) $request->get('find_name')),
    'find_domain' => trim((string) $request->get('find_domain')),
    'find_active' => (string) $request->get('find_active'),
    'find_collector_type' => (string) $request->get('find_collector_type'),
];
if ($filterValues['find_name'] !== '') $filter['%NAME'] = $filterValues['find_name'];
if ($filterValues['find_domain'] !== '') $filter['%DOMAIN'] = $filterValues['find_domain'];
if (in_array($filterValues['find_active'], ['Y', 'N'], true)) $filter['=ACTIVE'] = $filterValues['find_active'];
if (CollectorType::isValid($filterValues['find_collector_type'])) $filter['=COLLECTOR_TYPE'] = $filterValues['find_collector_type'];

$ormOrder = [$sortField => strtoupper($sortDirection)];
foreach (['SORT', 'NAME', 'ID'] as $tieBreaker) {
    if (!isset($ormOrder[$tieBreaker])) $ormOrder[$tieBreaker] = 'ASC';
}
$navigation = $list->getPageNavigation('nav-kk-pricewatch-competitors');
$navigation->allowAllRecords(false);
$navigation->setRecordCount(CompetitorTable::getCount($filter));

$query = CompetitorTable::getList([
    'select' => $sortFields,
    'filter' => $filter,
    'order' => $ormOrder,
    'limit' => $navigation->getLimit(),
    'offset' => $navigation->getOffset(),
]);
$data = new CAdminResult($query, $tableId);
$list->setNavigation($navigation, Loc::getMessage('KK_PRICEWATCH_LIST_NAV'), false);
$list->AddHeaders([
    ['id' => 'ID', 'content' => 'ID', 'sort' => 'ID', 'default' => true],
    ['id' => 'ACTIVE', 'content' => Loc::getMessage('KK_PRICEWATCH_FIELD_ACTIVE'), 'sort' => 'ACTIVE', 'default' => true],
    ['id' => 'SORT', 'content' => Loc::getMessage('KK_PRICEWATCH_FIELD_SORT'), 'sort' => 'SORT', 'default' => true],
    ['id' => 'NAME', 'content' => Loc::getMessage('KK_PRICEWATCH_FIELD_NAME'), 'sort' => 'NAME', 'default' => true],
    ['id' => 'DOMAIN', 'content' => Loc::getMessage('KK_PRICEWATCH_FIELD_DOMAIN'), 'sort' => 'DOMAIN', 'default' => true],
    ['id' => 'COLLECTOR_TYPE', 'content' => Loc::getMessage('KK_PRICEWATCH_FIELD_TYPE'), 'sort' => 'COLLECTOR_TYPE', 'default' => true],
    ['id' => 'COLLECTOR_HANDLER', 'content' => Loc::getMessage('KK_PRICEWATCH_FIELD_HANDLER'), 'sort' => 'COLLECTOR_HANDLER', 'default' => true],
    ['id' => 'UPDATED_AT', 'content' => Loc::getMessage('KK_PRICEWATCH_FIELD_UPDATED'), 'sort' => 'UPDATED_AT', 'default' => true],
]);
while ($item = $data->Fetch()) {
    $id = (int) $item['ID'];
    $url = 'kk_pricewatch_competitor_edit.php?lang=' . LANGUAGE_ID . '&ID=' . $id;
    $row = &$list->AddRow($id, $item, $url, Loc::getMessage('KK_PRICEWATCH_LIST_OPEN'));
    $row->AddViewField('ID', '<a href="' . htmlspecialcharsbx($url) . '">' . $id . '</a>');
    $row->AddViewField('NAME', '<a href="' . htmlspecialcharsbx($url) . '">' . htmlspecialcharsbx((string) $item['NAME']) . '</a>');
    foreach (['ACTIVE', 'DOMAIN', 'COLLECTOR_TYPE', 'COLLECTOR_HANDLER', 'UPDATED_AT'] as $field) {
        $row->AddViewField($field, htmlspecialcharsbx((string) $item[$field]));
    }
}
$list->AddAdminContextMenu(Access::canWrite() ? [[
    'TEXT' => Loc::getMessage('KK_PRICEWATCH_LIST_ADD'),
    'LINK' => 'kk_pricewatch_competitor_edit.php?lang=' . LANGUAGE_ID,
    'ICON' => 'btn_new',
]] : []);
$list->CheckListMode();

$APPLICATION->SetTitle(Loc::getMessage('KK_PRICEWATCH_LIST_TITLE'));
require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_after.php';
$filterUi = new CAdminFilter($tableId . '_filter', [Loc::getMessage('KK_PRICEWATCH_FIELD_NAME'), Loc::getMessage('KK_PRICEWATCH_FIELD_DOMAIN')]);
?>
<form name="find_form" method="get" action="<?= htmlspecialcharsbx($APPLICATION->GetCurPage()) ?>">
<?php $filterUi->Begin(); ?>
<tr><td><?= Loc::getMessage('KK_PRICEWATCH_FIELD_NAME') ?>:</td><td><input type="text" name="find_name" value="<?= htmlspecialcharsbx($filterValues['find_name']) ?>"></td></tr>
<tr><td><?= Loc::getMessage('KK_PRICEWATCH_FIELD_DOMAIN') ?>:</td><td><input type="text" name="find_domain" value="<?= htmlspecialcharsbx($filterValues['find_domain']) ?>"></td></tr>
<tr><td><?= Loc::getMessage('KK_PRICEWATCH_FIELD_ACTIVE') ?>:</td><td><select name="find_active"><option value=""></option><option value="Y"<?= $filterValues['find_active'] === 'Y' ? ' selected' : '' ?>>Y</option><option value="N"<?= $filterValues['find_active'] === 'N' ? ' selected' : '' ?>>N</option></select></td></tr>
<tr><td><?= Loc::getMessage('KK_PRICEWATCH_FIELD_TYPE') ?>:</td><td><select name="find_collector_type"><option value=""></option><option value="mock"<?= $filterValues['find_collector_type'] === 'mock' ? ' selected' : '' ?>>mock</option><option value="external"<?= $filterValues['find_collector_type'] === 'external' ? ' selected' : '' ?>>external</option></select></td></tr>
<?php $filterUi->Buttons(['table_id' => $tableId, 'url' => $APPLICATION->GetCurPage(), 'form' => 'find_form']); $filterUi->End(); ?>
</form>
<?php $list->DisplayList(); require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_admin.php';
