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

namespace KevinGH\Box;

use RuntimeException;
use Symfony\Component\Process\PhpExecutableFinder as SymfonyPhpExecutableFinder;

final class ExecutableFinder
{
    private static string $boxExecutable;
    private static string $phpExecutable;

    public static function findBoxExecutable(): string
    {
        if (isset(self::$boxExecutable)) {
            return self::$boxExecutable;
        }

        self::$boxExecutable = getenv(Constants::BIN) ?: $_SERVER['SCRIPT_NAME'];

        return self::$boxExecutable;
    }

    public static function findPhpExecutable(): string
    {
        if (isset(self::$phpExecutable)) {
            return self::$phpExecutable;
        }

        $phpExecutable = (new SymfonyPhpExecutableFinder())->find();

        if (false === $phpExecutable) {
            throw new RuntimeException('Could not find a PHP executable.');
        }

        self::$phpExecutable = $phpExecutable;

        return self::$phpExecutable;
    }
}
