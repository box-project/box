<?php

declare(strict_types=1);

function patch_file(string $filePath, callable $patch): void {
    if (!file_exists($filePath)) {
        return;
    }

    $contents = file_get_contents($filePath);

    $patchedContents = $patch($contents);

    file_put_contents($filePath, $patchedContents, LOCK_EX);
}

function remove_phpscoper_hotfix_references(): void {
    $files = [
        __DIR__.'/vendor/composer/autoload_classmap.php',
        __DIR__.'/vendor/composer/autoload_static.php',
    ];

    foreach ($files as $file) {
        patch_file(
            $file,
            static function (string $contents): string {
                $lines = explode("\n", $contents);

                $fixedLines = array_filter(
                    $lines,
                    static fn (string $line) => false === strpos($line, 'humbug/php-scoper/vendor-hotfix'),
                );

                return implode("\n", $fixedLines);
            },
        );
    }
}

function remove_installed_phpscoper_hotfix_references(): void {
    $files = [
        __DIR__.'/vendor/composer/installed.json',
        __DIR__.'/vendor/composer/installed.php',
    ];

    foreach ($files as $file) {
        patch_file(
            $file,
            static fn (string $contents) => str_replace(
                [
                    '"vendor-hotfix/",',
                    '"vendor-hotfix/"',
                ],
                ['', ''],
                $contents,
            ),
        );
    }
}

remove_phpscoper_hotfix_references();
remove_installed_phpscoper_hotfix_references();
