<?php

use Bitrix\Main\Context;
use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;
use KK\PriceWatch\Admin\Access;
use KK\PriceWatch\Admin\ProductCompetitorLinkService;
use KK\PriceWatch\Admin\ProductContext;
use KK\PriceWatch\Model\ProductCompetitorTable;
use KK\PriceWatch\Service\PriceUpdateServiceFactory;

require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_before.php';
if (!Loader::includeModule('kk.pricewatch') || !Access::canRead()) {
    $APPLICATION->AuthForm(Loc::getMessage('ACCESS_DENIED'));
}
Loc::loadMessages(__FILE__);

$request = Context::getCurrent()->getRequest();
$mode = (string) $request->get('MODE');
$productId = (int) $request->get('PRODUCT_ID');
$linkId = (int) $request->get('LINK_ID');
$product = ProductContext::findCatalogProduct($productId);
$error = '';
$unexpectedFailure = false;

if ($product === null) {
    $error = (string) Loc::getMessage('KK_PRICEWATCH_CHECK_INVALID_PRODUCT');
} elseif (!in_array($mode, ['link', 'product'], true)) {
    $error = (string) Loc::getMessage('KK_PRICEWATCH_CHECK_INVALID_MODE');
}

$select = [
    'ID', 'PRODUCT_ID', 'ACTIVE', 'URL', 'CURRENT_PRICE', 'CURRENCY', 'STATUS',
    'LAST_CHECK_AT', 'LAST_SUCCESS_AT', 'COMPETITOR_NAME' => 'COMPETITOR.NAME',
];
$links = [];
if ($error === '' && $mode === 'link') {
    if ($linkId <= 0) {
        $error = (string) Loc::getMessage('KK_PRICEWATCH_CHECK_INVALID_LINK');
    } else {
        $link = ProductCompetitorTable::getByPrimary($linkId, ['select' => $select])->fetch();
        if (!$link || (int) $link['PRODUCT_ID'] !== $productId) {
            $error = (string) Loc::getMessage('KK_PRICEWATCH_CHECK_LINK_MISMATCH');
        } else {
            $links = [$link];
        }
    }
} elseif ($error === '' && $mode === 'product') {
    $links = ProductCompetitorTable::getList([
        'select' => $select,
        'filter' => ['=PRODUCT_ID' => $productId, '=ACTIVE' => 'Y'],
        'order' => ['ID' => 'ASC'],
    ])->fetchAll();
}

$flashKey = (string) $request->get('RESULT');
$resultData = null;
if (!$request->isPost() && $flashKey !== '' && isset($_SESSION['KK_PRICEWATCH_PRICE_CHECK'][$flashKey])) {
    $resultData = $_SESSION['KK_PRICEWATCH_PRICE_CHECK'][$flashKey];
    unset($_SESSION['KK_PRICEWATCH_PRICE_CHECK'][$flashKey]);
}

if ($request->isPost() && $error === '') {
    if (!Access::canWrite()) {
        $error = (string) Loc::getMessage('KK_PRICEWATCH_CHECK_WRITE_DENIED');
    } elseif (!check_bitrix_sessid()) {
        $error = (string) Loc::getMessage('KK_PRICEWATCH_CHECK_BAD_SESSID');
    } elseif ($links === []) {
        $error = (string) Loc::getMessage('KK_PRICEWATCH_CHECK_NO_ACTIVE');
    } else {
        $linkIds = array_map(static fn(array $link): int => (int) $link['ID'], $links);
        try {
            $result = PriceUpdateServiceFactory::createDefault()->updateLinks($linkIds);
            $flashKey = bin2hex(random_bytes(16));
            $_SESSION['KK_PRICEWATCH_PRICE_CHECK'][$flashKey] = [
                'requested' => $result->requestedCount,
                'success' => $result->successCount(),
                'errors' => $result->errorCount(),
                'skipped' => $result->skippedCount(),
                'persistence_failures' => $result->persistenceFailureCount(),
                'outcomes' => array_map(static fn($outcome): array => [
                    'link_id' => $outcome->linkId,
                    'status' => $outcome->status,
                    'code' => $outcome->code,
                    'message' => $outcome->message,
                ], $result->outcomes),
            ];
            $redirect = 'kk_pricewatch_product_price_check.php?lang=' . rawurlencode((string) LANGUAGE_ID)
                . '&MODE=' . rawurlencode($mode) . '&PRODUCT_ID=' . $productId
                . ($mode === 'link' ? '&LINK_ID=' . $linkId : '')
                . '&RESULT=' . rawurlencode($flashKey);
            LocalRedirect($redirect);
        } catch (Throwable) {
            $unexpectedFailure = true;
            $error = (string) Loc::getMessage('KK_PRICEWATCH_CHECK_UNEXPECTED');
        }
    }
}

$APPLICATION->SetTitle((string) Loc::getMessage('KK_PRICEWATCH_CHECK_TITLE'));
require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_after.php';
if ($error !== '') {
    CAdminMessage::ShowMessage(htmlspecialcharsbx($error));
}
$backUrl = 'kk_pricewatch_product_competitors.php?lang=' . rawurlencode((string) LANGUAGE_ID) . '&PRODUCT_ID=' . $productId;
?>
<?php if ($product !== null): ?>
<p><?= htmlspecialcharsbx((string) Loc::getMessage('KK_PRICEWATCH_CHECK_PRODUCT_CONTEXT', ['#NAME#' => $product['NAME'], '#ID#' => $productId])) ?></p>
<?php endif; ?>

<?php if (is_array($resultData)): ?>
<h2><?= htmlspecialcharsbx((string) Loc::getMessage('KK_PRICEWATCH_CHECK_RESULT_TITLE')) ?></h2>
<table class="adm-list-table"><tbody>
<?php foreach (['requested', 'success', 'errors', 'skipped', 'persistence_failures'] as $counter): ?>
<tr><th><?= htmlspecialcharsbx((string) Loc::getMessage('KK_PRICEWATCH_CHECK_' . strtoupper($counter))) ?></th><td><?= (int) ($resultData[$counter] ?? 0) ?></td></tr>
<?php endforeach; ?>
</tbody></table>
<table class="adm-list-table"><thead><tr><th>ID</th><th><?= htmlspecialcharsbx((string) Loc::getMessage('KK_PRICEWATCH_CHECK_OUTCOME_STATUS')) ?></th><th><?= htmlspecialcharsbx((string) Loc::getMessage('KK_PRICEWATCH_CHECK_OUTCOME_CODE')) ?></th><th><?= htmlspecialcharsbx((string) Loc::getMessage('KK_PRICEWATCH_CHECK_OUTCOME_MESSAGE')) ?></th></tr></thead><tbody>
<?php foreach (($resultData['outcomes'] ?? []) as $outcome): ?><tr>
<td><?= (int) ($outcome['link_id'] ?? 0) ?></td><td><?= htmlspecialcharsbx((string) ($outcome['status'] ?? '')) ?></td><td><?= htmlspecialcharsbx((string) ($outcome['code'] ?? '')) ?></td><td><?= htmlspecialcharsbx((string) ($outcome['message'] ?? '')) ?></td>
</tr><?php endforeach; ?>
</tbody></table>
<?php elseif ($error === '' && $links === []): ?>
<?php CAdminMessage::ShowNote(htmlspecialcharsbx((string) Loc::getMessage('KK_PRICEWATCH_CHECK_NO_ACTIVE'))); ?>
<?php elseif ($error === '' && !$unexpectedFailure): ?>
<p><?= htmlspecialcharsbx((string) Loc::getMessage($mode === 'link' ? 'KK_PRICEWATCH_CHECK_LINK_CONFIRM' : 'KK_PRICEWATCH_CHECK_PRODUCT_CONFIRM', ['#COUNT#' => count($links)])) ?></p>
<table class="adm-list-table"><thead><tr><th>ID</th><th><?= htmlspecialcharsbx((string) Loc::getMessage('KK_PRICEWATCH_CHECK_COMPETITOR')) ?></th><th>URL</th><th><?= htmlspecialcharsbx((string) Loc::getMessage('KK_PRICEWATCH_CHECK_ACTIVE')) ?></th><th><?= htmlspecialcharsbx((string) Loc::getMessage('KK_PRICEWATCH_CHECK_PRICE')) ?></th><th><?= htmlspecialcharsbx((string) Loc::getMessage('KK_PRICEWATCH_CHECK_STATUS')) ?></th><th><?= htmlspecialcharsbx((string) Loc::getMessage('KK_PRICEWATCH_CHECK_LAST_CHECK')) ?></th><th><?= htmlspecialcharsbx((string) Loc::getMessage('KK_PRICEWATCH_CHECK_LAST_SUCCESS')) ?></th></tr></thead><tbody>
<?php foreach ($links as $link): $url = (string) $link['URL']; $safeUrl = htmlspecialcharsbx($url); ?><tr>
<td><?= (int) $link['ID'] ?></td><td><?= htmlspecialcharsbx((string) $link['COMPETITOR_NAME']) ?></td><td><?= ProductCompetitorLinkService::isAcceptedHttpUrl($url) ? '<a href="' . $safeUrl . '" target="_blank" rel="noopener noreferrer">' . $safeUrl . '</a>' : $safeUrl ?></td><td><?= htmlspecialcharsbx((string) $link['ACTIVE']) ?></td><td><?= htmlspecialcharsbx(trim((string) $link['CURRENT_PRICE'] . ' ' . (string) $link['CURRENCY'])) ?></td><td><?= htmlspecialcharsbx((string) $link['STATUS']) ?></td><td><?= htmlspecialcharsbx((string) $link['LAST_CHECK_AT']) ?></td><td><?= htmlspecialcharsbx((string) $link['LAST_SUCCESS_AT']) ?></td>
</tr><?php endforeach; ?>
</tbody></table>
<?php if (Access::canWrite()): ?><form method="post" action="<?= htmlspecialcharsbx($APPLICATION->GetCurPage()) ?>">
<?= bitrix_sessid_post() ?><input type="hidden" name="MODE" value="<?= htmlspecialcharsbx($mode) ?>"><input type="hidden" name="PRODUCT_ID" value="<?= $productId ?>"><?php if ($mode === 'link'): ?><input type="hidden" name="LINK_ID" value="<?= $linkId ?>"><?php endif; ?>
<input type="submit" class="adm-btn-save" value="<?= htmlspecialcharsbx((string) Loc::getMessage('KK_PRICEWATCH_CHECK_EXECUTE')) ?>">
</form><?php endif; ?>
<?php endif; ?>
<p><a class="adm-btn" href="<?= htmlspecialcharsbx($backUrl) ?>"><?= htmlspecialcharsbx((string) Loc::getMessage('KK_PRICEWATCH_CHECK_BACK')) ?></a></p>
<?php require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_admin.php';
