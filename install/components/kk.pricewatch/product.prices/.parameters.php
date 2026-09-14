<?php

declare(strict_types=1);

use Bitrix\Main\Localization\Loc;

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) die();
Loc::loadMessages(__FILE__);

$arComponentParameters = [
    'PARAMETERS' => [
        'PRODUCT_ID' => ['PARENT' => 'BASE', 'NAME' => Loc::getMessage('KK_PRICEWATCH_PRODUCT_ID'), 'TYPE' => 'STRING'],
        'MAX_AGE_SECONDS' => ['PARENT' => 'BASE', 'NAME' => Loc::getMessage('KK_PRICEWATCH_MAX_AGE'), 'TYPE' => 'STRING', 'DEFAULT' => '86400'],
    ],
];
