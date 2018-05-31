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

final class Compactors
{
    private $compactors;

    public function __construct(Compactor ...$compactors)
    {
        $this->compactors = $compactors;
    }

    public function compactContents(string $file, string $contents): string
    {
        return (string) array_reduce(
            $this->compactors,
            function (string $contents, Compactor $compactor) use ($file): string {
                return $compactor->compact($file, $contents);
            },
            $contents
        );
    }

    public function toArray(): array
    {
        return $this->compactors;
    }
}
