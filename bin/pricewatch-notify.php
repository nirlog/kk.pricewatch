#!/usr/bin/env php
<?php
declare(strict_types=1);
use Bitrix\Main\Loader;
use KK\PriceWatch\Notification\NotificationRunnerFactory;
if (PHP_SAPI !== 'cli') { http_response_code(404); exit(2); }
function pricewatchNotifyFail(string $code, int $status = 2): never { fwrite(STDERR, json_encode(['error' => $code]) . PHP_EOL); exit($status); }
$options = getopt('', ['batch-size:', 'document-root:']);
$batch = $options['batch-size'] ?? '100';
if (!is_string($batch) || preg_match('/^[1-9][0-9]*$/D', $batch) !== 1 || (int) $batch > 1000) pricewatchNotifyFail('INVALID_BATCH_SIZE');
$root = $options['document-root'] ?? ($_SERVER['DOCUMENT_ROOT'] ?? '');
if (!is_string($root) || $root === '') $root = dirname(__DIR__, 4);
$root = rtrim($root, '/\\'); $prolog = $root . '/bitrix/modules/main/include/prolog_before.php';
if (!is_file($prolog)) pricewatchNotifyFail('INVALID_DOCUMENT_ROOT');
$_SERVER['DOCUMENT_ROOT'] = $root; define('NO_KEEP_STATISTIC', true); define('NOT_CHECK_PERMISSIONS', true); define('BX_NO_ACCELERATOR_RESET', true);
try { require $prolog; if (!Loader::includeModule('kk.pricewatch')) pricewatchNotifyFail('MODULE_LOAD_FAILED');
    $result = NotificationRunnerFactory::createDefault()->run((int) $batch); echo json_encode($result->toArray(), JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL; exit($result->isSuccessful() ? 0 : 1);
} catch (Throwable) { pricewatchNotifyFail('RUNNER_FAILED', 1); }
