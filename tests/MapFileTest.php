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

use Generator;
use PHPUnit\Framework\TestCase;
use function serialize;
use function unserialize;

/**
 * @covers \KevinGH\Box\MapFile
 */
class MapFileTest extends TestCase
{
    /**
     * @dataProvider provideMaps
     */
    public function test_it_can_map_files(string $basePath, array $map, string $file, string $expected): void
    {
        $mapFile = new MapFile($basePath, $map);

        $actual = $mapFile($file);

        $this->assertSame($expected, $actual);
    }

    /**
     * @dataProvider provideMapFiles
     */
    public function test_it_serializable(MapFile $mapFile): void
    {
        $this->assertEquals(
            $mapFile,
            unserialize(serialize($mapFile))
        );
    }

    public function provideMaps(): Generator
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

    public function provideMapFiles(): Generator
    {
        yield [new MapFile('', [])];

        yield [new MapFile('/basepath', [['' => 'local/path']])];
    }
}
