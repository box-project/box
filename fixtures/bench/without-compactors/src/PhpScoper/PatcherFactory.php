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
use Humbug\PhpScoper\Patcher\PatcherChain;
use KevinGH\Box\NotInstantiable;
use Laravel\SerializableClosure\SerializableClosure;

final class PatcherFactory
{
    use NotInstantiable;

    /**
     * @param callable[] $patcher
     *
     * @return SerializableClosure[]
     */
    public static function createSerializablePatchers(Patcher $patcher): Patcher
    {
        if (!($patcher instanceof PatcherChain)) {
            return $patcher;
        }

        $serializablePatchers = array_map(
            static fn (callable $patcher) => SerializablePatcher::create($patcher),
            $patcher->getPatchers(),
        );

        return new PatcherChain($serializablePatchers);
    }
}
