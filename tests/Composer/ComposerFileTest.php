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
use LogicException;
use PHPUnit\Framework\TestCase;

/**
 * @covers \KevinGH\Box\Composer\ComposerFile
 */
class ComposerFileTest extends TestCase
{
    /**
     * @dataProvider validInstantiatorsProvider
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
     * @dataProvider invalidInstantiatorsProvider
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

    public static function validInstantiatorsProvider(): iterable
    {
        yield [
            static fn (): ComposerFile => new ComposerFile(null, []),
            null,
            [],
        ];

        yield [
            static fn (): ComposerFile => ComposerFile::createEmpty(),
            null,
            [],
        ];

        yield [
            static fn (): ComposerFile => new ComposerFile('path/to/foo', ['foo' => 'bar']),
            'path/to/foo',
            ['foo' => 'bar'],
        ];
    }

    public static function invalidInstantiatorsProvider(): iterable
    {
        yield [
            static function (): void {
                new ComposerFile('', []);
            },
            'Expected a non-empty value. Got: ""',
        ];

        yield [
            static function (): void {
                new ComposerFile(null, ['foo' => 'bar']);
            },
            'Expected a value identical to array. Got: array',
        ];
    }
}
