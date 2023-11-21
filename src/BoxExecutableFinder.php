<?php

declare(strict_types=1);

namespace KevinGH\Box;

final class BoxExecutableFinder
{
    private static string $boxExecutable;

    public static function find(): string
    {
        if (isset(self::$boxExecutable)) {
            return self::$boxExecutable;
        }

        self::$boxExecutable = getenv(BOX_BIN) ?: $_SERVER['SCRIPT_NAME'];;

        return self::$boxExecutable;
    }
}