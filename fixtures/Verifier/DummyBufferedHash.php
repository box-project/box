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

namespace KevinGH\Box\Verifier;

use function func_get_args;
use KevinGH\Box\NotCallable;

final class DummyBufferedHash extends BufferedHash
{
    use NotCallable;

    /**
     * {@inheritdoc}
     */
    public function __construct(string $algorithm, string $path)
    {
    }

    /**
     * {@inheritdoc}
     */
    public function verify(string $signature): bool
    {
        $this->__call(__METHOD__, func_get_args());
    }

    public function getPublicBufferedData(): string
    {
        return $this->getBufferedData();
    }
}
