<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This command is available only from the CLI.\n");
    exit(1);
}

$root = dirname(__DIR__);
$version = $argv[1] ?? '';
$output = $argv[2] ?? '';
$updater = $root . '/install/updates/' . $version . '/updater.php';

if (!preg_match('/^\d+\.\d+\.\d+$/D', $version) || !is_file($updater)) {
    fwrite(STDERR, "Usage: php bin/build-update-package.php VERSION OUTPUT.tar.gz\n");
    exit(1);
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
$runtimePaths = ['admin', 'install/admin', 'lang', 'lib'];
$runtimeFiles = ['include.php', 'install/index.php', 'install/version.php', 'bin/pricewatch-run.php'];

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

// Bitrix runs this reserved file from the update archive root after extraction.
$archive->addFile($updater, 'updater.php');
$archive->compress(\Phar::GZ);
unset($archive);
@unlink($tarPath);

fwrite(STDOUT, $output . "\n");
