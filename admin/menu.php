<?php

use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;
use KK\PriceWatch\Admin\Access;

if (!Loader::includeModule('kk.pricewatch') || !Access::canRead()) {
    return false;
}

Loc::loadMessages(__FILE__);

return [
    'parent_menu' => 'global_menu_services',
    'section' => 'kk_pricewatch',
    'sort' => 500,
    'text' => Loc::getMessage('KK_PRICEWATCH_MENU_ROOT'),
    'title' => Loc::getMessage('KK_PRICEWATCH_MENU_ROOT_TITLE'),
    'icon' => 'default_menu_icon',
    'page_icon' => 'default_page_icon',
    'items_id' => 'menu_kk_pricewatch',
    'items' => [[
        'text' => Loc::getMessage('KK_PRICEWATCH_MENU_COMPETITORS'),
        'title' => Loc::getMessage('KK_PRICEWATCH_MENU_COMPETITORS_TITLE'),
        'url' => 'kk_pricewatch_competitors.php?lang=' . LANGUAGE_ID,
        'more_url' => ['kk_pricewatch_competitor_edit.php'],
    ]],
];
