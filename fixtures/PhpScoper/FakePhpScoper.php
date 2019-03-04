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

namespace KevinGH\Box\PhpScoper;

use function func_get_args;
use Humbug\PhpScoper\Scoper;
use Humbug\PhpScoper\Whitelist;
use KevinGH\Box\NotCallable;

final class FakePhpScoper implements Scoper
{
    use NotCallable;

    /**
     * {@inheritdoc}
     */
    public function scope(string $filePath, string $contents, string $prefix, array $patchers, Whitelist $whitelist): string
    {
        $this->__call(__METHOD__, func_get_args());
    }
}
