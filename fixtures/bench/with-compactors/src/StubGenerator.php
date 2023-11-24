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

namespace BenchTest;

use function addcslashes;
use function implode;
use function str_replace;

/**
 * Generates a new PHP bootstrap loader stub for a PHAR.
 *
 * @private
 */
final class StubGenerator
{
    use NotInstantiable;

    private const CHECK_FILE_NAME = 'bin/check-requirements.php';

    private const STUB_TEMPLATE = <<<'STUB'
        __BOX_SHEBANG__
        <?php
        __BOX_BANNER__

        __BOX_PHAR_CONFIG__

        __HALT_COMPILER(); ?>

        STUB;

    /**
     * @param null|string           $alias     The alias to be used in "phar://" URLs
     * @param null|string           $banner    The top header comment banner text
     * @param null|string           $index     The location within the PHAR of index script
     * @param bool                  $intercept Use the Phar::interceptFileFuncs() method?
     * @param null|non-empty-string $shebang   The shebang line
     */
    public static function generateStub(
        ?string $alias = null,
        ?string $banner = null,
        ?string $index = null,
        bool $intercept = false,
        ?string $shebang = null,
        bool $checkRequirements = true,
    ): string {
        $stub = self::STUB_TEMPLATE;

        $stub = str_replace(
            "__BOX_SHEBANG__\n",
            null === $shebang ? '' : $shebang."\n",
            $stub,
        );

        $stub = str_replace(
            "__BOX_BANNER__\n",
            self::generateBannerStmt($banner),
            $stub,
        );

        return str_replace(
            "__BOX_PHAR_CONFIG__\n",
            self::generatePharConfigStmt(
                $alias,
                $index,
                $intercept,
                $checkRequirements,
            ),
            $stub,
        );
    }

    private static function generateBannerStmt(?string $banner): string
    {
        if (null === $banner) {
            return '';
        }

        $generatedBanner = "/*\n * ";

        $generatedBanner .= str_replace(
            " \n",
            "\n",
            str_replace("\n", "\n * ", $banner),
        );

        $generatedBanner .= "\n */";

        return "\n".$generatedBanner."\n";
    }

    private static function getAliasStmt(?string $alias): ?string
    {
        return null !== $alias ? 'Phar::mapPhar('.self::arg($alias).');' : null;
    }

    /**
     * Escapes an argument so it can be written as a string in a call.
     *
     * @return string The escaped argument
     */
    private static function arg(string $arg, string $quote = "'"): string
    {
        return $quote.addcslashes($arg, $quote).$quote;
    }

    private static function generatePharConfigStmt(
        ?string $alias = null,
        ?string $index = null,
        bool $intercept = false,
        bool $checkRequirements = true,
    ): string {
        $previous = false;
        $stub = [];
        $aliasStmt = self::getAliasStmt($alias);

        if (null !== $aliasStmt) {
            $stub[] = $aliasStmt;

            $previous = true;
        }

        if ($intercept) {
            $stub[] = 'Phar::interceptFileFuncs();';

            $previous = true;
        }

        if (false !== $checkRequirements) {
            if ($previous) {
                $stub[] = '';
            }

            $checkRequirementsFile = self::CHECK_FILE_NAME;

            $stub[] = null === $alias
                ? "require 'phar://' . __FILE__ . '/.box/{$checkRequirementsFile}';"
                : "require 'phar://{$alias}/.box/{$checkRequirementsFile}';";

            $previous = true;
        }

        if (null !== $index) {
            if ($previous) {
                $stub[] = '';
            }

            $stub[] = null === $alias
                ? "require 'phar://' . __FILE__ . '/{$index}';"
                : "require 'phar://{$alias}/{$index}';";
        }

        if ([] === $stub) {
            return "// No PHAR config\n";
        }

        return implode("\n", $stub)."\n";
    }
}
