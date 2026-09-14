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

        // Always create the composite dynamic area, including for denied requests.
        // Its empty stub contains no confidential data; every dynamic render repeats
        // authorization below. No component result cache is used.
        $this->setFrameMode(true);
        $frame = $this->createFrame()->begin('');

        if (!Loader::includeModule('kk.pricewatch')) {
            $frame->end();
            return;
        }

        if (!Access::canRead()) {
            $frame->end();
            return;
        }

        // Authorization above must remain before construction/execution of the ORM read service.
        $maxAge = filter_var($this->arParams['MAX_AGE_SECONDS'] ?? 86400, FILTER_VALIDATE_INT);
        $maxAge = $maxAge === false ? 86400 : max(0, $maxAge);
        $rows = (new StaffProductPriceReadService())->read($productId, $maxAge);
        if ($rows === []) {
            $frame->end();
            return;
        }

        $this->arResult = ['ROWS' => $rows];
        $this->includeComponentTemplate();
        $frame->end();
    }
}
