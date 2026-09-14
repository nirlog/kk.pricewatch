<?php

declare(strict_types=1);

namespace KK\PriceWatch\Installer;

use RuntimeException;

final class ComponentInstaller
{
    public const RELATIVE_PATH = '/bitrix/components/kk.pricewatch/product.prices';

    public function install(string $moduleInstallDirectory, string $documentRoot): void
    {
        if (!CopyDirFiles($moduleInstallDirectory . '/components/kk.pricewatch/product.prices', $documentRoot . self::RELATIVE_PATH, true, true)) {
            throw new RuntimeException('Could not install kk.pricewatch product prices component.');
        }
    }

    public function uninstall(string $documentRoot): void
    {
        $path = $documentRoot . self::RELATIVE_PATH;
        if (is_dir($path)) {
            DeleteDirFilesEx(self::RELATIVE_PATH);
            clearstatcache(true, $path);
        }
        if (is_dir($path)) {
            throw new RuntimeException('Could not remove kk.pricewatch product prices component.');
        }
    }
}
