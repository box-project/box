<?php

declare(strict_types=1);

namespace KevinGH\Box\PhpScoper;


use Humbug\PhpScoper\Scoper as PhpScoper;

final class SimpleScoper implements Scoper
{
    private $scoper;
    private $prefix;
    private $whitelist;
    private $patchers;

    public function __construct(PhpScoper $scoper, string $prefix, array $whitelist, array $patchers)
    {
        $this->scoper = $scoper;
        $this->prefix = $prefix;
        $this->whitelist = $whitelist;
        $this->patchers = $patchers;
    }

    /**
     * @inheritdoc
     */
    public function scope(string $filePath, string $contents): string
    {
        return $this->scoper->scope(
            $filePath,
            $contents,
            $this->prefix,
            $this->patchers,
            $this->whitelist
        );
    }

    /**
     * @inheritdoc
     */
    public function getWhitelist(): array
    {
        return $this->whitelist;
    }

    /**
     * @inheritdoc
     */
    public function getPrefix(): string
    {
        return $this->prefix;
    }
}