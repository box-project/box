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

namespace KevinGH\Box;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use function serialize;
use function unserialize;

/**
 * @internal
 */
#[CoversClass(MapFile::class)]
class MapFileTest extends TestCase
{
    #[DataProvider('mapsProvider')]
    public function test_it_can_map_files(string $basePath, array $map, string $file, string $expected): void
    {
        $mapFile = new MapFile($basePath, $map);

        $actual = $mapFile($file);

        self::assertSame($expected, $actual);
    }

    #[DataProvider('mapFilesProvider')]
    public function test_it_serializable(MapFile $mapFile): void
    {
        self::assertEquals(
            $mapFile,
            unserialize(serialize($mapFile)),
        );
    }

    public static function mapsProvider(): iterable
    {
        $basePath = '/basepath';

        yield [
            $basePath,
            [
                ['' => 'local/path'],
            ],
            'foo',
            'local/path/foo',
        ];

        yield [
            $basePath,
            [
                ['foo' => 'local/path/foo'],
            ],
            'foo',
            'local/path/foo',
        ];

        $map = [
            ['acme' => 'src/Foo'],
            ['' => 'lib'],
        ];

        yield [
            $basePath,
            $map,
            'acme/bar',
            'src/Foo/bar',
        ];

        yield [
            $basePath,
            $map,
            'acme/foo',
            'src/Foo/foo',
        ];

        yield [
            $basePath,
            $map,
            'file1',
            'lib/file1',
        ];

        yield [
            $basePath,
            $map,
            'file2',
            'lib/file2',
        ];
    }

    public static function mapFilesProvider(): iterable
    {
        yield [new MapFile('', [])];

        yield [new MapFile('/basepath', [['' => 'local/path']])];
    }
}
