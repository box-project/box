<?php

declare(strict_types=1);

namespace KevinGH\Box\Filesystem;

final readonly class LocalPharFile implements File
{
    public function __construct(
        public string $path,
        public string $contents,
    ) {
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function getContents(): string
    {
        return $this->contents;
    }
}