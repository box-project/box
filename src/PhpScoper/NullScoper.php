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
use Humbug\PhpScoper\Whitelist;

/**
 * @private
 */
final class NullScoper implements Scoper
{
    /**
     * {@inheritdoc}
     */
    public function scope(string $filePath, string $contents): string
    {
        return $contents;
    }

    /**
     * {@inheritdoc}
     */
    public function changeSymbolsRegistry(SymbolsRegistry $symbolsRegistry): void
    {
    }

    /**
     * {@inheritdoc}
     */
    public function getSymbolsRegistry(): SymbolsRegistry
    {
        return new SymbolsRegistry();
    }

    /**
     * {@inheritdoc}
     */
    public function getPrefix(): string
    {
        return '';
    }
}
