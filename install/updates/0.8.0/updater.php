<?php

declare(strict_types=1);

use KK\PriceWatch\Installer\ScheduledAgentInstaller;

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}

$moduleRoot = is_file(__DIR__ . '/include.php') ? __DIR__ : dirname(__DIR__, 3);
require_once $moduleRoot . '/include.php';

// This is also safe when an administrator has disabled an existing agent:
// install() retains the first exact match without changing its ACTIVE value.
(new ScheduledAgentInstaller())->install();
