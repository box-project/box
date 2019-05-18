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

use Closure;
use Opis\Closure\SerializableClosure;
use Serializable;
use function serialize;
use function unserialize;

final class CompactorProxy implements Compactor, Serializable
{
    private $createCompactor;
    private $compactor;

    public function __construct(Closure $createCompactor)
    {
        $this->createCompactor = new SerializableClosure($createCompactor);

        // Instantiate a compactor instead of lazily instantiate it in order to ensure the factory closure is correct
        // and that the created object is of the created type. Since compactors instantiation should be fast, this is
        // a minimal overhead which is an acceptable trade-off for providing early errors.
        $this->getCompactor();
    }

    /**
     * {@inheritdoc}
     */
    public function compact(string $file, string $contents): string
    {
        return $this->getCompactor()->compact($file, $contents);
    }

    /**
     * {@inheritdoc}
     */
    public function serialize(): string
    {
        return serialize($this->createCompactor);
    }

    /**
     * {@inheritdoc}
     */
    public function unserialize($serialized): void
    {
        $this->createCompactor = unserialize($serialized);
    }

    public function getCompactor(): Compactor
    {
        if (null === $this->compactor) {
            $this->compactor = ($this->createCompactor)();
        }

        return $this->compactor;
    }
}
