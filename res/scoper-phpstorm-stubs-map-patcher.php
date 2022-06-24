<?php

declare(strict_types=1);

/*
 * This file is part of the box project.
 *
 * (c) Kevin Herrera <kevin@herrera.io>
 *     Théo Fidry <theo.fidry@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

$stubsMapVendorPath = 'vendor/jetbrains/phpstorm-stubs/PhpStormStubsMap.php';
$stubsMapPath = __DIR__.'/../'.$stubsMapVendorPath;
$stubsMapOriginalContent = file_get_contents($stubsMapPath);

if (!preg_match('/class PhpStormStubsMap([\s\S]+)/', $stubsMapOriginalContent, $matches)) {
    throw new InvalidArgumentException('Could not capture the map original content.');
}

$stubsMapClassOriginalContent = $matches[1];

return static function (string $filePath, string $prefix, string $contents)
use (
    $stubsMapVendorPath,
    $stubsMapClassOriginalContent,
): string {
    if ($filePath !== $stubsMapVendorPath) {
        return $contents;
    }

    return preg_replace(
        '/class PhpStormStubsMap([\s\S]+)/',
        'class PhpStormStubsMap'.$stubsMapClassOriginalContent,
        $contents,
    );
};
