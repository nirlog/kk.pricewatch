<?php
declare(strict_types=1);
namespace KK\PriceWatch\Collector\External;
use Bitrix\Main\Config\Option;
final class BitrixExternalCollectorSettingsProvider implements ExternalCollectorSettingsProviderInterface
{
    public function get(): ExternalCollectorSettings
    {
        return new ExternalCollectorSettings(
            Option::get('kk.pricewatch', 'external_collector_enabled', 'N') === 'Y',
            Option::get('kk.pricewatch', 'external_collector_base_url', ''),
            Option::get('kk.pricewatch', 'external_collector_token', ''),
            (int) Option::get('kk.pricewatch', 'external_collector_connect_timeout', '5'),
            (int) Option::get('kk.pricewatch', 'external_collector_request_timeout', '60'),
        );
    }
}
