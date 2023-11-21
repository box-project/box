<?php

declare(strict_types=1);

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

        self::$boxExecutable = getenv(BOX_BIN) ?: $_SERVER['SCRIPT_NAME'];;

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