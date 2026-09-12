<?php

use Bitrix\Main\Context;
use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;
use KK\PriceWatch\Admin\Access;
use KK\PriceWatch\Admin\ProductCompetitorLinkService;
use KK\PriceWatch\Admin\ProductContext;
use KK\PriceWatch\Model\CompetitorTable;
use KK\PriceWatch\Model\ProductCompetitorTable;

require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_before.php';
if (!Loader::includeModule('kk.pricewatch') || !Access::canRead()) {
    $APPLICATION->AuthForm(Loc::getMessage('ACCESS_DENIED'));
}
Loc::loadMessages(__FILE__);
$request = Context::getCurrent()->getRequest();
$productId = (int) $request->get('PRODUCT_ID');
$product = ProductContext::findCatalogProduct($productId);
if ($product === null) {
    $APPLICATION->AuthForm(Loc::getMessage('KK_PRICEWATCH_LINK_INVALID_PRODUCT'));
}
$id = max(0, (int) $request->get('ID'));
$canWrite = Access::canWrite();
$record = $id > 0 ? ProductCompetitorTable::getList([
    'filter' => ['=ID' => $id, '=PRODUCT_ID' => $productId], 'limit' => 1,
])->fetch() : null;
$error = '';
if ($id > 0 && !$record) $error = Loc::getMessage('KK_PRICEWATCH_LINK_NOT_FOUND');
if ($id === 0 && !$canWrite) $APPLICATION->AuthForm(Loc::getMessage('ACCESS_DENIED'));
$values = $record ?: ['COMPETITOR_ID' => 0, 'URL' => '', 'ACTIVE' => 'Y'];
$service = new ProductCompetitorLinkService();

if ($request->isPost() && ($record !== null || $id === 0)) {
    if (!$canWrite) {
        $error = Loc::getMessage('KK_PRICEWATCH_LINK_WRITE_DENIED');
    } elseif (!check_bitrix_sessid()) {
        $error = Loc::getMessage('KK_PRICEWATCH_LINK_BAD_SESSID');
    } elseif ((string) $request->getPost('action') === 'delete') {
        if ($id <= 0 || (string) $request->getPost('confirm_delete') !== 'Y') {
            $error = Loc::getMessage('KK_PRICEWATCH_LINK_CONFIRM_DELETE');
        } else {
            try {
                $service->delete($productId, $id);
                LocalRedirect('kk_pricewatch_product_competitors.php?lang=' . LANGUAGE_ID . '&PRODUCT_ID=' . $productId);
            } catch (DomainException) {
                $error = Loc::getMessage('KK_PRICEWATCH_LINK_SAVE_FAILED');
            }
        }
    } elseif ((string) $request->getPost('action') === 'save') {
        // URL is deliberately read and persisted byte-for-byte; do not trim or normalize it.
        $values = [
            'COMPETITOR_ID' => (int) $request->getPost('COMPETITOR_ID'),
            'URL' => (string) $request->getPost('URL'),
            'ACTIVE' => $request->getPost('ACTIVE') === 'Y' ? 'Y' : 'N',
        ];
        try {
            $savedId = $service->save($productId, $values['COMPETITOR_ID'], $values['URL'], $values['ACTIVE'], $id > 0 ? $id : null);
            $target = $request->getPost('apply') !== null
                ? 'kk_pricewatch_product_competitor_edit.php?lang=' . LANGUAGE_ID . '&PRODUCT_ID=' . $productId . '&ID=' . $savedId
                : 'kk_pricewatch_product_competitors.php?lang=' . LANGUAGE_ID . '&PRODUCT_ID=' . $productId;
            LocalRedirect($target);
        } catch (DomainException $exception) {
            $messageKey = match ($exception->getMessage()) {
                ProductCompetitorLinkService::ERROR_INVALID_URL => 'KK_PRICEWATCH_LINK_INVALID_URL',
                ProductCompetitorLinkService::ERROR_COMPETITOR_NOT_FOUND => 'KK_PRICEWATCH_LINK_INVALID_COMPETITOR',
                ProductCompetitorLinkService::ERROR_DUPLICATE => 'KK_PRICEWATCH_LINK_DUPLICATE',
                default => 'KK_PRICEWATCH_LINK_SAVE_FAILED',
            };
            $error = Loc::getMessage($messageKey);
        }
    }
}

$competitors = CompetitorTable::getList([
    'select' => ['ID', 'NAME', 'ACTIVE'],
    'filter' => $id === 0 ? ['=ACTIVE' => 'Y'] : [],
    'order' => ['SORT' => 'ASC', 'NAME' => 'ASC', 'ID' => 'ASC'],
])->fetchAll();
if ($record && !array_filter($competitors, static fn(array $item): bool => (int) $item['ID'] === (int) $record['COMPETITOR_ID'])) {
    $selected = CompetitorTable::getByPrimary((int) $record['COMPETITOR_ID'])->fetch();
    if ($selected) $competitors[] = $selected;
}

$APPLICATION->SetTitle(Loc::getMessage($id > 0 ? 'KK_PRICEWATCH_LINK_TITLE' : 'KK_PRICEWATCH_LINK_NEW_TITLE'));
require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_after.php';
if ($error !== '') CAdminMessage::ShowMessage(htmlspecialcharsbx((string) $error));
$tabs = [['DIV' => 'main', 'TAB' => Loc::getMessage('KK_PRICEWATCH_LINK_TAB'), 'TITLE' => Loc::getMessage('KK_PRICEWATCH_LINK_TAB_TITLE')]];
$form = new CAdminTabControl('kk_pricewatch_product_competitor_form', $tabs);
?>
<p><?= htmlspecialcharsbx((string) Loc::getMessage('KK_PRICEWATCH_LINK_CONTEXT', ['#NAME#' => $product['NAME'], '#ID#' => $productId])) ?></p>
<?php if ($canWrite && $record): ?>
<p><a class="adm-btn" href="<?= htmlspecialcharsbx('kk_pricewatch_product_price_check.php?lang=' . LANGUAGE_ID . '&MODE=link&PRODUCT_ID=' . $productId . '&LINK_ID=' . $id) ?>"><?= htmlspecialcharsbx((string) Loc::getMessage('KK_PRICEWATCH_LINK_CHECK_PRICE')) ?></a></p>
<?php endif; ?>
<?php if ($record !== null || $id === 0): ?>
<form method="post" action="<?= htmlspecialcharsbx($APPLICATION->GetCurPageParam('', ['action'])) ?>">
<?= bitrix_sessid_post() ?><input type="hidden" name="action" value="save">
<?php $form->Begin(); $form->BeginNextTab(); ?>
<?php if ($id > 0): ?><tr><td width="40%">ID:</td><td><?= $id ?></td></tr><?php endif; ?>
<tr class="adm-detail-required-field"><td><?= Loc::getMessage('KK_PRICEWATCH_LINK_COMPETITOR') ?>:</td><td><select name="COMPETITOR_ID"<?= $canWrite ? '' : ' disabled' ?>>
<?php foreach ($competitors as $competitor): ?><option value="<?= (int) $competitor['ID'] ?>"<?= (int) $values['COMPETITOR_ID'] === (int) $competitor['ID'] ? ' selected' : '' ?>><?= htmlspecialcharsbx((string) $competitor['NAME']) ?><?= $competitor['ACTIVE'] === 'N' ? ' (' . htmlspecialcharsbx((string) Loc::getMessage('KK_PRICEWATCH_LINK_INACTIVE')) . ')' : '' ?></option><?php endforeach; ?>
</select></td></tr>
<tr class="adm-detail-required-field"><td>URL:</td><td><textarea name="URL" rows="4" cols="90"<?= $canWrite ? '' : ' disabled' ?>><?= htmlspecialcharsbx((string) $values['URL']) ?></textarea></td></tr>
<tr><td><?= Loc::getMessage('KK_PRICEWATCH_LINK_ACTIVE') ?>:</td><td><input type="checkbox" name="ACTIVE" value="Y"<?= $values['ACTIVE'] === 'Y' ? ' checked' : '' ?><?= $canWrite ? '' : ' disabled' ?>></td></tr>
<?php if ($record): foreach (['CURRENT_PRICE', 'CURRENCY', 'STATUS', 'ERROR_CODE', 'ERROR_MESSAGE', 'LAST_CHECK_AT', 'LAST_SUCCESS_AT', 'CREATED_AT', 'UPDATED_AT'] as $field): ?>
<tr><td><?= htmlspecialcharsbx((string) Loc::getMessage('KK_PRICEWATCH_LINK_' . $field)) ?>:</td><td><?= htmlspecialcharsbx((string) $record[$field]) ?></td></tr>
<?php endforeach; endif; ?>
<?php $form->Buttons(['disabled' => !$canWrite, 'back_url' => 'kk_pricewatch_product_competitors.php?lang=' . LANGUAGE_ID . '&PRODUCT_ID=' . $productId]); $form->End(); ?>
</form>
<?php endif; ?>
<?php if ($canWrite && $record): ?>
<form method="post" action="<?= htmlspecialcharsbx($APPLICATION->GetCurPageParam('', ['action'])) ?>" onsubmit="return confirm('<?= CUtil::JSEscape(Loc::getMessage('KK_PRICEWATCH_LINK_DELETE_CONFIRM_JS')) ?>');">
<?= bitrix_sessid_post() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="confirm_delete" value="Y">
<input type="submit" class="adm-btn-danger" value="<?= htmlspecialcharsbx((string) Loc::getMessage('KK_PRICEWATCH_LINK_DELETE')) ?>">
</form>
<?php endif; require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_admin.php';
