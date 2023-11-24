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

final class IncompariblePhars extends PharError
{
    public static function signedPhars(): self
    {
        return new self('Cannot compare PHARs which have an external public key.');
    }
}
