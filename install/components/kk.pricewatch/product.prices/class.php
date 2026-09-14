<?php

declare(strict_types=1);

use Bitrix\Main\Loader;
use KK\PriceWatch\Admin\Access;
use KK\PriceWatch\Service\StaffProductPriceReadService;

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}

final class KkPriceWatchProductPricesComponent extends CBitrixComponent
{
    public function executeComponent(): void
    {
        $productId = filter_var($this->arParams['PRODUCT_ID'] ?? null, FILTER_VALIDATE_INT);
        if ($productId === false || $productId <= 0) {
            return;
        }

        $this->setFrameMode(true);
        $this->arResult = [
            'ACCESS_ALLOWED' => false,
            'ROWS' => [],
        ];

        if (Loader::includeModule('kk.pricewatch') && Access::canRead()) {
            // Authorization above must remain before construction/execution of the ORM read service.
            $maxAge = filter_var($this->arParams['MAX_AGE_SECONDS'] ?? 86400, FILTER_VALIDATE_INT);
            $maxAge = $maxAge === false ? 86400 : max(0, $maxAge);
            $this->arResult['ACCESS_ALLOWED'] = true;
            $this->arResult['ROWS'] = (new StaffProductPriceReadService())->read($productId, $maxAge);
        }

        // The template owns the composite frame and must run for every valid product ID,
        // including module-load failures, denied requests, and empty authorized results.
        $this->includeComponentTemplate();
    }
}
