<?php

use Bitrix\Main\Config\Option;
use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\SiteTable;
use KK\PriceWatch\Admin\Access;
use KK\PriceWatch\Installer\NotificationAgentInstaller;
use KK\PriceWatch\Installer\NotificationAgentState;
use KK\PriceWatch\Notification\BitrixNotificationMailTemplateChecker;
use KK\PriceWatch\Notification\NotificationAgentIntervalValidator;
use KK\PriceWatch\Notification\NotificationSettings;
use KK\PriceWatch\Notification\NotificationSettingsValidator;

require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_before.php';
if (!Loader::includeModule('kk.pricewatch') || !Access::canWrite()) {
    $APPLICATION->AuthForm(Loc::getMessage('ACCESS_DENIED'));
}
Loc::loadMessages(__FILE__);

$moduleId = 'kk.pricewatch';
$errors = [];
$agentInstaller = new NotificationAgentInstaller();
$intervalValidator = new NotificationAgentIntervalValidator();

$sites = [];
$siteQuery = SiteTable::getList(['select' => ['LID', 'NAME'], 'filter' => ['=ACTIVE' => 'Y'], 'order' => ['SORT' => 'ASC']]);
while ($site = $siteQuery->fetch()) {
    $sites[(string) $site['LID']] = (string) $site['NAME'];
}

try {
    $agentState = $agentInstaller->getState();
} catch (\Throwable) {
    $errors[] = Loc::getMessage('KK_PRICEWATCH_NOTIFY_AGENT_CHANGE_FAILED');
    $agentState = new NotificationAgentState(false, NotificationAgentInstaller::DEFAULT_INTERVAL_SECONDS, null, null);
}

$values = [
    'enabled' => Option::get($moduleId, 'notifications_enabled', 'N'),
    'emails' => Option::get($moduleId, 'notification_emails', ''),
    'site' => Option::get($moduleId, 'notification_site_id', ''),
    'recovery' => Option::get($moduleId, 'send_recovery', 'Y'),
    'agent_active' => $agentState->active,
    'agent_interval' => (string) intdiv($agentState->intervalSeconds, 60),
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save']) && check_bitrix_sessid()) {
    $enabled = isset($_POST['notifications_enabled']) ? 'Y' : 'N';
    $recovery = isset($_POST['send_recovery']) ? 'Y' : 'N';
    $agentActive = isset($_POST['notification_agent_active']);
    $agentIntervalRaw = trim((string) ($_POST['notification_agent_interval'] ?? ''));
    $agentIntervalMinutes = $intervalValidator->validate($agentIntervalRaw);
    $rawEmails = (string) ($_POST['notification_emails'] ?? '');
    $emails = NotificationSettings::normalizeEmails($rawEmails);
    $tokens = preg_split('/[,;\s]+/', trim($rawEmails), -1, PREG_SPLIT_NO_EMPTY) ?: [];
    if (array_filter($tokens, static fn(string $email): bool => filter_var($email, FILTER_VALIDATE_EMAIL) === false) !== []) {
        $errors[] = Loc::getMessage('KK_PRICEWATCH_NOTIFY_INVALID_EMAIL');
    }
    if ($agentIntervalMinutes === null) {
        $errors[] = Loc::getMessage('KK_PRICEWATCH_NOTIFY_AGENT_INVALID_INTERVAL');
    }

    $siteId = trim((string) ($_POST['notification_site_id'] ?? ''));
    $values = [
        'enabled' => $enabled,
        'emails' => $rawEmails,
        'site' => $siteId,
        'recovery' => $recovery,
        'agent_active' => $agentActive,
        'agent_interval' => $agentIntervalRaw,
    ];
    $templateExists = $siteId !== '' && isset($sites[$siteId])
        && (new BitrixNotificationMailTemplateChecker())->existsForSite($siteId);
    $validationMessages = [
        NotificationSettingsValidator::RECIPIENTS_REQUIRED => 'KK_PRICEWATCH_NOTIFY_RECIPIENTS_REQUIRED',
        NotificationSettingsValidator::SITE_REQUIRED => 'KK_PRICEWATCH_NOTIFY_SITE_REQUIRED',
        NotificationSettingsValidator::SITE_INACTIVE => 'KK_PRICEWATCH_NOTIFY_INVALID_SITE',
        NotificationSettingsValidator::TEMPLATE_MISSING => 'KK_PRICEWATCH_NOTIFY_TEMPLATE_MISSING',
    ];
    foreach ((new NotificationSettingsValidator())->validate($enabled === 'Y', $emails, $siteId, isset($sites[$siteId]), $templateExists) as $code) {
        $errors[] = Loc::getMessage($validationMessages[$code]);
    }

    if ($errors === []) {
        try {
            $agentInstaller->configure($agentActive, $agentIntervalMinutes * 60);
        } catch (\Throwable) {
            $errors[] = Loc::getMessage('KK_PRICEWATCH_NOTIFY_AGENT_CHANGE_FAILED');
        }
    }
    if ($errors === []) {
        Option::set($moduleId, 'notifications_enabled', $enabled);
        Option::set($moduleId, 'notification_emails', implode(', ', $emails));
        Option::set($moduleId, 'notification_site_id', $siteId);
        Option::set($moduleId, 'send_recovery', $recovery);
        LocalRedirect('kk_pricewatch_notification_settings.php?lang=' . LANGUAGE_ID . '&saved=Y');
    }
}

$APPLICATION->SetTitle(Loc::getMessage('KK_PRICEWATCH_NOTIFY_TITLE'));
require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_after.php';
if (($_GET['saved'] ?? '') === 'Y') {
    CAdminMessage::ShowMessage(['TYPE' => 'OK', 'MESSAGE' => Loc::getMessage('KK_PRICEWATCH_NOTIFY_SAVED')]);
}
foreach ($errors as $error) {
    CAdminMessage::ShowMessage($error);
}
?>
<div class="adm-info-message"><?= htmlspecialcharsbx(Loc::getMessage('KK_PRICEWATCH_NOTIFY_EXECUTION_NOTE')) ?></div>
<form method="post" action="<?= htmlspecialcharsbx($APPLICATION->GetCurPage()) ?>">
<?= bitrix_sessid_post() ?>
<table class="adm-detail-content-table edit-table"><tbody>
<tr class="heading"><td colspan="2"><?= htmlspecialcharsbx(Loc::getMessage('KK_PRICEWATCH_NOTIFY_SETTINGS')) ?></td></tr>
<tr><td width="40%"><?= htmlspecialcharsbx(Loc::getMessage('KK_PRICEWATCH_NOTIFY_ENABLED')) ?></td><td><input type="checkbox" name="notifications_enabled" value="Y"<?= $values['enabled'] === 'Y' ? ' checked' : '' ?>></td></tr>
<tr><td><?= htmlspecialcharsbx(Loc::getMessage('KK_PRICEWATCH_NOTIFY_EMAILS')) ?></td><td><textarea name="notification_emails" rows="4" cols="60"><?= htmlspecialcharsbx($values['emails']) ?></textarea></td></tr>
<tr><td><?= htmlspecialcharsbx(Loc::getMessage('KK_PRICEWATCH_NOTIFY_SITE')) ?></td><td><select name="notification_site_id"><option value=""></option><?php foreach ($sites as $id => $name): ?><option value="<?= htmlspecialcharsbx($id) ?>"<?= $values['site'] === $id ? ' selected' : '' ?>><?= htmlspecialcharsbx($name . ' [' . $id . ']') ?></option><?php endforeach ?></select></td></tr>
<tr><td><?= htmlspecialcharsbx(Loc::getMessage('KK_PRICEWATCH_NOTIFY_RECOVERY')) ?></td><td><input type="checkbox" name="send_recovery" value="Y"<?= $values['recovery'] === 'Y' ? ' checked' : '' ?>></td></tr>
<tr class="heading"><td colspan="2"><?= htmlspecialcharsbx(Loc::getMessage('KK_PRICEWATCH_NOTIFY_AGENT_HEADING')) ?></td></tr>
<tr><td><?= htmlspecialcharsbx(Loc::getMessage('KK_PRICEWATCH_NOTIFY_AGENT_ENABLED')) ?></td><td><input type="checkbox" name="notification_agent_active" value="Y"<?= $values['agent_active'] ? ' checked' : '' ?>></td></tr>
<tr><td><?= htmlspecialcharsbx(Loc::getMessage('KK_PRICEWATCH_NOTIFY_AGENT_INTERVAL')) ?></td><td><input type="text" size="6" name="notification_agent_interval" value="<?= htmlspecialcharsbx($values['agent_interval']) ?>"> <?= htmlspecialcharsbx(Loc::getMessage('KK_PRICEWATCH_NOTIFY_AGENT_MINUTES')) ?></td></tr>
<tr><td><?= htmlspecialcharsbx(Loc::getMessage('KK_PRICEWATCH_NOTIFY_AGENT_STATUS')) ?></td><td><?= htmlspecialcharsbx(Loc::getMessage($agentState->active ? 'KK_PRICEWATCH_NOTIFY_AGENT_ACTIVE' : 'KK_PRICEWATCH_NOTIFY_AGENT_INACTIVE')) ?></td></tr>
<tr><td><?= htmlspecialcharsbx(Loc::getMessage('KK_PRICEWATCH_NOTIFY_AGENT_LAST_EXECUTION')) ?></td><td><?= htmlspecialcharsbx($agentState->lastExecution ?? '—') ?></td></tr>
<tr><td><?= htmlspecialcharsbx(Loc::getMessage('KK_PRICEWATCH_NOTIFY_AGENT_NEXT_EXECUTION')) ?></td><td><?= htmlspecialcharsbx($agentState->nextExecution ?? '—') ?></td></tr>
</tbody></table>
<input type="submit" class="adm-btn-save" name="save" value="<?= htmlspecialcharsbx(Loc::getMessage('MAIN_SAVE')) ?>">
</form>
<?php require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_admin.php';
