<?php

use Bitrix\Main\Context;
use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;
use KK\PriceWatch\Admin\Access;
use KK\PriceWatch\Model\CollectorOptions;
use KK\PriceWatch\Model\CollectorType;
use KK\PriceWatch\Model\CompetitorTable;
use KK\PriceWatch\Model\ProductCompetitorTable;

require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_before.php';

if (!Loader::includeModule('kk.pricewatch') || !Access::canRead()) {
    $APPLICATION->AuthForm(Loc::getMessage('ACCESS_DENIED'));
}
Loc::loadMessages(__FILE__);
$request = Context::getCurrent()->getRequest();
$id = max(0, (int) $request->get('ID'));
$canWrite = Access::canWrite();
$error = '';
$record = $id > 0 ? CompetitorTable::getByPrimary($id)->fetch() : null;
if ($id > 0 && !$record) {
    $error = Loc::getMessage('KK_PRICEWATCH_EDIT_NOT_FOUND');
}
if ($id === 0 && !$canWrite) {
    $APPLICATION->AuthForm(Loc::getMessage('ACCESS_DENIED'));
}

$values = $record ?: [
    'NAME' => '', 'ACTIVE' => 'Y', 'SORT' => 500, 'DOMAIN' => '',
    'COLLECTOR_TYPE' => CollectorType::MOCK, 'COLLECTOR_HANDLER' => '', 'COLLECTOR_OPTIONS' => '{}',
];

if ($request->isPost() && ($record !== null || $id === 0)) {
    if (!$canWrite) {
        $error = Loc::getMessage('KK_PRICEWATCH_EDIT_WRITE_DENIED');
    } elseif (!check_bitrix_sessid()) {
        $error = Loc::getMessage('KK_PRICEWATCH_EDIT_BAD_SESSID');
    } elseif ((string) $request->getPost('action') === 'delete') {
        if ($id <= 0 || (string) $request->getPost('confirm_delete') !== 'Y') {
            $error = Loc::getMessage('KK_PRICEWATCH_EDIT_CONFIRM_DELETE');
        } elseif (ProductCompetitorTable::getList([
            'select' => ['ID'], 'filter' => ['=COMPETITOR_ID' => $id], 'limit' => 1,
        ])->fetch()) {
            $error = Loc::getMessage('KK_PRICEWATCH_EDIT_DELETE_REFERENCED');
        } else {
            $result = CompetitorTable::delete($id);
            $error = implode('<br>', array_map('htmlspecialcharsbx', $result->getErrorMessages()));
            if ($result->isSuccess()) LocalRedirect('kk_pricewatch_competitors.php?lang=' . LANGUAGE_ID);
        }
    } elseif ((string) $request->getPost('action') === 'save') {
        $values = [
            'NAME' => trim((string) $request->getPost('NAME')),
            'ACTIVE' => $request->getPost('ACTIVE') === 'Y' ? 'Y' : 'N',
            'SORT' => (int) $request->getPost('SORT'),
            'DOMAIN' => trim((string) $request->getPost('DOMAIN')),
            'COLLECTOR_TYPE' => (string) $request->getPost('COLLECTOR_TYPE'),
            'COLLECTOR_HANDLER' => trim((string) $request->getPost('COLLECTOR_HANDLER')),
            'COLLECTOR_OPTIONS' => (string) $request->getPost('COLLECTOR_OPTIONS'),
        ];
        try {
            $values['COLLECTOR_OPTIONS'] = CollectorOptions::encode(CollectorOptions::decode($values['COLLECTOR_OPTIONS']));
            $result = $id > 0 ? CompetitorTable::update($id, $values) : CompetitorTable::add($values);
            if ($result->isSuccess()) {
                $savedId = $id > 0 ? $id : (int) $result->getId();
                $target = $request->getPost('apply') !== null
                    ? 'kk_pricewatch_competitor_edit.php?lang=' . LANGUAGE_ID . '&ID=' . $savedId
                    : 'kk_pricewatch_competitors.php?lang=' . LANGUAGE_ID;
                LocalRedirect($target);
            }
            $error = implode('<br>', array_map('htmlspecialcharsbx', $result->getErrorMessages()));
        } catch (InvalidArgumentException) {
            $error = Loc::getMessage('KK_PRICEWATCH_EDIT_INVALID_OPTIONS');
        }
    }
}

$APPLICATION->SetTitle($id > 0 ? Loc::getMessage('KK_PRICEWATCH_EDIT_TITLE') : Loc::getMessage('KK_PRICEWATCH_EDIT_NEW_TITLE'));
require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_after.php';
if ($error !== '') CAdminMessage::ShowMessage($error);
$tabs = [['DIV' => 'main', 'TAB' => Loc::getMessage('KK_PRICEWATCH_EDIT_TAB'), 'TITLE' => Loc::getMessage('KK_PRICEWATCH_EDIT_TAB_TITLE')]];
$form = new CAdminTabControl('kk_pricewatch_competitor_form', $tabs);
?>
<?php if ($record !== null || $id === 0): ?>
<form method="post" action="<?= htmlspecialcharsbx($APPLICATION->GetCurPageParam('', ['action'])) ?>">
<?= bitrix_sessid_post() ?>
<input type="hidden" name="action" value="save">
<?php $form->Begin(); $form->BeginNextTab(); ?>
<?php if ($id > 0): ?>
<tr><td width="40%">ID:</td><td><?= $id ?></td></tr>
<tr><td><?= Loc::getMessage('KK_PRICEWATCH_EDIT_CREATED') ?>:</td><td><?= htmlspecialcharsbx((string) $values['CREATED_AT']) ?></td></tr>
<tr><td><?= Loc::getMessage('KK_PRICEWATCH_EDIT_UPDATED') ?>:</td><td><?= htmlspecialcharsbx((string) $values['UPDATED_AT']) ?></td></tr>
<?php endif; ?>
<tr class="adm-detail-required-field"><td><?= Loc::getMessage('KK_PRICEWATCH_EDIT_NAME') ?>:</td><td><input size="50" type="text" name="NAME" value="<?= htmlspecialcharsbx((string) $values['NAME']) ?>"<?= $canWrite ? '' : ' disabled' ?>></td></tr>
<tr><td><?= Loc::getMessage('KK_PRICEWATCH_EDIT_ACTIVE') ?>:</td><td><input type="checkbox" name="ACTIVE" value="Y"<?= $values['ACTIVE'] === 'Y' ? ' checked' : '' ?><?= $canWrite ? '' : ' disabled' ?>></td></tr>
<tr><td><?= Loc::getMessage('KK_PRICEWATCH_EDIT_SORT') ?>:</td><td><input type="number" name="SORT" value="<?= (int) $values['SORT'] ?>"<?= $canWrite ? '' : ' disabled' ?>></td></tr>
<tr><td><?= Loc::getMessage('KK_PRICEWATCH_EDIT_DOMAIN') ?>:<br><small><?= Loc::getMessage('KK_PRICEWATCH_EDIT_DOMAIN_HINT') ?></small></td><td><input size="50" type="text" name="DOMAIN" value="<?= htmlspecialcharsbx((string) $values['DOMAIN']) ?>"<?= $canWrite ? '' : ' disabled' ?>></td></tr>
<tr><td><?= Loc::getMessage('KK_PRICEWATCH_EDIT_TYPE') ?>:</td><td><select name="COLLECTOR_TYPE"<?= $canWrite ? '' : ' disabled' ?>><option value="mock"<?= $values['COLLECTOR_TYPE'] === 'mock' ? ' selected' : '' ?>>mock</option><option value="external"<?= $values['COLLECTOR_TYPE'] === 'external' ? ' selected' : '' ?>>external</option></select></td></tr>
<tr><td><?= Loc::getMessage('KK_PRICEWATCH_EDIT_HANDLER') ?>:<br><small><?= Loc::getMessage('KK_PRICEWATCH_EDIT_HANDLER_HINT') ?></small></td><td><input size="70" type="text" name="COLLECTOR_HANDLER" value="<?= htmlspecialcharsbx((string) $values['COLLECTOR_HANDLER']) ?>"<?= $canWrite ? '' : ' disabled' ?>></td></tr>
<tr><td><?= Loc::getMessage('KK_PRICEWATCH_EDIT_OPTIONS') ?>:<br><small><?= Loc::getMessage('KK_PRICEWATCH_EDIT_OPTIONS_HINT') ?></small></td><td><textarea rows="10" cols="70" name="COLLECTOR_OPTIONS"<?= $canWrite ? '' : ' disabled' ?>><?= htmlspecialcharsbx((string) $values['COLLECTOR_OPTIONS']) ?></textarea></td></tr>
<?php $form->Buttons(['disabled' => !$canWrite, 'back_url' => 'kk_pricewatch_competitors.php?lang=' . LANGUAGE_ID]); $form->End(); ?>
</form>
<?php endif; ?>
<?php if ($canWrite && $id > 0 && $record): ?>
<form method="post" action="<?= htmlspecialcharsbx($APPLICATION->GetCurPageParam('', ['action'])) ?>" onsubmit="return confirm('<?= CUtil::JSEscape(Loc::getMessage('KK_PRICEWATCH_EDIT_DELETE_CONFIRM_JS')) ?>');">
<?= bitrix_sessid_post() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="confirm_delete" value="Y">
<input type="submit" class="adm-btn-danger" value="<?= htmlspecialcharsbx(Loc::getMessage('KK_PRICEWATCH_EDIT_DELETE')) ?>">
</form>
<?php endif; require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_admin.php';
