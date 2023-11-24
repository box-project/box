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

namespace BenchTest\PhpScoper;

use Humbug\PhpScoper\Scoper\Scoper as PhpScoperScoper;
use function array_flip;
use function array_key_exists;
use function func_get_args;

final class ExcludedFilesScoper implements PhpScoperScoper
{
    private array $excludedFilePathsAsKeys;

    public function __construct(
        private PhpScoperScoper $decoratedScoper,
        string ...$excludedFilePaths,
    ) {
        $this->excludedFilePathsAsKeys = array_flip($excludedFilePaths);
    }

    public function scope(string $filePath, string $contents): string
    {
        if (array_key_exists($filePath, $this->excludedFilePathsAsKeys)) {
            return $contents;
        }

        return $this->decoratedScoper->scope(...func_get_args());
    }
}
