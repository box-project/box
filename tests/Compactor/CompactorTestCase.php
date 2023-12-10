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

namespace KevinGH\Box\Compactor;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use function serialize;
use function unserialize;

abstract class CompactorTestCase extends TestCase
{
    #[DataProvider('compactorProvider')]
    public function test_it_is_serializable(Compactor $compactor): void
    {
        $unserializedCompactor = unserialize(serialize($compactor));

        self::assertEquals(
            $compactor,
            $unserializedCompactor,
        );
    }

    abstract public static function compactorProvider(): iterable;
}
