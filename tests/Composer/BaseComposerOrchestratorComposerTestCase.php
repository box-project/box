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

namespace KevinGH\Box\Composer;

use Humbug\PhpScoper\Symbol\SymbolsRegistry;
use KevinGH\Box\Test\FileSystemTestCase;
use PhpParser\Node\Name\FullyQualified;
use Symfony\Component\Finder\Finder;
use function file_exists;
use function iterator_to_array;
use function sprintf;

abstract class BaseComposerOrchestratorComposerTestCase extends FileSystemTestCase
{
    protected const FIXTURES = __DIR__.'/../../fixtures/composer-dump';
    protected const COMPOSER_AUTOLOADER_NAME = 'ComposerAutoloaderInit80c62b20a4a44fb21e8e102ccb92ff05';

    protected ComposerOrchestrator $composerOrchestrator;

    protected bool $skip;
    protected string $skipReason;

    protected function setUp(): void
    {
        $this->composerOrchestrator = ComposerOrchestrator::create();

        if (!isset($this->skip, $this->skipReason)) {
            $composerVersion = $this->composerOrchestrator->getVersion();

            [$skip, $supportedConstraint] = $this->shouldSkip($composerVersion);

            $this->skip = $skip;
            $this->skipReason = sprintf(
                'Can only be executed with Composer %s. Got "%s".',
                $supportedConstraint,
                $composerVersion,
            );
        }

        if ($this->skip) {
            self::markTestSkipped($this->skipReason);
        }

        parent::setUp();
    }

    /**
     * @return array{bool, string}
     */
    abstract protected function shouldSkip(string $composerVersion): array;

    /**
     * @param array<array{string, string}> $recordedClasses
     * @param array<array{string, string}> $recordedFunctions
     */
    protected static function createSymbolsRegistry(array $recordedClasses = [], array $recordedFunctions = []): SymbolsRegistry
    {
        $registry = new SymbolsRegistry();

        foreach ($recordedClasses as [$original, $alias]) {
            $registry->recordClass(
                new FullyQualified($original),
                new FullyQualified($alias),
            );
        }

        foreach ($recordedFunctions as [$original, $alias]) {
            $registry->recordFunction(
                new FullyQualified($original),
                new FullyQualified($alias),
            );
        }

        return $registry;
    }

    /**
     * @return string[]
     */
    protected function retrievePaths(): array
    {
        $finder = Finder::create()->files()->in($this->tmp);

        return $this->normalizePaths(iterator_to_array($finder, false));
    }

    protected function skipIfFixturesNotInstalled(string $path): void
    {
        if (!file_exists($path)) {
            self::markTestSkipped('The fixtures were not installed. Run `$ make test_unit` in order to set them all up.');
        }
    }
}
