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
use LogicException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(ComposerFile::class)]
class ComposerFileTest extends TestCase
{
    #[DataProvider('validInstantiatorsProvider')]
    public function test_it_can_be_created(Closure $create, ?string $expectedPath, array $expectedContents): void
    {
        /** @var ComposerFile $actual */
        $actual = $create();

        self::assertInstanceOf(ComposerFile::class, $actual);

        self::assertSame($expectedPath, $actual->getPath());
        self::assertSame($expectedContents, $actual->getDecodedContents());
    }

    #[DataProvider('invalidInstantiatorsProvider')]
    public function test_it_cannot_be_created_with_invalid_values(Closure $create, string $errorMessage): void
    {
        try {
            $create();

            self::fail('Expected exception to be thrown.');
        } catch (LogicException $exception) {
            self::assertSame($errorMessage, $exception->getMessage());
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
