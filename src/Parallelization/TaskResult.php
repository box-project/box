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
use function array_map;
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
        return new self(
            SymbolsRegistry::createFromRegistries(
                array_map(
                    static fn (self $result) => $result->symbolsRegistry,
                    $results,
                ),
            ),
        );
    }

    public function __construct(
        public SymbolsRegistry $symbolsRegistry,
    ) {
    }
}
