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

final readonly class BatchResult
{
    /**
     * @param array{string, string} $processedFilesWithContents
     */
    public function __construct(
        public array $processedFilesWithContents,
        public SymbolsRegistry $symbolsRegistry,
    ) {
    }

    public function serialize(): string
    {
        return serialize($this);
    }

    /**
     * @return list<array{string, string}>
     */
    public static function unserialize(string $serialized): self
    {
        return unserialize($serialized);
    }

    /**
     * @return array{
     *     array{string, string},
     *     SymbolsRegistry,
     * }
     */
    public function toArray(): array
    {
        return [
            $this->processedFilesWithContents,
            $this->symbolsRegistry,
        ];
    }
}
