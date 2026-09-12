<?php

declare(strict_types=1);

namespace KK\PriceWatch\Admin;

use Bitrix\Main\Context;
use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;
use KK\PriceWatch\Model\ProductCompetitorTable;

final class ProductEditTabHandler
{
    private const TAB_ID = 'kk_pricewatch_product_competitors';
    private const SUPPORTED_SCRIPTS = ['iblock_element_edit.php', 'cat_product_edit.php'];

    public static function onAdminTabControlBegin(\CAdminTabControl &$tabControl): void
    {
        global $APPLICATION;

        if (!Loader::includeModule(Access::MODULE_ID) || !Access::canRead()) {
            return;
        }

        $script = basename((string) $APPLICATION->GetCurPage());
        if (!in_array($script, self::SUPPORTED_SCRIPTS, true)) {
            return;
        }

        $request = Context::getCurrent()->getRequest();
        $productId = (int) $request->get('ID');
        if ($productId <= 0 || ProductContext::findCatalogProduct($productId) === null) {
            return;
        }

        Loc::loadMessages(__FILE__);
        self::appendTab($tabControl, [
            'DIV' => self::TAB_ID,
            'TAB' => Loc::getMessage('KK_PRICEWATCH_PRODUCT_TAB'),
            'TITLE' => Loc::getMessage('KK_PRICEWATCH_PRODUCT_TAB_TITLE'),
            'CONTENT' => self::render($productId, Access::canWrite()),
        ]);
    }

    /** @param array<string, mixed> $tab */
    private static function appendTab(\CAdminTabControl $tabControl, array $tab): void
    {
        foreach ($tabControl->tabs as $existingTab) {
            if (is_array($existingTab) && ($existingTab['DIV'] ?? null) === self::TAB_ID) {
                return;
            }
        }

        // CAdminTabControl::AddTabs() accepts a CAdminTabEngine, not a tab array.
        // The supported legacy API exposes this collection publicly.
        $tabControl->tabs[] = $tab;
    }

    private static function render(int $productId, bool $canWrite): string
    {
        $rows = ProductCompetitorTable::getList([
            'select' => [
                'ID', 'ACTIVE', 'URL', 'CURRENT_PRICE', 'CURRENCY', 'STATUS',
                'LAST_CHECK_AT', 'LAST_SUCCESS_AT', 'COMPETITOR_NAME' => 'COMPETITOR.NAME',
            ],
            'filter' => ['=PRODUCT_ID' => $productId],
            'order' => ['ID' => 'ASC'],
        ])->fetchAll();
        $listUrl = 'kk_pricewatch_product_competitors.php?lang=' . rawurlencode((string) LANGUAGE_ID)
            . '&PRODUCT_ID=' . $productId;
        $checkUrl = 'kk_pricewatch_product_price_check.php?lang=' . rawurlencode((string) LANGUAGE_ID)
            . '&MODE=product&PRODUCT_ID=' . $productId;

        ob_start();
        ?>
        <tr>
        <td colspan="2">
            <?php if ($rows === []): ?>
                <p><?= htmlspecialcharsbx((string) Loc::getMessage('KK_PRICEWATCH_PRODUCT_TAB_EMPTY')) ?></p>
            <?php else: ?>
                <table class="adm-list-table"><thead><tr>
                    <th><?= htmlspecialcharsbx((string) Loc::getMessage('KK_PRICEWATCH_PRODUCT_COMPETITOR')) ?></th>
                    <th><?= htmlspecialcharsbx((string) Loc::getMessage('KK_PRICEWATCH_PRODUCT_ACTIVE')) ?></th>
                    <th><?= htmlspecialcharsbx((string) Loc::getMessage('KK_PRICEWATCH_PRODUCT_URL')) ?></th>
                    <th><?= htmlspecialcharsbx((string) Loc::getMessage('KK_PRICEWATCH_PRODUCT_PRICE')) ?></th>
                    <th><?= htmlspecialcharsbx((string) Loc::getMessage('KK_PRICEWATCH_PRODUCT_STATUS')) ?></th>
                    <th><?= htmlspecialcharsbx((string) Loc::getMessage('KK_PRICEWATCH_PRODUCT_LAST_CHECK')) ?></th>
                    <th><?= htmlspecialcharsbx((string) Loc::getMessage('KK_PRICEWATCH_PRODUCT_LAST_SUCCESS')) ?></th>
                </tr></thead><tbody>
                <?php foreach ($rows as $row): ?>
                    <tr>
                        <td><?= htmlspecialcharsbx((string) $row['COMPETITOR_NAME']) ?></td>
                        <td><?= htmlspecialcharsbx((string) $row['ACTIVE']) ?></td>
                        <td><?= self::renderUrl((string) $row['URL']) ?></td>
                        <td><?= htmlspecialcharsbx(trim((string) $row['CURRENT_PRICE'] . ' ' . (string) $row['CURRENCY'])) ?></td>
                        <td><?= htmlspecialcharsbx((string) $row['STATUS']) ?></td>
                        <td><?= htmlspecialcharsbx((string) $row['LAST_CHECK_AT']) ?></td>
                        <td><?= htmlspecialcharsbx((string) $row['LAST_SUCCESS_AT']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody></table>
            <?php endif; ?>
            <p><a class="adm-btn<?= $canWrite ? ' adm-btn-save' : '' ?>" href="<?= htmlspecialcharsbx($listUrl) ?>"><?= htmlspecialcharsbx((string) Loc::getMessage($canWrite ? 'KK_PRICEWATCH_PRODUCT_MANAGE' : 'KK_PRICEWATCH_PRODUCT_VIEW')) ?></a></p>
            <?php if ($canWrite): ?><p><a class="adm-btn" href="<?= htmlspecialcharsbx($checkUrl) ?>"><?= htmlspecialcharsbx((string) Loc::getMessage('KK_PRICEWATCH_PRODUCT_CHECK_ACTIVE')) ?></a></p><?php endif; ?>
        </td>
        </tr>
        <?php
        return (string) ob_get_clean();
    }

    private static function renderUrl(string $url): string
    {
        $escaped = htmlspecialcharsbx($url);
        if (!ProductCompetitorLinkService::isAcceptedHttpUrl($url)) {
            return $escaped;
        }
        return '<a href="' . $escaped . '" target="_blank" rel="noopener noreferrer">' . $escaped . '</a>';
    }
}
