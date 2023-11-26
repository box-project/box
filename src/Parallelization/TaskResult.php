<?php

declare(strict_types=1);

namespace KevinGH\Box\Parallelization;

use Humbug\PhpScoper\Symbol\SymbolsRegistry;

/**
 * @private
 */
final readonly class TaskResult
{
    /**
     * @param list<array{string, string}> $filesWithContents
     */
    public function __construct(
        public array $filesWithContents,
        public SymbolsRegistry $symbolsRegistry,
    ) {
    }
}