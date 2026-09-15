<?php

declare(strict_types=1);

use KK\PriceWatch\Installer\ComponentInstaller;
use KK\PriceWatch\Installer\SchemaInstaller;

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) die();
$moduleRoot = is_file(__DIR__ . '/include.php') ? __DIR__ : dirname(__DIR__, 3);
require_once $moduleRoot . '/include.php';
// Additive, idempotent index installation only; existing module data is preserved.
(new SchemaInstaller())->install();
(new ComponentInstaller())->install($moduleRoot . '/install', $_SERVER['DOCUMENT_ROOT']);
