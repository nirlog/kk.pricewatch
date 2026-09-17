<?php
use Bitrix\Main\Config\Option;
use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\SiteTable;
use KK\PriceWatch\Admin\Access;
use KK\PriceWatch\Notification\NotificationSettings;

require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_before.php';
if (!Loader::includeModule('kk.pricewatch') || !Access::canWrite()) $APPLICATION->AuthForm(Loc::getMessage('ACCESS_DENIED'));
Loc::loadMessages(__FILE__);
$moduleId = 'kk.pricewatch'; $errors = [];
$sites = []; $siteQuery = SiteTable::getList(['select' => ['LID', 'NAME'], 'filter' => ['=ACTIVE' => 'Y'], 'order' => ['SORT' => 'ASC']]);
while ($site = $siteQuery->fetch()) $sites[(string) $site['LID']] = (string) $site['NAME'];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save']) && check_bitrix_sessid()) {
    $enabled = isset($_POST['notifications_enabled']) ? 'Y' : 'N';
    $recovery = isset($_POST['send_recovery']) ? 'Y' : 'N';
    $rawEmails = (string) ($_POST['notification_emails'] ?? ''); $emails = NotificationSettings::normalizeEmails($rawEmails);
    $tokens = preg_split('/[,;\s]+/', trim($rawEmails), -1, PREG_SPLIT_NO_EMPTY) ?: [];
    if (array_filter($tokens, static fn(string $email): bool => filter_var($email, FILTER_VALIDATE_EMAIL) === false) !== []) {
        $errors[] = Loc::getMessage('KK_PRICEWATCH_NOTIFY_INVALID_EMAIL');
    }
    $siteId = trim((string) ($_POST['notification_site_id'] ?? ''));
    if ($siteId !== '' && !isset($sites[$siteId])) $errors[] = Loc::getMessage('KK_PRICEWATCH_NOTIFY_INVALID_SITE');
    if ($errors === []) {
        Option::set($moduleId, 'notifications_enabled', $enabled); Option::set($moduleId, 'notification_emails', implode(', ', $emails));
        Option::set($moduleId, 'notification_site_id', $siteId); Option::set($moduleId, 'send_recovery', $recovery);
        LocalRedirect('kk_pricewatch_notification_settings.php?lang=' . LANGUAGE_ID . '&saved=Y');
    }
}
$values = ['enabled' => Option::get($moduleId, 'notifications_enabled', 'N'), 'emails' => Option::get($moduleId, 'notification_emails', ''),
    'site' => Option::get($moduleId, 'notification_site_id', ''), 'recovery' => Option::get($moduleId, 'send_recovery', 'Y')];
$APPLICATION->SetTitle(Loc::getMessage('KK_PRICEWATCH_NOTIFY_TITLE'));
require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_after.php';
if (($_GET['saved'] ?? '') === 'Y') CAdminMessage::ShowMessage(['TYPE' => 'OK', 'MESSAGE' => Loc::getMessage('KK_PRICEWATCH_NOTIFY_SAVED')]);
foreach ($errors as $error) CAdminMessage::ShowMessage($error);
?><form method="post" action="<?= htmlspecialcharsbx($APPLICATION->GetCurPage()) ?>">
<?= bitrix_sessid_post() ?>
<table class="adm-detail-content-table edit-table"><tbody>
<tr><td width="40%"><?= htmlspecialcharsbx(Loc::getMessage('KK_PRICEWATCH_NOTIFY_ENABLED')) ?></td><td><input type="checkbox" name="notifications_enabled" value="Y"<?= $values['enabled'] === 'Y' ? ' checked' : '' ?>></td></tr>
<tr><td><?= htmlspecialcharsbx(Loc::getMessage('KK_PRICEWATCH_NOTIFY_EMAILS')) ?></td><td><textarea name="notification_emails" rows="4" cols="60"><?= htmlspecialcharsbx($values['emails']) ?></textarea></td></tr>
<tr><td><?= htmlspecialcharsbx(Loc::getMessage('KK_PRICEWATCH_NOTIFY_SITE')) ?></td><td><select name="notification_site_id"><option value=""></option><?php foreach ($sites as $id => $name): ?><option value="<?= htmlspecialcharsbx($id) ?>"<?= $values['site'] === $id ? ' selected' : '' ?>><?= htmlspecialcharsbx($name . ' [' . $id . ']') ?></option><?php endforeach ?></select></td></tr>
<tr><td><?= htmlspecialcharsbx(Loc::getMessage('KK_PRICEWATCH_NOTIFY_RECOVERY')) ?></td><td><input type="checkbox" name="send_recovery" value="Y"<?= $values['recovery'] === 'Y' ? ' checked' : '' ?>></td></tr>
</tbody></table><input type="submit" class="adm-btn-save" name="save" value="<?= htmlspecialcharsbx(Loc::getMessage('MAIN_SAVE')) ?>"></form>
<?php require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_admin.php';
