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

use Countable;
use Humbug\PhpScoper\Symbol\SymbolsRegistry;
use KevinGH\Box\PhpScoper\Scoper;
use function array_reduce;
use function count;

/**
 * @private
 */
final class Compactors implements Compactor, Countable
{
    /**
     * @var Compactor[]
     */
    private readonly array $compactors;

    private ?PhpScoper $scoperCompactor = null;

    public function __construct(Compactor ...$compactors)
    {
        $this->compactors = $compactors;

        foreach ($compactors as $compactor) {
            if ($compactor instanceof PhpScoper) {
                $this->scoperCompactor = $compactor;

                // We do not expect more than one Scoper Compactor. If there is more than
                // one then the latter is ignored.
                break;
            }
        }
    }

    public function compact(string $file, string $contents): string
    {
        return array_reduce(
            $this->compactors,
            static fn (string $contents, Compactor $compactor): string => $compactor->compact($file, $contents),
            $contents,
        );
    }

    public function getScoper(): ?Scoper
    {
        return $this->scoperCompactor?->getScoper();
    }

    public function getScoperSymbolsRegistry(): ?SymbolsRegistry
    {
        return $this->scoperCompactor?->getScoper()->getSymbolsRegistry();
    }

    public function registerSymbolsRegistry(SymbolsRegistry $symbolsRegistry): void
    {
        $this->scoperCompactor?->getScoper()->changeSymbolsRegistry($symbolsRegistry);
    }

    /**
     * @return Compactor[]
     */
    public function toArray(): array
    {
        return $this->compactors;
    }

    public function count(): int
    {
        return count($this->compactors);
    }
}
