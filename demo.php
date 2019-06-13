<?php

declare(strict_types=1);

use function Humbug\PhpScoper\json_decode;
use function KevinGH\Box\FileSystem\file_contents;
use function KevinGH\Box\parallel_map;

require __DIR__.'/vendor/autoload.php';

$config = KevinGH\Box\Configuration\Configuration::create(
    __DIR__.'/requirement-checker/box.json.dist',
    json_decode(
        file_get_contents(__DIR__.'/requirement-checker/box.json.dist')
    )
);

$mapFile = $config->getFileMapper();
$compactors = $config->getCompactors();
$cwd = getcwd();

$processFile = static function (string $file) use ($cwd, $mapFile, $compactors): array {
    chdir($cwd);

    // Keep the fully qualified call here since this function may be executed without the right autoloading
    // mechanism
    \KevinGH\Box\register_aliases();
    if (true === \KevinGH\Box\is_parallel_processing_enabled()) {
        \KevinGH\Box\register_error_handler();
    }

    $contents = file_contents($file);

    $local = $mapFile($file);

    $processedContents = $compactors->compact($local, $contents);

    return [$local, $processedContents, $compactors->getScoperWhitelist()];
};

$files = [__DIR__.'/requirement-checker/composer.json'];

parallel_map($files, $processFile);
