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
use PHPUnit\Framework\TestCase;

/**
 * @covers \KevinGH\Box\Composer\ComposerFiles
 *
 * @internal
 */
class ComposerFilesTest extends TestCase
{
    /**
     * @dataProvider validInstantiatorsProvider
     */
    public function test_it_can_be_created(
        Closure $create,
        ComposerFile $expectedComposerJson,
        ComposerFile $expectedComposerLock,
        ComposerFile $expectedInstalledJson,
        ComposerFile $expectedInstalledPhp,
        array $expectedPaths,
    ): void {
        /** @var ComposerFiles $actual */
        $actual = $create();

        self::assertInstanceOf(ComposerFiles::class, $actual);

        self::assertEquals($expectedComposerJson, $actual->getComposerJson());
        self::assertEquals($expectedComposerLock, $actual->getComposerLock());
        self::assertEquals($expectedInstalledJson, $actual->getInstalledJson());
        self::assertEquals($expectedInstalledPhp, $actual->getInstalledPhp());

        self::assertSame($expectedPaths, $actual->getPaths());
    }

    public static function validInstantiatorsProvider(): iterable
    {
        yield (static function (): array {
            $json = new ComposerFile('path/to/composer.json', ['name' => 'composer.json']);
            $lock = new ComposerFile('path/to/composer.lock', ['name' => 'composer.lock']);
            $installedJson = new ComposerFile('path/to/installed.json', ['name' => 'installed.json']);
            $installedPhp = new ComposerFile('path/to/installed.php', ['name' => 'installed.php']);

            return [
                static fn (): ComposerFiles => new ComposerFiles($json, $lock, $installedJson, $installedPhp),
                $json,
                $lock,
                $installedJson,
                $installedPhp,
                [
                    'path/to/composer.json',
                    'path/to/composer.lock',
                    'path/to/installed.json',
                    'path/to/installed.php',
                ],
            ];
        })();

        yield (static function (): array {
            $json = new ComposerFile('path/to/composer.json', ['name' => 'composer.json']);
            $lock = ComposerFile::createEmpty();
            $installedJson = new ComposerFile('path/to/installed.json', ['name' => 'installed.json']);
            $installedPhp = new ComposerFile('path/to/installed.php', ['name' => 'installed.php']);

            return [
                static fn (): ComposerFiles => new ComposerFiles($json, $lock, $installedJson, $installedPhp),
                $json,
                $lock,
                $installedJson,
                $installedPhp,
                [
                    'path/to/composer.json',
                    'path/to/installed.json',
                    'path/to/installed.php',
                ],
            ];
        })();

        yield (static fn (): array => [
            static fn (): ComposerFiles => ComposerFiles::createEmpty(),
            ComposerFile::createEmpty(),
            ComposerFile::createEmpty(),
            ComposerFile::createEmpty(),
            ComposerFile::createEmpty(),
            [],
        ])();
    }
}
