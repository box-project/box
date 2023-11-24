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

use Closure;
use Humbug\PhpScoper\Patcher\Patcher;
use Laravel\SerializableClosure\SerializableClosure;
use function func_get_args;

/**
 * @var PatcherCallable = (string $filePath, string $prefix, string $contents): string
 */
final class SerializablePatcher implements Patcher
{
    public static function create(callable $patcher): self
    {
        if ($patcher instanceof Patcher) {
            $patcher = static fn (mixed ...$args) => $patcher(...$args);
        }

        return new self(new SerializableClosure($patcher));
    }

    /**
     * @param PatcherCallable $patch
     */
    private function __construct(private Closure|SerializableClosure $patch)
    {
    }

    public function __invoke(string $filePath, string $prefix, string $contents): string
    {
        return ($this->patch)(...func_get_args());
    }
}
