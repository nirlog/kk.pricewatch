<?php

declare(strict_types=1);

namespace KK\PriceWatch\Installer;

use RuntimeException;

final class AdminInstaller
{
    public function install(string $moduleInstallDirectory, string $documentRoot): void
    {
        if (!CopyDirFiles($moduleInstallDirectory . '/admin', $documentRoot . '/bitrix/admin', true, true)) {
            throw new RuntimeException('Could not install kk.pricewatch admin entry points.');
        }
    }
}
