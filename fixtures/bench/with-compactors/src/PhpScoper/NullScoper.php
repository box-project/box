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

namespace BenchTest\PhpScoper;

use Humbug\PhpScoper\Symbol\SymbolsRegistry;

/**
 * @private
 */
final class NullScoper implements Scoper
{
    public function __construct(
        private SymbolsRegistry $symbolsRegistry = new SymbolsRegistry(),
    ) {
    }

    public function scope(string $filePath, string $contents): string
    {
        return $contents;
    }

    public function changeSymbolsRegistry(SymbolsRegistry $symbolsRegistry): void
    {
        $this->symbolsRegistry = $symbolsRegistry;
    }

    public function getSymbolsRegistry(): SymbolsRegistry
    {
        return $this->symbolsRegistry;
    }

    public function getPrefix(): string
    {
        return '';
    }

    public function getExcludedFilePaths(): array
    {
        return [];
    }
}
