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

use Humbug\PhpScoper\Symbol\SymbolsRegistry;
use function array_merge;

/**
 * @private
 */
final readonly class TaskResult
{
    /**
     * @param self[] $results
     */
    public static function aggregate(array $results): self
    {
        $filesWithContents = [];
        $symbolsRegistries = [];

        foreach ($results as $result) {
            $filesWithContents[] = $result->filesWithContents;
            $symbolsRegistries[] = $result->symbolsRegistry;
        }

        return new self(
            array_merge(...$filesWithContents),
            SymbolsRegistry::createFromRegistries($symbolsRegistries),
        );
    }

    /**
     * @param list<array{string, string}> $filesWithContents
     */
    public function __construct(
        public array $filesWithContents,
        public SymbolsRegistry $symbolsRegistry,
    ) {
    }
}
