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

use Closure;
use function func_get_args;
use Humbug\PhpScoper\Scoper as HumbugPhpScoperScoper;
use Humbug\PhpScoper\Whitelist;
use Opis\Closure\SerializableClosure;
use Serializable;
use function serialize;
use function unserialize;

/**
 * Humbug PHP-Scoper scoper which leverages closures to ensure the scoper is serializable.
 */
final class SerializablePhpScoper implements HumbugPhpScoperScoper, Serializable
{
    private $createScoper;
    private $scoper;

    public function __construct(Closure $createScoper)
    {
        $this->createScoper = new SerializableClosure($createScoper);

        // Checks that the closure used is serializable upfront instead of lazily: the overhead generated is negligible
        // hence worth the security this check provides
        unserialize(serialize($this->createScoper));

        // Checks that the scoper is instantiable upfront instead of lazily: the overhead generated is negligible hence
        // worth the security this check provides
        $this->getScoper();
    }

    /**
     * {@inheritdoc}
     */
    public function scope(string $filePath, string $contents, string $prefix, array $patchers, Whitelist $whitelist): string
    {
        return $this->getScoper()->scope(...func_get_args());
    }

    /**
     * {@inheritdoc}
     */
    public function serialize(): string
    {
        return serialize($this->createScoper);
    }

    /**
     * {@inheritdoc}
     */
    public function unserialize($serialized): void
    {
        $this->createScoper = unserialize($serialized);
    }

    public function getScoper(): HumbugPhpScoperScoper
    {
        if (null === $this->scoper) {
            $this->scoper = ($this->createScoper)();
        }

        return $this->scoper;
    }
}
