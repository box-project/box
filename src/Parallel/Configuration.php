<?php

declare(strict_types=1);

namespace KevinGH\Box\Parallel;

use Fidry\FileSystem\FS;
use KevinGH\Box\Compactor\Compactors;
use KevinGH\Box\MapFile;
use function Safe\json_encode;
use function serialize;
use function unserialize;

final readonly class Configuration
{
    public function __construct(
        public array      $filePaths,
        public MapFile    $mapFile,
        public Compactors $compactors,
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
}