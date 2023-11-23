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

namespace KevinGH\Box\Parallelization;

use KevinGH\Box\Compactor\Compactors;
use KevinGH\Box\MapFile;
use function serialize;
use function unserialize;

/**
 * @private
 */
final readonly class Configuration
{
    public function __construct(
        public array $filePaths,
        public MapFile $mapFile,
        public Compactors $compactors,
    ) {
    }

    public function serialize(): string
    {
        return serialize($this);
    }

    /**
     * @return list<array{string, string}>
     */
    public static function unserialize(string $serialized): self
    {
        return unserialize($serialized);
    }
}
