<?php

$documentRoot = (string) $_SERVER['DOCUMENT_ROOT'];
$relative = '/modules/kk.pricewatch/admin/competitor_edit.php';
$moduleFile = $documentRoot . '/local' . $relative;
if (!is_file($moduleFile)) {
    $moduleFile = $documentRoot . '/bitrix' . $relative;
}
require $moduleFile;
