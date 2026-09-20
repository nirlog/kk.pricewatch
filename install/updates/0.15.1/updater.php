<?php

declare(strict_types=1);

use KK\PriceWatch\Installer\NotificationMailInstaller;

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) die();
$moduleRoot = is_file(__DIR__ . '/include.php') ? __DIR__ : dirname(__DIR__, 3);
require_once $moduleRoot . '/include.php';

// Idempotently repairs empty 0.15.0 templates and leaves non-empty administrator customizations intact.
(new NotificationMailInstaller())->install();
