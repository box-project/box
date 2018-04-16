<?php

declare(strict_types=1);

namespace KevinGH\Box\PhpScoper;


final class NullScoper implements Scoper
{
    /**
     * @inheritdoc
     */
    public function scope(string $filePath, string $contents): string
    {
        return $contents;
    }

    /**
     * @return string[]
     */
    public function getWhitelist(): array
    {
        return [];
    }

    public function getPrefix(): string
    {
        return '';
    }
}