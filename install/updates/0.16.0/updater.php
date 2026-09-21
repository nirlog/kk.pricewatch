<?php

declare(strict_types=1);

use KK\PriceWatch\Installer\NotificationAgentInstaller;

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) die();
$moduleRoot = is_file(__DIR__ . '/include.php') ? __DIR__ : dirname(__DIR__, 3);
require_once $moduleRoot . '/include.php';

// Idempotently preserve the primary Agent state and interval, create a missing
// Agent inactive, normalize an invalid interval, and remove exact duplicates.
(new NotificationAgentInstaller())->install();
