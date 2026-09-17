<?php

use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\UI\Filter\Options as FilterOptions;
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
$service = new MonitoringDashboardService(new OrmMonitoringDashboardRepository());
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
    $competitorQuery = CompetitorTable::getList([
        'select' => ['ID', 'NAME'], 'filter' => ['=ACTIVE' => 'Y'], 'order' => ['SORT' => 'ASC', 'NAME' => 'ASC', 'ID' => 'ASC'],
    ]);
    while ($competitor = $competitorQuery->fetch()) $competitors[(int) $competitor['ID']] = (string) $competitor['NAME'];

    $filterFields = [
        ['id' => 'PRODUCT_ID', 'name' => Loc::getMessage('KK_PRICEWATCH_MONITORING_FILTER_PRODUCT'), 'type' => 'string'],
        ['id' => 'COMPETITOR_ID', 'name' => Loc::getMessage('KK_PRICEWATCH_MONITORING_FILTER_COMPETITOR'), 'type' => 'list', 'items' => $competitors],
        ['id' => 'STATUS', 'name' => Loc::getMessage('KK_PRICEWATCH_MONITORING_FILTER_STATUS'), 'type' => 'list', 'items' => [
            CollectionStatus::NEW => Loc::getMessage('KK_PRICEWATCH_MONITORING_STATUS_NEW'),
            CollectionStatus::SUCCESS => Loc::getMessage('KK_PRICEWATCH_MONITORING_STATUS_SUCCESS'),
            CollectionStatus::ERROR => Loc::getMessage('KK_PRICEWATCH_MONITORING_STATUS_ERROR'),
        ]],
        ['id' => 'HEALTH', 'name' => Loc::getMessage('KK_PRICEWATCH_MONITORING_FILTER_HEALTH'), 'type' => 'list', 'items' => [
            MonitoringHealth::PROBLEMS => Loc::getMessage('KK_PRICEWATCH_MONITORING_FILTER_HEALTH_PROBLEMS'),
            MonitoringHealth::HEALTHY => Loc::getMessage('KK_PRICEWATCH_MONITORING_FILTER_HEALTH_HEALTHY'),
            MonitoringHealth::STALE => Loc::getMessage('KK_PRICEWATCH_MONITORING_FILTER_HEALTH_STALE'),
            MonitoringHealth::NO_PRICE => Loc::getMessage('KK_PRICEWATCH_MONITORING_FILTER_HEALTH_NO_PRICE'),
            MonitoringHealth::ERROR => Loc::getMessage('KK_PRICEWATCH_MONITORING_FILTER_HEALTH_ERROR'),
        ]],
    ];
    $filterData = (new FilterOptions($tableId))->getFilter($filterFields);
    $filters = $service->normalizeFilters([
        'product_id' => $filterData['PRODUCT_ID'] ?? null,
        'competitor_id' => $filterData['COMPETITOR_ID'] ?? null,
        'status' => $filterData['STATUS'] ?? null,
        'health' => $filterData['HEALTH'] ?? null,
    ]);
    $dashboard = $service->load($filters, $ormOrder, $navigation->getLimit(), $navigation->getOffset());
    $navigation->setRecordCount($dashboard->totalRows);
    $productNames = (new ProductNameResolver())->resolve(array_map(static fn(array $row): int => (int) $row['PRODUCT_ID'], $dashboard->rows));
} catch (Throwable) {
    $readFailed = true;
}

$filterFields ??= [];

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
$formatDateTime = static function (mixed $value): string {
    return $value instanceof \DateTimeInterface
        ? $value->format('d.m.Y H:i:s')
        : '—';
};
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
        $rowData = $item;
        foreach (['LAST_CHECK_AT', 'LAST_SUCCESS_AT'] as $field) {
            $rowData[$field] = $formatDateTime($item[$field] ?? null);
        }
        $row = &$list->AddRow($id, $rowData, $editUrl, Loc::getMessage('KK_PRICEWATCH_MONITORING_ACTION_OPEN'));
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
        foreach (['LAST_CHECK_AT', 'LAST_SUCCESS_AT'] as $field) $row->AddViewField($field, $safe($formatDateTime($item[$field] ?? null)));
        $row->AddViewField('ERROR_CODE', $safe($item['ERROR_CODE'] ?? '—'));
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
$list->DisplayFilter($filterFields);
if (!$readFailed) $list->DisplayList();
require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_admin.php';
