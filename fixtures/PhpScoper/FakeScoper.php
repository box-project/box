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

use Humbug\PhpScoper\Symbol\SymbolsRegistry;
use function func_get_args;
use KevinGH\Box\NotCallable;

final class FakeScoper implements Scoper
{
    use NotCallable;

    /**
     * {@inheritdoc}
     */
    public function scope(string $filePath, string $contents): string
    {
        $this->__call(__METHOD__, func_get_args());
    }

    public function changeSymbolsRegistry(SymbolsRegistry $symbolsRegistry): void
    {
        $this->__call(__METHOD__, func_get_args());
    }

    public function getSymbolsRegistry(): SymbolsRegistry
    {
        $this->__call(__METHOD__, func_get_args());
    }

    public function getPrefix(): string
    {
        $this->__call(__METHOD__, func_get_args());
    }
}
