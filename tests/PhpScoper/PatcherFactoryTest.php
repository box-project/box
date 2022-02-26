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

namespace PhpScoper;

use Humbug\PhpScoper\Patcher\PatcherChain;
use KevinGH\Box\PhpScoper\DummyPatcher;
use KevinGH\Box\PhpScoper\PatcherFactory;
use KevinGH\Box\PhpScoper\SerializablePatcher;
use PHPUnit\Framework\TestCase;

/**
 * @covers \KevinGH\Box\PhpScoper\PatcherFactory
 */
final class PatcherFactoryTest extends TestCase
{
    public function test_it_create_a_new_chain_of_serializable_patchers(): void
    {
        $patcherChain = new PatcherChain([
            new DummyPatcher(),
            new DummyPatcher(),
        ]);

        $patcherChainOfSerializablePatchers = PatcherFactory::createSerializablePatchers($patcherChain);

        self::assertInstanceOf(PatcherChain::class, $patcherChainOfSerializablePatchers);

        $serializedPatchers = $patcherChainOfSerializablePatchers->getPatchers();

        self::assertCount(2, $serializedPatchers);

        foreach ($serializedPatchers as $serializedPatcher) {
            self::assertInstanceOf(SerializablePatcher::class, $serializedPatcher);
        }
    }

    public function test_it_leaves_patcher_unchanged_if_is_not_a_patcher_chain(): void
    {
        $patcher = new DummyPatcher();

        $serializablePatcher = PatcherFactory::createSerializablePatchers($patcher);

        self::assertSame($patcher, $serializablePatcher);
    }
}
