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
    private PhpScoperContainer $scoperContainer;
    private PhpScoperScoper $scoper;
    private SymbolsRegistry $symbolsRegistry;

    /**
     * @var list<string>
     */
    public array $excludedFilePaths;

    public function __construct(
        private readonly PhpScoperConfiguration $scoperConfig,
        string ...$excludedFilePaths,
    ) {
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

    public function __serialize(): array
    {
        return [
            $this->scoperConfig->getPath(),
            $this->scoperConfig->getPrefix(),
            $this->excludedFilePaths,
        ];
    }

    public function __unserialize(array $data): void
    {
        [$configPath, $configPrefix, $excludedFilePaths] = $data;

        $config = ConfigurationFactory::create($configPath)->withPrefix($configPrefix);

        $this->__construct(
            $config,
            ...$excludedFilePaths,
        );
    }
}
