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

use Humbug\PhpScoper\Patcher\Patcher;
use function sprintf;

final class DummyPatcher implements Patcher
{
    public function __invoke(string $filePath, string $prefix, string $contents): string
    {
        return sprintf(
            'scopedContent(%s)',
            $contents,
        );
    }
}
