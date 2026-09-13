<?php

use Bitrix\Main\Context;
use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;
use KK\PriceWatch\Admin\Access;
use KK\PriceWatch\Admin\ProductContext;
use KK\PriceWatch\Admin\PriceHistoryChartRenderer;
use KK\PriceWatch\Model\PriceHistoryTable;
use KK\PriceWatch\Model\ProductCompetitorTable;
use KK\PriceWatch\Service\OrmPriceHistoryAnalyticsRepository;
use KK\PriceWatch\Service\PriceHistoryAnalyticsService;

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
$analytics = null;
$currentLink = null;
if ($linkId > 0 && $productId > 0) {
    $currentLink = ProductCompetitorTable::getList([
        'select' => ['ID', 'PRODUCT_ID', 'COMPETITOR_ID', 'URL', 'URL_HASH', 'COMPETITOR_NAME' => 'COMPETITOR.NAME'],
        'filter' => ['=ID' => $linkId, '=PRODUCT_ID' => $productId],
        'limit' => 1,
    ])->fetch();
    if (is_array($currentLink)) {
        $analytics = (new PriceHistoryAnalyticsService(new OrmPriceHistoryAnalyticsRepository()))->analyze(
            (int) $currentLink['ID'], (int) $currentLink['PRODUCT_ID'], (int) $currentLink['COMPETITOR_ID'], (string) $currentLink['URL_HASH']
        );
    }
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
$safe = static fn(mixed $value): string => htmlspecialcharsbx((string) $value);
if ($linkId <= 0 || $productId <= 0) {
    CAdminMessage::ShowMessage(['TYPE' => 'OK', 'MESSAGE' => Loc::getMessage('KK_PRICEWATCH_ANALYTICS_NEEDS_LINK')]);
} elseif ($currentLink === null || $currentLink === false) {
    CAdminMessage::ShowMessage(['TYPE' => 'ERROR', 'MESSAGE' => Loc::getMessage('KK_PRICEWATCH_ANALYTICS_DELETED_LINK')]);
} else {
    ?><div class="adm-detail-content-wrap"><div class="adm-detail-content"><div class="adm-detail-content-item-block">
        <h3><?= $safe(Loc::getMessage('KK_PRICEWATCH_ANALYTICS_CONTEXT')) ?></h3>
        <p><?= $safe($currentLink['COMPETITOR_NAME']) ?> (ID <?= (int) $currentLink['COMPETITOR_ID'] ?>),
            <span><?= $safe($currentLink['URL']) ?></span></p>
        <?php if ($analytics->isEmpty()): ?>
            <p><?= $safe(Loc::getMessage('KK_PRICEWATCH_ANALYTICS_EMPTY')) ?></p>
        <?php else: ?>
            <h3><?= $safe(Loc::getMessage('KK_PRICEWATCH_ANALYTICS_SUMMARY')) ?></h3>
            <dl>
                <dt><?= $safe(Loc::getMessage('KK_PRICEWATCH_ANALYTICS_COUNT')) ?></dt><dd><?= $analytics->pointCount ?></dd>
                <dt><?= $safe(Loc::getMessage('KK_PRICEWATCH_ANALYTICS_FIRST')) ?></dt><dd><?= $safe($analytics->first['price'] . ' ' . $analytics->first['currency'] . ' — ' . $analytics->first['collected_at']) ?></dd>
                <dt><?= $safe(Loc::getMessage('KK_PRICEWATCH_ANALYTICS_LATEST')) ?></dt><dd><?= $safe($analytics->latest['price'] . ' ' . $analytics->latest['currency'] . ' — ' . $analytics->latest['collected_at']) ?></dd>
                <dt><?= $safe(Loc::getMessage('KK_PRICEWATCH_ANALYTICS_PREVIOUS')) ?></dt><dd><?= $analytics->previous === null ? '—' : $safe($analytics->previous['price'] . ' ' . $analytics->previous['currency'] . ' — ' . $analytics->previous['collected_at']) ?></dd>
                <dt><?= $safe(Loc::getMessage('KK_PRICEWATCH_ANALYTICS_MIN_MAX')) ?></dt><dd><?= $analytics->mixedCurrencies ? '—' : $safe($analytics->minimumPrice . ' / ' . $analytics->maximumPrice . ' ' . $analytics->currency) ?></dd>
                <dt><?= $safe(Loc::getMessage('KK_PRICEWATCH_ANALYTICS_DIRECTION')) ?></dt><dd><?= $safe(Loc::getMessage('KK_PRICEWATCH_ANALYTICS_DIRECTION_' . strtoupper($analytics->direction))) ?></dd>
            </dl>
            <?php if ($analytics->mixedCurrencies): ?>
                <?php CAdminMessage::ShowMessage(['TYPE' => 'ERROR', 'MESSAGE' => Loc::getMessage('KK_PRICEWATCH_ANALYTICS_MIXED')]); ?>
            <?php else: ?>
                <?= PriceHistoryChartRenderer::render($analytics->chartPoints,
                    (string) Loc::getMessage('KK_PRICEWATCH_ANALYTICS_CHART_TITLE'),
                    (string) Loc::getMessage('KK_PRICEWATCH_ANALYTICS_CHART_DESCRIPTION')) ?>
                <?php if ($analytics->chartTruncated): ?><p><?= $safe(Loc::getMessage('KK_PRICEWATCH_ANALYTICS_TRUNCATED', ['#COUNT#' => PriceHistoryAnalyticsService::CHART_POINT_LIMIT])) ?></p><?php endif; ?>
            <?php endif; ?>
        <?php endif; ?>
    </div></div></div><?php
}
$list->DisplayList();
require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_admin.php';
