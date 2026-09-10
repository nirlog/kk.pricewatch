<?php

use Bitrix\Main\Localization\Loc;
use Bitrix\Main\ModuleManager;
use KK\PriceWatch\Installer\SchemaInstaller;

require_once dirname(__DIR__) . '/include.php';

Loc::loadMessages(__FILE__);

class kk_pricewatch extends CModule
{
    public $MODULE_ID = 'kk.pricewatch';
    public $MODULE_VERSION;
    public $MODULE_VERSION_DATE;
    public $MODULE_NAME;
    public $MODULE_DESCRIPTION;

    public function __construct()
    {
        $version = [];
        include __DIR__ . '/version.php';

        if (isset($arModuleVersion) && is_array($arModuleVersion)) {
            $version = $arModuleVersion;
        }

        $this->MODULE_VERSION = $version['VERSION'] ?? '0.0.0';
        $this->MODULE_VERSION_DATE = $version['VERSION_DATE'] ?? '';
        $this->MODULE_NAME = Loc::getMessage('KK_PRICEWATCH_MODULE_NAME') ?: 'Price Watch';
        $this->MODULE_DESCRIPTION = Loc::getMessage('KK_PRICEWATCH_MODULE_DESCRIPTION') ?: 'Competitor price monitoring';
        $this->PARTNER_NAME = Loc::getMessage('KK_PRICEWATCH_PARTNER_NAME') ?: 'KK';
        $this->PARTNER_URI = '';
    }

    public function DoInstall(): void
    {
        (new SchemaInstaller())->install();
        ModuleManager::registerModule($this->MODULE_ID);
    }

    public function DoUninstall(): void
    {
        // Module-owned data is intentionally preserved for a safe reinstall.
        ModuleManager::unRegisterModule($this->MODULE_ID);
    }
}
