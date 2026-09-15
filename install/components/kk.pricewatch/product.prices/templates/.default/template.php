<?php

declare(strict_types=1);

use Bitrix\Main\Localization\Loc;
use Bitrix\Main\Text\HtmlFilter;

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) die();
Loc::loadMessages(__FILE__);

$frame = $this->createFrame()->begin('');

$formatPrice = static function (string $price, string $currency): string {
    [$integer, $fraction] = array_pad(explode('.', $price, 2), 2, '');
    $integer = preg_replace('/(?<=\d)(?=(\d{3})+(?!\d))/', ' ', $integer) ?? $integer;
    $amount = $integer . ($fraction !== '' && trim($fraction, '0') !== '' ? ',' . rtrim($fraction, '0') : '');
    return $amount . ' ' . ($currency === 'RUB' ? '₽' : $currency);
};
$formatTime = static function (mixed $value): string {
    if (is_object($value) && method_exists($value, 'format')) {
        return $value->format('d.m.Y H:i');
    }
    return '';
};
?><?php if (($arResult['ACCESS_ALLOWED'] ?? false) === true && (($arResult['ROWS'] ?? []) !== [] || ($arResult['READ_FAILED'] ?? false) === true)): ?>
<section class="kk-pricewatch-product-prices" aria-label="<?= HtmlFilter::encode((string) Loc::getMessage('KK_PRICEWATCH_HEADING')) ?>">
    <div class="kk-pricewatch-product-prices__staff"><?= HtmlFilter::encode((string) Loc::getMessage('KK_PRICEWATCH_STAFF_ONLY')) ?></div>
    <h3 class="kk-pricewatch-product-prices__heading"><?= HtmlFilter::encode((string) Loc::getMessage('KK_PRICEWATCH_HEADING')) ?></h3>
    <?php if (($arResult['READ_FAILED'] ?? false) === true): ?>
        <p class="kk-pricewatch-product-prices__state"><?= HtmlFilter::encode((string) Loc::getMessage('KK_PRICEWATCH_READ_FAILED')) ?></p>
    <?php else: ?>
    <ul class="kk-pricewatch-product-prices__list">
        <?php foreach ($arResult['ROWS'] as $row): ?>
            <li class="kk-pricewatch-product-prices__item">
                <strong><?= HtmlFilter::encode($row['competitor_name']) ?></strong>
                <span>
                    <?php if ($row['current_price'] !== null): ?>
                        <?= HtmlFilter::encode($formatPrice($row['current_price'], $row['currency'])) ?>
                    <?php else: ?>
                        <?= HtmlFilter::encode((string) Loc::getMessage('KK_PRICEWATCH_NO_PRICE')) ?>
                    <?php endif; ?>
                </span>
                <?php if ($row['status'] === 'error'): ?>
                    <span class="kk-pricewatch-product-prices__state"><?= HtmlFilter::encode((string) Loc::getMessage('KK_PRICEWATCH_ERROR')) ?></span>
                <?php elseif ($row['is_stale']): ?>
                    <span class="kk-pricewatch-product-prices__state"><?= HtmlFilter::encode((string) Loc::getMessage('KK_PRICEWATCH_STALE')) ?></span>
                <?php endif; ?>
                <?php $updated = $formatTime($row['last_success_at']); if ($updated !== ''): ?>
                    <span><?= HtmlFilter::encode((string) Loc::getMessage('KK_PRICEWATCH_UPDATED')) ?> <?= HtmlFilter::encode($updated) ?></span>
                <?php endif; ?>
                <?php if ($row['is_url_safe']): ?>
                    <a href="<?= HtmlFilter::encode($row['exact_url']) ?>" target="_blank" rel="noopener noreferrer"><?= HtmlFilter::encode((string) Loc::getMessage('KK_PRICEWATCH_OPEN')) ?></a>
                <?php endif; ?>
            </li>
        <?php endforeach; ?>
    </ul>
    <?php if (($arResult['HAS_MORE'] ?? false) === true): ?>
        <p class="kk-pricewatch-product-prices__notice"><?= HtmlFilter::encode(str_replace('#COUNT#', (string) ($arResult['MAX_ROWS'] ?? 50), (string) Loc::getMessage('KK_PRICEWATCH_HAS_MORE'))) ?></p>
    <?php endif; ?>
    <?php endif; ?>
</section>
<?php endif; $frame->end(); ?>
