<?php

declare(strict_types=1);

namespace KevinGH\Box\PhpScoper;


use KevinGH\Box\NotCallable;

final class FakeScoper implements Scoper
{
    use NotCallable;

    /**
     * {@inheritdoc}
     */
    public function scope(string $filePath, string $contents): string
    {
        $this->__call(__METHOD__, func_get_args());
    }

    /**
     * {@inheritdoc}
     */
    public function getWhitelist(): array
    {
        $this->__call(__METHOD__, func_get_args());
    }

    /**
     * {@inheritdoc}
     */
    public function getPrefix(): string
    {
        $this->__call(__METHOD__, func_get_args());
    }
}