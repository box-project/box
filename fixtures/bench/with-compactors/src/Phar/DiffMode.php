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

namespace BenchTest\Phar;

use function array_map;

enum DiffMode: string
{
    case FILE_NAME = 'file-name';
    case GIT = 'git';
    case GNU = 'gnu';
    case CHECKSUM = 'checksum';

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
