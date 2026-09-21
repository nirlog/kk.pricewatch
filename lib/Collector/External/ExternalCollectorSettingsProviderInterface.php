<?php
declare(strict_types=1);
namespace KK\PriceWatch\Collector\External;
interface ExternalCollectorSettingsProviderInterface { public function get(): ExternalCollectorSettings; }
