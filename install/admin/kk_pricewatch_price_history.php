<?php
$local = $_SERVER['DOCUMENT_ROOT'] . '/local/modules/kk.pricewatch/admin/price_history.php';
require is_file($local) ? $local : $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/kk.pricewatch/admin/price_history.php';
