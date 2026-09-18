<?php
declare(strict_types=1);
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) die();
use KK\PriceWatch\Installer\AdminInstaller;
use KK\PriceWatch\Installer\NotificationAgentInstaller;
use KK\PriceWatch\Installer\NotificationMailInstaller;
use KK\PriceWatch\Installer\SchemaInstaller;
$moduleDirectory = is_file(__DIR__ . '/include.php') ? __DIR__ : dirname(__DIR__, 3);
require_once $moduleDirectory . '/include.php';
(new SchemaInstaller())->install();
(new AdminInstaller())->install($moduleDirectory . '/install', $_SERVER['DOCUMENT_ROOT']);
(new NotificationAgentInstaller())->install();
(new NotificationMailInstaller())->install();
