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
use LogicException;
use PHPUnit\Framework\TestCase;

/**
 * @covers \KevinGH\Box\Composer\ComposerFile
 */
class ComposerFileTest extends TestCase
{
    /**
     * @dataProvider provideValidInstantiators
     */
    public function test_it_can_be_created(Closure $create, ?string $expectedPath, array $expectedContents): void
    {
        /** @var ComposerFile $actual */
        $actual = $create();

        $this->assertInstanceOf(ComposerFile::class, $actual);

        $this->assertSame($expectedPath, $actual->getPath());
        $this->assertSame($expectedContents, $actual->getDecodedContents());
    }

    /**
     * @dataProvider provideInvalidInstantiators
     */
    public function test_it_cannot_be_created_with_invalid_values(Closure $create, string $errorMessage): void
    {
        try {
            $create();

            $this->fail('Expected exception to be thrown.');
        } catch (LogicException $exception) {
            $this->assertSame($errorMessage, $exception->getMessage());
        }
    }

    public function provideValidInstantiators(): Generator
    {
        yield [
            static function (): ComposerFile {
                return new ComposerFile(null, []);
            },
            null,
            [],
        ];

        yield [
            static function (): ComposerFile {
                return ComposerFile::createEmpty();
            },
            null,
            [],
        ];

        yield [
            static function (): ComposerFile {
                return new ComposerFile('path/to/foo', ['foo' => 'bar']);
            },
            'path/to/foo',
            ['foo' => 'bar'],
        ];
    }

    public function provideInvalidInstantiators(): Generator
    {
        yield [
            static function (): void {
                new ComposerFile('', []);
            },
            'Value "" is empty, but non empty value was expected.',
        ];

        yield [
            static function (): void {
                new ComposerFile(null, ['foo' => 'bar']);
            },
            'Value "<ARRAY>" is not the same as expected value "<ARRAY>".',
        ];
    }
}
