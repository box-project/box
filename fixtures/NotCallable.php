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

use DomainException;
use function sprintf;

// TODO: move this to throw unsupported method exception instead
trait NotCallable
{
    public function __call($method, $arguments): void
    {
        throw new DomainException(
            sprintf(
                'Did not expect "%s" to be called.',
                $method
            )
        );
    }
}
