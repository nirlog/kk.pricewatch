<?php
declare(strict_types=1);
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) die();

use KK\PriceWatch\Installer\AdminInstaller;

$moduleDirectory = is_file(__DIR__ . '/include.php') ? __DIR__ : dirname(__DIR__, 3);
require_once $moduleDirectory . '/include.php';

(new AdminInstaller())->install($moduleDirectory . '/install', $_SERVER['DOCUMENT_ROOT']);
