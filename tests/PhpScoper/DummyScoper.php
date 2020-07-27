<?php

declare(strict_types=1);

namespace KevinGH\Box\PhpScoper;

use Humbug\PhpScoper\Scoper;
use Humbug\PhpScoper\Whitelist;

class DummyScoper implements Scoper
{
    public function scope(string $filePath, string $contents, string $prefix, array $patchers, Whitelist $whitelist): string
    {
        return 'dummy';
    }
}
