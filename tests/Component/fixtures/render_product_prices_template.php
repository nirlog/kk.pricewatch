<?php

declare(strict_types=1);

namespace Bitrix\Main\Text {
    final class HtmlFilter
    {
        public static function encode(string $value): string
        {
            return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        }
    }
}

namespace Bitrix\Main\Localization {
    final class Loc
    {
        public static function loadMessages(string $file): void {}
        public static function getMessage(string $key): string { return $key; }
    }
}

namespace {
    use KK\PriceWatch\Model\ProductUrl;

    final class TestCompositeFrame
    {
        public function begin(string $stub): self { return $this; }
        public function end(): void {}
    }

    final class TestComponentTemplate
    {
        public function createFrame(): TestCompositeFrame { return new TestCompositeFrame(); }

        public function render(array $arResult): void
        {
            include dirname(__DIR__, 3) . '/install/components/kk.pricewatch/product.prices/templates/.default/template.php';
        }
    }

    define('B_PROLOG_INCLUDED', true);
    require dirname(__DIR__, 3) . '/lib/Model/ProductUrl.php';
    $url = (string) ($argv[1] ?? '');
    $mode = $argv[2] ?? 'allowed';
    $accessAllowed = in_array($mode, ['allowed', 'failed'], true);
    $readFailed = in_array($mode, ['failed', 'denied-failed'], true);
    $arResult = ['ACCESS_ALLOWED' => $accessAllowed, 'READ_FAILED' => $readFailed, 'HAS_MORE' => false, 'MAX_ROWS' => 50, 'ROWS' => $readFailed ? [] : [[
        'competitor_name' => 'Test', 'current_price' => null, 'currency' => null,
        'status' => 'new', 'is_stale' => false, 'last_success_at' => null,
        'exact_url' => $url, 'is_url_safe' => ProductUrl::isAcceptedHttpUrl($url),
    ]]];
    (new TestComponentTemplate())->render($arResult);
}
