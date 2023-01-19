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
use KevinGH\Box\UnsupportedMethodCall;

final class FakeScoper implements Scoper
{
    public function scope(string $filePath, string $contents): string
    {
        throw UnsupportedMethodCall::forMethod(__CLASS__, __METHOD__);
    }

    public function changeSymbolsRegistry(SymbolsRegistry $symbolsRegistry): void
    {
        throw UnsupportedMethodCall::forMethod(__CLASS__, __METHOD__);
    }

    public function getSymbolsRegistry(): SymbolsRegistry
    {
        throw UnsupportedMethodCall::forMethod(__CLASS__, __METHOD__);
    }

    public function getPrefix(): string
    {
        throw UnsupportedMethodCall::forMethod(__CLASS__, __METHOD__);
    }
}
