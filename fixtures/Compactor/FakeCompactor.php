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

namespace KevinGH\Box\Compactor;

use function func_get_args;
use KevinGH\Box\NotCallable;

class FakeCompactor implements Compactor
{
    use NotCallable;

    /**
     * {@inheritdoc}
     */
    public function compact(string $file, string $contents): string
    {
        $this->__call(__METHOD__, func_get_args());
    }
}
