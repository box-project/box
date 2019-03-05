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

namespace KevinGH\Box\Composer;

use Closure;
use Generator;
use PHPUnit\Framework\TestCase;

/**
 * @covers \KevinGH\Box\Composer\ComposerFiles
 */
class ComposerFilesTest extends TestCase
{
    /**
     * @dataProvider provideValidInstantiators
     *
     * @param string[] $paths
     */
    public function test_it_can_be_created(
        Closure $create,
        ComposerFile $expectedComposerJson,
        ComposerFile $expectedComposerLock,
        ComposerFile $expectedInstalledJson,
        array $expectedPaths
    ): void {
        /** @var ComposerFiles $actual */
        $actual = $create();

        $this->assertInstanceOf(ComposerFiles::class, $actual);

        $this->assertEquals($expectedComposerJson, $actual->getComposerJson());
        $this->assertEquals($expectedComposerLock, $actual->getComposerLock());
        $this->assertEquals($expectedInstalledJson, $actual->getInstalledJson());

        $this->assertSame($expectedPaths, $actual->getPaths());
    }

    public function provideValidInstantiators(): Generator
    {
        yield (static function (): array {
            $json = new ComposerFile('path/to/composer.json', ['name' => 'composer.json']);
            $lock = new ComposerFile('path/to/composer.lock', ['name' => 'composer.lock']);
            $installed = new ComposerFile('path/to/installed.json', ['name' => 'installed.json']);

            return [
                static function () use ($json, $lock, $installed): ComposerFiles {
                    return new ComposerFiles($json, $lock, $installed);
                },
                $json,
                $lock,
                $installed,
                [
                    'path/to/composer.json',
                    'path/to/composer.lock',
                    'path/to/installed.json',
                ],
            ];
        })();

        yield (static function (): array {
            $json = new ComposerFile('path/to/composer.json', ['name' => 'composer.json']);
            $lock = ComposerFile::createEmpty();
            $installed = ComposerFile::createEmpty();

            return [
                static function () use ($json, $lock, $installed): ComposerFiles {
                    return new ComposerFiles($json, $lock, $installed);
                },
                $json,
                $lock,
                $installed,
                ['path/to/composer.json'],
            ];
        })();

        yield (static function (): array {
            return [
                static function (): ComposerFiles {
                    return ComposerFiles::createEmpty();
                },
                ComposerFile::createEmpty(),
                ComposerFile::createEmpty(),
                ComposerFile::createEmpty(),
                [],
            ];
        })();
    }
}
