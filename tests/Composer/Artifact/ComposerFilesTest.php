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

namespace KevinGH\Box\Composer\Artifact;

use Closure;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(ComposerFiles::class)]
class ComposerFilesTest extends TestCase
{
    #[DataProvider('validInstantiatorsProvider')]
    public function test_it_can_be_created(
        Closure $create,
        ComposerFile $expectedComposerJson,
        ComposerFile $expectedComposerLock,
        ComposerFile $expectedInstalledJson,
        array $expectedPaths,
    ): void {
        /** @var ComposerFiles $actual */
        $actual = $create();

        self::assertInstanceOf(ComposerFiles::class, $actual);

        self::assertEquals($expectedComposerJson, $actual->getComposerJson());
        self::assertEquals($expectedComposerLock, $actual->getComposerLock());
        self::assertEquals($expectedInstalledJson, $actual->getInstalledJson());

        self::assertSame($expectedPaths, $actual->getPaths());
    }

    public static function validInstantiatorsProvider(): iterable
    {
        yield (static function (): array {
            $json = new ComposerFile('path/to/composer.json', ['name' => 'composer.json']);
            $lock = new ComposerFile('path/to/composer.lock', ['name' => 'composer.lock']);
            $installed = new ComposerFile('path/to/installed.json', ['name' => 'installed.json']);

            return [
                static fn (): ComposerFiles => new ComposerFiles($json, $lock, $installed),
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
            $installed = new ComposerFile('path/to/installed.json', ['name' => 'installed.json']);

            return [
                static fn (): ComposerFiles => new ComposerFiles($json, $lock, $installed),
                $json,
                $lock,
                $installed,
                [
                    'path/to/composer.json',
                    'path/to/installed.json',
                ],
            ];
        })();

        yield (static fn (): array => [
            static fn (): ComposerFiles => ComposerFiles::createEmpty(),
            ComposerFile::createEmpty(),
            ComposerFile::createEmpty(),
            ComposerFile::createEmpty(),
            [],
        ])();
    }
}
