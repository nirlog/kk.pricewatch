<?php

use Bitrix\Main\Localization\Loc;
use Bitrix\Main\ModuleManager;
use Bitrix\Main\EventManager;
use KK\PriceWatch\Admin\ProductEditTabHandler;
use KK\PriceWatch\Installer\AdminInstaller;
use KK\PriceWatch\Installer\ComponentInstaller;
use KK\PriceWatch\Installer\SchemaInstaller;
use KK\PriceWatch\Installer\ScheduledAgentInstaller;

require_once dirname(__DIR__) . '/include.php';

Loc::loadMessages(__FILE__);

class kk_pricewatch extends CModule
{
    public $MODULE_GROUP_RIGHTS = 'Y';
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
        (new AdminInstaller())->install(__DIR__, $_SERVER['DOCUMENT_ROOT']);
        (new ComponentInstaller())->install(__DIR__, $_SERVER['DOCUMENT_ROOT']);
        ModuleManager::registerModule($this->MODULE_ID);
        (new ScheduledAgentInstaller())->install();
        $events = EventManager::getInstance();
        $events->unRegisterEventHandler('main', 'OnAdminTabControlBegin', $this->MODULE_ID, ProductEditTabHandler::class, 'onAdminTabControlBegin');
        $events->registerEventHandler('main', 'OnAdminTabControlBegin', $this->MODULE_ID, ProductEditTabHandler::class, 'onAdminTabControlBegin');
    }

    public function DoUninstall(): void
    {
        (new ScheduledAgentInstaller())->uninstall();
        (new ComponentInstaller())->uninstall($_SERVER['DOCUMENT_ROOT']);
        EventManager::getInstance()->unRegisterEventHandler(
            'main', 'OnAdminTabControlBegin', $this->MODULE_ID, ProductEditTabHandler::class, 'onAdminTabControlBegin'
        );
        $adminDirectory = $_SERVER['DOCUMENT_ROOT'] . '/bitrix/admin';
        DeleteDirFiles(__DIR__ . '/admin', $adminDirectory);

        foreach ([
            'kk_pricewatch_competitors.php',
            'kk_pricewatch_competitor_edit.php',
            'kk_pricewatch_product_competitors.php',
            'kk_pricewatch_product_competitor_edit.php',
            'kk_pricewatch_product_price_check.php',
            'kk_pricewatch_price_history.php',
            'kk_pricewatch_monitoring.php',
        ] as $proxyFile) {
            $proxyPath = $adminDirectory . '/' . $proxyFile;
            clearstatcache(true, $proxyPath);
            if (is_file($proxyPath)) {
                throw new RuntimeException('Could not remove kk.pricewatch admin entry point: ' . $proxyFile);
            }
        }

        // Module-owned data is intentionally preserved for a safe reinstall.
        ModuleManager::unRegisterModule($this->MODULE_ID);
    }

    public function GetModuleRightList(): array
    {
        return [
            'reference_id' => ['D', 'R', 'W'],
            'reference' => [
                Loc::getMessage('KK_PRICEWATCH_RIGHT_D'),
                Loc::getMessage('KK_PRICEWATCH_RIGHT_R'),
                Loc::getMessage('KK_PRICEWATCH_RIGHT_W'),
            ],
        ];
    }
}
