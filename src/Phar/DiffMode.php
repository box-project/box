<?php

declare(strict_types=1);

namespace KevinGH\Box\PharInfo;

use BackedEnum;
use UnitEnum;
use function array_map;

enum DiffMode: string
{
    case LIST = 'list';
    case GIT = 'git';
    case GNU = 'gnu';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(
            static fn (self $enum) => $enum->value,
            self::cases(),
        );
    }
}
