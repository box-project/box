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

use Humbug\PhpScoper\Whitelist;
use KevinGH\Box\Compactor\PhpScoper;
use function array_reduce;

final class Compactors
{
    private $compactors;
    private $scoperCompactor;

    public function __construct(Compactor ...$compactors)
    {
        $this->compactors = $compactors;

        foreach ($compactors as $compactor) {
            if ($compactor instanceof PhpScoper) {
                $this->scoperCompactor = $compactor;

                break;
            }
        }
    }

    public function compactContents(string $file, string $contents): string
    {
        return (string) array_reduce(
            $this->compactors,
            static function (string $contents, Compactor $compactor) use ($file): string {
                return $compactor->compact($file, $contents);
            },
            $contents
        );
    }

    public function getScoperWhitelist(): ?Whitelist
    {
        return null !== $this->scoperCompactor ? $this->scoperCompactor->getScoper()->getWhitelist() : null;
    }

    public function registerWhitelist(Whitelist $whitelist): void
    {
        if (null !== $this->scoperCompactor) {
            $this->scoperCompactor->getScoper()->changeWhitelist($whitelist);
        }
    }

    public function toArray(): array
    {
        return $this->compactors;
    }
}
