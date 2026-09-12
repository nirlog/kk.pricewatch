#!/usr/bin/env php
<?php

declare(strict_types=1);

use Bitrix\Main\Loader;
use KK\PriceWatch\Scheduling\ScheduledPriceUpdateRunnerFactory;

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit(2);
}

/** @return never */
function pricewatchFail(string $code, int $status = 2): void
{
    fwrite(STDERR, json_encode(['error' => $code], JSON_UNESCAPED_SLASHES) . PHP_EOL);
    exit($status);
}

$options = getopt('', ['batch-size:', 'document-root:']);
$batchSizeValue = $options['batch-size'] ?? '100';
if (!is_string($batchSizeValue) || preg_match('/^[1-9][0-9]*$/D', $batchSizeValue) !== 1) {
    pricewatchFail('INVALID_BATCH_SIZE');
}
$batchSize = (int) $batchSizeValue;
if ($batchSize > 1000) {
    pricewatchFail('INVALID_BATCH_SIZE');
}

$documentRoot = $options['document-root'] ?? ($_SERVER['DOCUMENT_ROOT'] ?? '');
if (!is_string($documentRoot) || $documentRoot === '') {
    // The installed module is normally <document-root>/local/modules/kk.pricewatch/bin.
    $documentRoot = dirname(__DIR__, 4);
}
$documentRoot = rtrim($documentRoot, '/\\');
$prolog = $documentRoot . '/bitrix/modules/main/include/prolog_before.php';
if (!is_dir($documentRoot) || !is_file($prolog)) {
    pricewatchFail('INVALID_DOCUMENT_ROOT');
}
$_SERVER['DOCUMENT_ROOT'] = $documentRoot;

define('NO_KEEP_STATISTIC', true);
define('NOT_CHECK_PERMISSIONS', true);
define('BX_NO_ACCELERATOR_RESET', true);

try {
    require $prolog;
    if (!Loader::includeModule('kk.pricewatch')) {
        pricewatchFail('MODULE_LOAD_FAILED');
    }
    $result = ScheduledPriceUpdateRunnerFactory::createDefault()->run($batchSize);
    echo json_encode($result->toArray(), JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL;
    exit($result->globalFailure ? 1 : 0);
} catch (Throwable) {
    pricewatchFail('RUNNER_FAILED', 1);
}
