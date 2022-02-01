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

use Humbug\PhpScoper\Symbol\SymbolsRegistry;
use function array_reduce;
use function count;
use Countable;
use Humbug\PhpScoper\Whitelist;
use KevinGH\Box\PhpScoper\Scoper;

/**
 * @private
 */
final class Compactors implements Compactor, Countable
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

    /**
     * {@inheritdoc}
     */
    public function compact(string $file, string $contents): string
    {
        return (string) array_reduce(
            $this->compactors,
            static function (string $contents, Compactor $compactor) use ($file): string {
                return $compactor->compact($file, $contents);
            },
            $contents
        );
    }

    public function getScoper(): ?Scoper
    {
        return null !== $this->scoperCompactor ? $this->scoperCompactor->getScoper() : null;
    }

    public function getScoperSymbolsRegistry(): ?SymbolsRegistry
    {
        return null !== $this->scoperCompactor ? $this->scoperCompactor->getScoper()->getSymbolsRegistry() : null;
    }

    public function registerSymbolsRegistry(SymbolsRegistry $symbolsRegistry): void
    {
        if (null !== $this->scoperCompactor) {
            $this->scoperCompactor->getScoper()->changeSymbolsRegistry($symbolsRegistry);
        }
    }

    public function toArray(): array
    {
        return $this->compactors;
    }

    /**
     * {@inheritdoc}
     */
    public function count(): int
    {
        return count($this->compactors);
    }
}
