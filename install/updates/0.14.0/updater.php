<?php

declare(strict_types=1);

use KK\PriceWatch\Installer\AdminInstaller;

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) die();
$moduleRoot = is_file(__DIR__ . '/include.php') ? __DIR__ : dirname(__DIR__, 3);
require_once $moduleRoot . '/include.php';
// Task 023 adds admin/read-only runtime files only. No schema or business data is changed.
(new AdminInstaller())->install($moduleRoot . '/install', $_SERVER['DOCUMENT_ROOT']);
