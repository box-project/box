<?php

declare(strict_types=1);

namespace KevinGH\Box\PhpScoper;


use Humbug\PhpScoper\Scoper;
use KevinGH\Box\NotCallable;

final class FakePhpScoper implements Scoper
{
    use NotCallable;

    /**
     * @inheritdoc
     */
    public function scope(string $filePath, string $contents, string $prefix, array $patchers, array $whitelist): string
    {
        $this->__call(__METHOD__, func_get_args());
    }
}