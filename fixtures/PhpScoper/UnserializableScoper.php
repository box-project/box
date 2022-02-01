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

namespace KevinGH\Box\PhpScoper;

use DomainException;
use Humbug\PhpScoper\Scoper\Scoper as PhpScoperScoper;
use KevinGH\Box\NotCallable;
use Serializable;

final class UnserializableScoper implements PhpScoperScoper, Serializable
{
    use NotCallable;

    /**
     * {@inheritdoc}
     */
    public function scope(string $filePath, string $contents): string
    {
        $this->__call(__METHOD__, func_get_args());
    }

    /**
     * {@inheritdoc}
     */
    public function serialize(): string
    {
        throw new DomainException('This class is not serializable');
    }

    /**
     * {@inheritdoc}
     */
    public function unserialize($serialized): void
    {
        throw new DomainException('This class is not serializable');
    }
}
