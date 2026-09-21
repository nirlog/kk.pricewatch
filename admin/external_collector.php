<?php
use Bitrix\Main\Config\Option;
use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;
use KK\PriceWatch\Admin\Access;
use KK\PriceWatch\Collector\External\ExternalCollectorSettings;
require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_before.php';
if (!Loader::includeModule('kk.pricewatch') || !Access::canWrite()) $APPLICATION->AuthForm(Loc::getMessage('ACCESS_DENIED'));
Loc::loadMessages(__FILE__);
$module = 'kk.pricewatch'; $errors = []; $storedToken = Option::get($module, 'external_collector_token', '');
$values = ['enabled'=>Option::get($module,'external_collector_enabled','N'),'base_url'=>Option::get($module,'external_collector_base_url',''),
'connect_timeout'=>Option::get($module,'external_collector_connect_timeout','5'),'request_timeout'=>Option::get($module,'external_collector_request_timeout','60')];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save'])) {
    if (!check_bitrix_sessid()) $errors[] = Loc::getMessage('KK_PRICEWATCH_EXTERNAL_BAD_SESSID');
    $values=['enabled'=>isset($_POST['enabled'])?'Y':'N','base_url'=>trim((string)($_POST['base_url']??'')),
    'connect_timeout'=>trim((string)($_POST['connect_timeout']??'')),'request_timeout'=>trim((string)($_POST['request_timeout']??''))];
    $newToken=trim((string)($_POST['token']??'')); $token=$newToken!==''?$newToken:$storedToken;
    try { new ExternalCollectorSettings($values['enabled']==='Y',$values['base_url'],$token,(int)$values['connect_timeout'],(int)$values['request_timeout']); }
    catch (\Throwable) { $errors[]=Loc::getMessage('KK_PRICEWATCH_EXTERNAL_INVALID'); }
    if ($errors===[]) {
        foreach (['enabled','base_url','connect_timeout','request_timeout'] as $key) Option::set($module,'external_collector_'.$key,$values[$key]);
        if ($newToken!=='') Option::set($module,'external_collector_token',$newToken);
        LocalRedirect('kk_pricewatch_external_collector.php?lang='.LANGUAGE_ID.'&saved=Y');
    }
}
$APPLICATION->SetTitle(Loc::getMessage('KK_PRICEWATCH_EXTERNAL_TITLE'));
require $_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_admin_after.php';
if (($_GET['saved']??'')==='Y') CAdminMessage::ShowMessage(['TYPE'=>'OK','MESSAGE'=>Loc::getMessage('KK_PRICEWATCH_EXTERNAL_SAVED')]);
foreach ($errors as $error) CAdminMessage::ShowMessage($error);
?>
<form method="post" action="<?= htmlspecialcharsbx($APPLICATION->GetCurPage()) ?>"><?= bitrix_sessid_post() ?>
<table class="adm-detail-content-table edit-table"><tbody>
<tr><td width="40%"><?= htmlspecialcharsbx(Loc::getMessage('KK_PRICEWATCH_EXTERNAL_ENABLED')) ?></td><td><input type="checkbox" name="enabled" value="Y"<?= $values['enabled']==='Y'?' checked':'' ?>></td></tr>
<tr><td><?= htmlspecialcharsbx(Loc::getMessage('KK_PRICEWATCH_EXTERNAL_URL')) ?></td><td><input size="70" name="base_url" value="<?= htmlspecialcharsbx($values['base_url']) ?>"></td></tr>
<tr><td><?= htmlspecialcharsbx(Loc::getMessage('KK_PRICEWATCH_EXTERNAL_TOKEN')) ?></td><td><input type="password" size="50" name="token" value="" autocomplete="new-password"><br><small><?= htmlspecialcharsbx(Loc::getMessage('KK_PRICEWATCH_EXTERNAL_TOKEN_HINT')) ?></small><br><?= htmlspecialcharsbx(Loc::getMessage($storedToken!==''?'KK_PRICEWATCH_EXTERNAL_TOKEN_SET':'KK_PRICEWATCH_EXTERNAL_TOKEN_UNSET')) ?></td></tr>
<tr><td><?= htmlspecialcharsbx(Loc::getMessage('KK_PRICEWATCH_EXTERNAL_CONNECT')) ?></td><td><input type="number" min="1" max="30" name="connect_timeout" value="<?= (int)$values['connect_timeout'] ?>"></td></tr>
<tr><td><?= htmlspecialcharsbx(Loc::getMessage('KK_PRICEWATCH_EXTERNAL_REQUEST')) ?></td><td><input type="number" min="5" max="300" name="request_timeout" value="<?= (int)$values['request_timeout'] ?>"></td></tr>
</tbody></table><input type="submit" class="adm-btn-save" name="save" value="<?= htmlspecialcharsbx(Loc::getMessage('MAIN_SAVE')) ?>"></form>
<?php require $_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/epilog_admin.php';
