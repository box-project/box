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

use function array_map;
use function count;
use Humbug\PhpScoper\Configuration\Configuration as PhpScoperConfiguration;
use Humbug\PhpScoper\Container as PhpScoperContainer;
use Humbug\PhpScoper\Patcher\Patcher;
use Humbug\PhpScoper\Patcher\PatcherChain;
use Humbug\PhpScoper\Scoper\FileWhitelistScoper;
use Humbug\PhpScoper\Scoper\Scoper as PhpScoper;
use Humbug\PhpScoper\Symbol\SymbolsRegistry;
use Opis\Closure\SerializableClosure;

/**
 * @private
 */
final class SimpleScoper implements Scoper
{
    private PhpScoperConfiguration $scoperConfig;
    private PhpScoperContainer $scoperContainer;
    private PhpScoper $scoper;
    private SymbolsRegistry $symbolsRegistry;

    /**
     * @var list<string>
     */
    private array $excludedFilePaths;

    public function __construct(
        PhpScoperConfiguration $scoperConfig,
        string ...$excludedFilePaths
    ) {
        $this->scoperConfig = new PhpScoperConfiguration(
            $scoperConfig->getPath(),
            $scoperConfig->getPrefix(),
            $scoperConfig->getFilesWithContents(),
            $scoperConfig->getExcludedFilesWithContents(),
            self::createSerializablePatchers($scoperConfig->getPatcher()),
            $scoperConfig->getSymbolsConfiguration(),
        );
        $this->excludedFilePaths = $excludedFilePaths;
        $this->symbolsRegistry = new SymbolsRegistry();
    }

    /**
     * {@inheritdoc}
     */
    public function scope(string $filePath, string $contents): string
    {
        return $this->getScoper()->scope(
            $filePath,
            $contents,
        );
    }

    /**
     * {@inheritdoc}
     */
    public function changeSymbolsRegistry(SymbolsRegistry $symbolsRegistry): void
    {
        $this->symbolsRegistry = $symbolsRegistry;

        unset($this->scoper);
    }

    /**
     * {@inheritdoc}
     */
    public function getSymbolsRegistry(): SymbolsRegistry
    {
        return $this->symbolsRegistry;
    }

    /**
     * {@inheritdoc}
     */
    public function getPrefix(): string
    {
        return $this->scoperConfig->getPrefix();
    }

    private function getScoper(): PhpScoper
    {
        if (isset($this->scoper)) {
            return $this->scoper;
        }

        if (!isset($this->scoperContainer)) {
            $this->scoperContainer = new PhpScoperContainer();
        }

        $scoper = (new PhpScoperContainer())
            ->getScoperFactory()
            ->createScoper(
                $this->scoperConfig,
                $this->symbolsRegistry,
            );

        if (0 !== count($this->excludedFilePaths)) {
            $scoper = new FileWhitelistScoper(
                $scoper,
                ...$this->excludedFilePaths,
            );
        }

        $this->scoper = $scoper;

        return $this->scoper;
    }

    /**
     * @param callable[] $patcher
     *
     * @retunr SerializableClosure[]
     */
    private static function createSerializablePatchers(Patcher $patcher): Patcher
    {
        if (!($patcher instanceof PatcherChain)) {
            return $patcher;
        }

        $serializablePatchers = array_map(
            static function (callable $patcher): SerializableClosure {
                if ($patcher instanceof Patcher) {
                    $patcher = static fn (string $filePath, string $prefix, string $contents) => $patcher($filePath, $prefix, $contents);
                }

                return new SerializableClosure($patcher);
            },
            $patcher->getPatchers(),
        );

        return new PatcherChain($serializablePatchers);
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
}
