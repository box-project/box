<?php

declare(strict_types=1);

namespace KevinGH\Box;


use const PHP_OS_FAMILY;

final class Platform
{
    use NotInstantiable;

    public static function isOSX(): bool
    {
        return 'Darwin' === PHP_OS_FAMILY;
    }
}
