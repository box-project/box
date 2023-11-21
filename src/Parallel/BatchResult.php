<?php

declare(strict_types=1);

namespace KevinGH\Box\Parallel;

use Humbug\PhpScoper\Symbol\SymbolsRegistry;
use KevinGH\Box\NotInstantiable;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;
use function array_map;
use function iter\toArray;
use function KevinGH\Box\unique_id;

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
     * @param string[] $filePaths
     *
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