<?php

use Bitrix\Main\Context;
use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;
use KK\PriceWatch\Admin\Access;
use KK\PriceWatch\Admin\ProductNameResolver;
use KK\PriceWatch\Model\CollectionStatus;
use KK\PriceWatch\Model\CompetitorTable;
use KK\PriceWatch\Model\ProductUrl;
use KK\PriceWatch\Service\MonitoringDashboardService;
use KK\PriceWatch\Service\MonitoringHealth;
use KK\PriceWatch\Service\OrmMonitoringDashboardRepository;

require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_before.php';
if (!Loader::includeModule('kk.pricewatch') || !Access::canRead()) {
    $APPLICATION->AuthForm(Loc::getMessage('ACCESS_DENIED'));
}
Loc::loadMessages(__FILE__);

$tableId = 'kk_pricewatch_monitoring';
$sort = new CAdminSorting($tableId, 'LAST_CHECK_AT', 'desc');
$list = new CAdminUiList($tableId, $sort);
$request = Context::getCurrent()->getRequest();
$service = new MonitoringDashboardService(new OrmMonitoringDashboardRepository());
$filterInput = [
    'product_id' => $request->get('find_product_id'),
    'competitor_id' => $request->get('find_competitor_id'),
    'status' => $request->get('find_status'),
    'health' => $request->get('find_health'),
];
$filters = $service->normalizeFilters($filterInput);
$sortField = (string) ($by ?? 'LAST_CHECK_AT');
$sortDirection = (string) ($order ?? 'desc');
$ormOrder = $service->order($sortField, $sortDirection);
$navigation = $list->getPageNavigation('nav-kk-pricewatch-monitoring');
$navigation->allowAllRecords(false);
$dashboard = null;
$competitors = [];
$productNames = [];
$readFailed = false;
try {
    $dashboard = $service->load($filters, $ormOrder, $navigation->getLimit(), $navigation->getOffset());
    $navigation->setRecordCount($dashboard->totalRows);
    $competitorQuery = CompetitorTable::getList([
        'select' => ['ID', 'NAME'], 'filter' => ['=ACTIVE' => 'Y'], 'order' => ['SORT' => 'ASC', 'NAME' => 'ASC', 'ID' => 'ASC'],
    ]);
    while ($competitor = $competitorQuery->fetch()) $competitors[(int) $competitor['ID']] = (string) $competitor['NAME'];
    $productNames = (new ProductNameResolver())->resolve(array_map(static fn(array $row): int => (int) $row['PRODUCT_ID'], $dashboard->rows));
} catch (Throwable) {
    $readFailed = true;
}

$list->setNavigation($navigation, Loc::getMessage('KK_PRICEWATCH_MONITORING_NAV'), false);
$list->AddHeaders([
    ['id' => 'ID', 'content' => Loc::getMessage('KK_PRICEWATCH_MONITORING_LINK_ID'), 'sort' => 'ID', 'default' => true],
    ['id' => 'PRODUCT_ID', 'content' => Loc::getMessage('KK_PRICEWATCH_MONITORING_PRODUCT'), 'sort' => 'PRODUCT_ID', 'default' => true],
    ['id' => 'COMPETITOR_ID', 'content' => Loc::getMessage('KK_PRICEWATCH_MONITORING_COMPETITOR'), 'sort' => 'COMPETITOR_ID', 'default' => true],
    ['id' => 'CURRENT_PRICE', 'content' => Loc::getMessage('KK_PRICEWATCH_MONITORING_PRICE'), 'sort' => 'CURRENT_PRICE', 'default' => true],
    ['id' => 'STATUS', 'content' => Loc::getMessage('KK_PRICEWATCH_MONITORING_STATUS'), 'sort' => 'STATUS', 'default' => true],
    ['id' => 'HEALTH', 'content' => Loc::getMessage('KK_PRICEWATCH_MONITORING_HEALTH'), 'default' => true],
    ['id' => 'LAST_CHECK_AT', 'content' => Loc::getMessage('KK_PRICEWATCH_MONITORING_LAST_CHECK'), 'sort' => 'LAST_CHECK_AT', 'default' => true],
    ['id' => 'LAST_SUCCESS_AT', 'content' => Loc::getMessage('KK_PRICEWATCH_MONITORING_LAST_SUCCESS'), 'sort' => 'LAST_SUCCESS_AT', 'default' => true],
    ['id' => 'ERROR_CODE', 'content' => Loc::getMessage('KK_PRICEWATCH_MONITORING_ERROR_CODE'), 'default' => true],
    ['id' => 'SOURCE', 'content' => Loc::getMessage('KK_PRICEWATCH_MONITORING_SOURCE'), 'default' => true],
]);

$safe = static fn(mixed $value): string => htmlspecialcharsbx((string) $value);
$money = static function (string $value, string $currency) use ($safe): string {
    [$whole, $fraction] = array_pad(explode('.', $value, 2), 2, '');
    $whole = preg_replace('/\B(?=(\d{3})+(?!\d))/', ' ', $whole) ?? $whole;
    return $safe($whole . ($fraction !== '' && $fraction !== '00' ? '.' . $fraction : '') . ' ' . $currency);
};
if (!$readFailed && $dashboard !== null) {
    foreach ($dashboard->rows as $item) {
        $id = (int) $item['ID'];
        $productId = (int) $item['PRODUCT_ID'];
        $editUrl = 'kk_pricewatch_product_competitor_edit.php?lang=' . LANGUAGE_ID . '&ID=' . $id . '&PRODUCT_ID=' . $productId;
        $row = &$list->AddRow($id, $item, $editUrl, Loc::getMessage('KK_PRICEWATCH_MONITORING_ACTION_OPEN'));
        $productLabel = ($productNames[$productId] ?? '') !== '' ? $productNames[$productId] . ' (#' . $productId . ')' : '#' . $productId;
        $row->AddViewField('ID', '<a href="' . $safe($editUrl) . '">#' . $id . '</a>');
        $row->AddViewField('PRODUCT_ID', $safe($productLabel));
        $row->AddViewField('COMPETITOR_ID', $safe($item['COMPETITOR_NAME']));
        $row->AddViewField('CURRENT_PRICE', $item['has_successful_value']
            ? $money((string) $item['CURRENT_PRICE'], (string) $item['CURRENCY'])
            : $safe(Loc::getMessage('KK_PRICEWATCH_MONITORING_HEALTH_NO_PRICE')));
        $row->AddViewField('STATUS', $safe(Loc::getMessage('KK_PRICEWATCH_MONITORING_STATUS_' . strtoupper((string) $item['STATUS']))));
        $healthKey = !$item['has_successful_value'] ? 'NO_PRICE' : ($item['is_stale'] ? 'STALE' : ($item['is_healthy'] ? 'HEALTHY' : 'ERROR'));
        $row->AddViewField('HEALTH', $safe(Loc::getMessage('KK_PRICEWATCH_MONITORING_HEALTH_' . $healthKey)));
        foreach (['LAST_CHECK_AT', 'LAST_SUCCESS_AT', 'ERROR_CODE'] as $field) $row->AddViewField($field, $safe($item[$field] ?? '—'));
        $exactUrl = (string) $item['URL'];
        $row->AddViewField('SOURCE', ProductUrl::isAcceptedHttpUrl($exactUrl)
            ? '<a href="' . $safe($exactUrl) . '" target="_blank" rel="noopener noreferrer">' . $safe(Loc::getMessage('KK_PRICEWATCH_MONITORING_ACTION_SOURCE')) . '</a>'
            : '—');
        $actions = [
            ['TEXT' => Loc::getMessage('KK_PRICEWATCH_MONITORING_ACTION_OPEN'), 'ACTION' => $list->ActionRedirect($editUrl)],
            ['TEXT' => Loc::getMessage('KK_PRICEWATCH_MONITORING_ACTION_PRODUCT_LINKS'), 'ACTION' => $list->ActionRedirect('kk_pricewatch_product_competitors.php?lang=' . LANGUAGE_ID . '&PRODUCT_ID=' . $productId)],
            ['TEXT' => Loc::getMessage('KK_PRICEWATCH_MONITORING_ACTION_HISTORY'), 'ACTION' => $list->ActionRedirect('kk_pricewatch_price_history.php?lang=' . LANGUAGE_ID . '&PRODUCT_ID=' . $productId . '&PRODUCT_COMPETITOR_ID=' . $id)],
        ];
        if (Access::canWrite()) $actions[] = ['TEXT' => Loc::getMessage('KK_PRICEWATCH_MONITORING_ACTION_CHECK'), 'ACTION' => $list->ActionRedirect('kk_pricewatch_product_price_check.php?lang=' . LANGUAGE_ID . '&MODE=link&PRODUCT_ID=' . $productId . '&LINK_ID=' . $id)];
        $row->AddActions($actions);
        unset($row);
    }
}
$list->CheckListMode();
$APPLICATION->SetTitle(Loc::getMessage('KK_PRICEWATCH_MONITORING_TITLE'));
require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_after.php';

if ($readFailed):
    CAdminMessage::ShowMessage(['TYPE' => 'ERROR', 'MESSAGE' => Loc::getMessage('KK_PRICEWATCH_MONITORING_READ_FAILED')]);
else:
    $summaryLabels = ['total_active' => 'TOTAL', 'healthy' => 'HEALTHY', 'errors' => 'ERRORS', 'stale' => 'STALE', 'no_success_price' => 'NO_PRICE'];
    ?><div class="adm-info-message-wrap"><div class="adm-info-message"><?php foreach ($summaryLabels as $key => $label): ?>
        <strong><?= $safe(Loc::getMessage('KK_PRICEWATCH_MONITORING_SUMMARY_' . $label)) ?>:</strong> <?= (int) $dashboard->summary[$key] ?>&nbsp;&nbsp;
    <?php endforeach; ?><br><small><?= $safe(Loc::getMessage('KK_PRICEWATCH_MONITORING_SUMMARY_NOTE')) ?></small></div></div><?php
endif;

$filterUi = new CAdminFilter($tableId . '_filter', [Loc::getMessage('KK_PRICEWATCH_MONITORING_FILTER_PRODUCT')]);
?>
<form name="find_form" method="get" action="<?= $safe($APPLICATION->GetCurPage()) ?>">
<?php $filterUi->Begin(); ?>
<tr><td><?= $safe(Loc::getMessage('KK_PRICEWATCH_MONITORING_FILTER_PRODUCT')) ?>:</td><td><input type="text" name="find_product_id" value="<?= isset($filters['PRODUCT_ID']) ? (int) $filters['PRODUCT_ID'] : '' ?>"></td></tr>
<tr><td><?= $safe(Loc::getMessage('KK_PRICEWATCH_MONITORING_FILTER_COMPETITOR')) ?>:</td><td><select name="find_competitor_id"><option value=""></option><?php foreach ($competitors as $id => $name): ?><option value="<?= $id ?>"<?= ($filters['COMPETITOR_ID'] ?? null) === $id ? ' selected' : '' ?>><?= $safe($name) ?></option><?php endforeach; ?></select></td></tr>
<tr><td><?= $safe(Loc::getMessage('KK_PRICEWATCH_MONITORING_FILTER_STATUS')) ?>:</td><td><select name="find_status"><option value=""></option><?php foreach ([CollectionStatus::NEW, CollectionStatus::SUCCESS, CollectionStatus::ERROR] as $status): ?><option value="<?= $status ?>"<?= ($filters['STATUS'] ?? '') === $status ? ' selected' : '' ?>><?= $safe(Loc::getMessage('KK_PRICEWATCH_MONITORING_STATUS_' . strtoupper($status))) ?></option><?php endforeach; ?></select></td></tr>
<tr><td><?= $safe(Loc::getMessage('KK_PRICEWATCH_MONITORING_FILTER_HEALTH')) ?>:</td><td><select name="find_health"><option value=""></option><?php foreach ([MonitoringHealth::PROBLEMS, MonitoringHealth::HEALTHY, MonitoringHealth::STALE, MonitoringHealth::NO_PRICE, MonitoringHealth::ERROR] as $health): ?><option value="<?= $health ?>"<?= ($filters['HEALTH'] ?? '') === $health ? ' selected' : '' ?>><?= $safe(Loc::getMessage('KK_PRICEWATCH_MONITORING_FILTER_HEALTH_' . strtoupper($health))) ?></option><?php endforeach; ?></select></td></tr>
<?php $filterUi->Buttons(['table_id' => $tableId, 'url' => $APPLICATION->GetCurPage(), 'form' => 'find_form']); $filterUi->End(); ?>
</form>
<?php if (!$readFailed) $list->DisplayList(); require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_admin.php';
