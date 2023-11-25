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

use function ini_get;

final class PharPhpSettings
{
    public static function isReadonly(): bool
    {
        return '1' === ini_get('phar.readonly');
    }

    private function __construct()
    {
    }
}
