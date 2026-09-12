<?php

declare(strict_types=1);

use KK\PriceWatch\Installer\ScheduledAgentInstaller;

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}

require_once dirname(__DIR__, 3) . '/include.php';

// This is also safe when an administrator has disabled an existing agent:
// install() retains the first exact match without changing its ACTIVE value.
(new ScheduledAgentInstaller())->install();
