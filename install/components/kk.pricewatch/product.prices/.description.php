<?php

declare(strict_types=1);

use Bitrix\Main\Localization\Loc;

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) die();
Loc::loadMessages(__FILE__);

$arComponentDescription = [
    'NAME' => Loc::getMessage('KK_PRICEWATCH_PRODUCT_PRICES_NAME'),
    'DESCRIPTION' => Loc::getMessage('KK_PRICEWATCH_PRODUCT_PRICES_DESCRIPTION'),
    'PATH' => ['ID' => 'kk.pricewatch', 'NAME' => 'kk.pricewatch'],
];
