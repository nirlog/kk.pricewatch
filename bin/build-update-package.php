<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This command is available only from the CLI.\n");
    exit(1);
}

$root = dirname(__DIR__);
$version = $argv[1] ?? '';
$output = $argv[2] ?? '';

if (!preg_match('/^\d+\.\d+\.\d+$/D', $version)) {
    fwrite(STDERR, "Usage: php bin/build-update-package.php VERSION OUTPUT.tar.gz\n");
    exit(1);
}

$installedVersion = (static function (string $versionFile): ?string {
    $arModuleVersion = [];
    require $versionFile;

    return $arModuleVersion['VERSION'] ?? null;
})($root . '/install/version.php');

if ($version !== $installedVersion) {
    fwrite(STDERR, "Requested version {$version} does not match install/version.php ({$installedVersion}).\n");
    exit(1);
}

$updateSource = $root . '/install/updates/' . $version;
$updateFiles = ['description.ru', 'description.en'];
if (is_file($updateSource . '/updater.php')) {
    array_unshift($updateFiles, 'updater.php');
}
foreach ($updateFiles as $file) {
    if (!is_file($updateSource . '/' . $file)) {
        fwrite(STDERR, "Missing required update-package source: {$updateSource}/{$file}\n");
        exit(1);
    }
}

if (!str_ends_with($output, '.tar.gz')) {
    fwrite(STDERR, "The output filename must end with .tar.gz.\n");
    exit(1);
}

$output = str_starts_with($output, '/') ? $output : getcwd() . '/' . $output;
$tarPath = substr($output, 0, -3);
$outputDirectory = dirname($output);
if (!is_dir($outputDirectory) && !mkdir($outputDirectory, 0775, true) && !is_dir($outputDirectory)) {
    fwrite(STDERR, "Unable to create output directory: {$outputDirectory}\n");
    exit(1);
}
@unlink($output);
@unlink($tarPath);

$archive = new \PharData($tarPath);
$runtimePaths = ['admin', 'install/admin', 'install/components', 'lang', 'lib'];
$runtimeFiles = ['include.php', 'install/index.php', 'install/version.php', 'bin/pricewatch-run.php', 'bin/pricewatch-notify.php'];

foreach ($runtimePaths as $path) {
    $iterator = new \RecursiveIteratorIterator(
        new \RecursiveDirectoryIterator($root . '/' . $path, \FilesystemIterator::SKIP_DOTS)
    );
    foreach ($iterator as $file) {
        if ($file->isFile()) {
            $archive->addFile($file->getPathname(), substr($file->getPathname(), strlen($root) + 1));
        }
    }
}

foreach ($runtimeFiles as $file) {
    $archive->addFile($root . '/' . $file, $file);
}

// Bitrix consumes these reserved files from the update archive root.
foreach ($updateFiles as $file) {
    $archive->addFile($updateSource . '/' . $file, $file);
}
$archive->compress(\Phar::GZ);
unset($archive);
@unlink($tarPath);

fwrite(STDOUT, $output . "\n");
