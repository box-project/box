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

namespace KevinGH\Box\Parallelization;

use Amp\Cancellation;
use Amp\Parallel\Worker\Task;
use Amp\Sync\Channel;
use Fidry\FileSystem\FS;
use Humbug\PhpScoper\Symbol\SymbolsRegistry;
use KevinGH\Box\Compactor\Compactors;
use KevinGH\Box\Filesystem\LocalPharFile;
use KevinGH\Box\MapFile;
use function array_map;

/**
 * @private
 */
final readonly class ProcessFileTask implements Task
{
    /**
     * @param string[] $filePaths
     */
    public function __construct(
        private array $filePaths,
        private string $cwd,
        private MapFile $mapFile,
        private Compactors $compactors,
    ) {
    }

    public function run(Channel $channel, Cancellation $cancellation): TaskResult
    {
        chdir($this->cwd);

        $mapFile = $this->mapFile;
        $compactors = $this->compactors;

        $processFile = static function (string $file) use ($mapFile, $compactors): array {
            $contents = FS::getFileContents($file);

            $local = $mapFile($file);

            $processedContents = $compactors->compact($local, $contents);

            return [new LocalPharFile($local, $processedContents), $compactors->getScoperSymbolsRegistry()];
        };

        $tuples = array_map($processFile, $this->filePaths);

        $localPharFiles = [];
        $symbolRegistries = [];

        foreach ($tuples as [$localPharFile, $symbolRegistry]) {
            $localPharFiles[] = $localPharFile;
            $symbolRegistries[] = $symbolRegistry;
        }

        return new TaskResult(
            $localPharFiles,
            SymbolsRegistry::createFromRegistries(array_filter($symbolRegistries)),
        );
    }
}
