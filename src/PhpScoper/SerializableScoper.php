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

use Humbug\PhpScoper\Configuration\Configuration as PhpScoperConfiguration;
use Humbug\PhpScoper\Container as PhpScoperContainer;
use Humbug\PhpScoper\Scoper\Scoper as PhpScoperScoper;
use Humbug\PhpScoper\Symbol\SymbolsRegistry;
use function count;

/**
 * @private
 */
final class SerializableScoper implements Scoper
{
    private PhpScoperConfiguration $scoperConfig;
    private PhpScoperContainer $scoperContainer;
    private PhpScoperScoper $scoper;
    private SymbolsRegistry $symbolsRegistry;

    /**
     * @var list<string>
     */
    public array $excludedFilePaths;

    public function __construct(
        PhpScoperConfiguration $scoperConfig,
        string ...$excludedFilePaths,
    ) {
        $this->scoperConfig = $scoperConfig->withPatcher(
            PatcherFactory::createSerializablePatchers($scoperConfig->getPatcher())
        );
        $this->excludedFilePaths = $excludedFilePaths;
        $this->symbolsRegistry = new SymbolsRegistry();
    }

    public function scope(string $filePath, string $contents): string
    {
        return $this->getScoper()->scope(
            $filePath,
            $contents,
        );
    }

    public function changeSymbolsRegistry(SymbolsRegistry $symbolsRegistry): void
    {
        $this->symbolsRegistry = $symbolsRegistry;

        unset($this->scoper);
    }

    public function getSymbolsRegistry(): SymbolsRegistry
    {
        return $this->symbolsRegistry;
    }

    public function getPrefix(): string
    {
        return $this->scoperConfig->getPrefix();
    }

    private function getScoper(): PhpScoperScoper
    {
        if (isset($this->scoper)) {
            return $this->scoper;
        }

        if (!isset($this->scoperContainer)) {
            $this->scoperContainer = new PhpScoperContainer();
        }

        $this->scoper = $this->createScoper();

        return $this->scoper;
    }

    public function __wakeup(): void
    {
        // We need to make sure that a fresh Scoper & PHP-Parser Parser/Lexer
        // is used within a sub-process.
        // Otherwise, there is a risk of data corruption or that a compatibility
        // layer of some sorts (such as the tokens for PHP-Paser) is not
        // triggered in the sub-process resulting in obscure errors
        unset($this->scoper, $this->scoperContainer);
    }

    private function createScoper(): PhpScoperScoper
    {
        $scoper = $this->scoperContainer
            ->getScoperFactory()
            ->createScoper(
                $this->scoperConfig,
                $this->symbolsRegistry,
            );

        if (0 === count($this->excludedFilePaths)) {
            return $scoper;
        }

        return new ExcludedFilesScoper(
            $scoper,
            ...$this->excludedFilePaths,
        );
    }

    public function getExcludedFilePaths(): array
    {
        return $this->excludedFilePaths;
    }
}
