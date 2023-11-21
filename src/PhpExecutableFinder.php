<?php

declare(strict_types=1);

namespace KevinGH\Box;

use RuntimeException;
use Symfony\Component\Process\PhpExecutableFinder as SymfonyPhpExecutableFinder;

final class PhpExecutableFinder
{
    private static string $phpExecutable;

    public static function find(): string
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