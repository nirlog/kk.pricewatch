<?php
declare(strict_types=1);
namespace KK\PriceWatch\Collector\External;
use Bitrix\Main\Config\Option;
final class BitrixExternalCollectorSettingsProvider implements ExternalCollectorSettingsProviderInterface
{
    public function get(): ExternalCollectorSettings
    {
        $timeouts = ExternalCollectorTimeouts::fromStrings(
            Option::get('kk.pricewatch', 'external_collector_connect_timeout', '5'),
            Option::get('kk.pricewatch', 'external_collector_request_timeout', '60'),
        );
        return new ExternalCollectorSettings(
            Option::get('kk.pricewatch', 'external_collector_enabled', 'N') === 'Y',
            Option::get('kk.pricewatch', 'external_collector_base_url', ''),
            Option::get('kk.pricewatch', 'external_collector_token', ''),
            $timeouts->connect,
            $timeouts->request,
        );
    }
}
