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

use PHPUnit\Framework\TestCase;
use function Safe\sprintf;
use function serialize;
use function unserialize;

/**
 * @covers \KevinGH\Box\PhpScoper\SerializablePatcher
 */
final class SerializablePatcherTest extends TestCase
{
    public function test_it_can_be_serialized_with_a_closure(): void
    {
        $patch = static fn (string $filePath, string $prefix, string $contents) => sprintf(
            'scopedContent(%s)',
            $contents,
        );
        $expected = 'scopedContent(content)';

        $serializablePatcher = SerializablePatcher::create($patch);
        $serializedPatcher = unserialize(serialize($serializablePatcher));

        $actual1 = $serializablePatcher('filePath', '_Humbug', 'content');
        $actual2 = $serializedPatcher('filePath', '_Humbug', 'content');

        self::assertSame($expected, $actual1);
        self::assertSame($expected, $actual2);
    }

    public function test_it_can_be_serialized_with_a_patcher(): void
    {
        $patch = new DummyPatcher();
        $expected = 'scopedContent(content)';

        $serializablePatcher = SerializablePatcher::create($patch);
        $serializedPatcher = unserialize(serialize($serializablePatcher));

        $actual1 = $serializablePatcher('filePath', '_Humbug', 'content');
        $actual2 = $serializedPatcher('filePath', '_Humbug', 'content');

        self::assertSame($expected, $actual1);
        self::assertSame($expected, $actual2);
    }
}
