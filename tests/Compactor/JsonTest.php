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

namespace KevinGH\Box\Compactor;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * @internal
 */
#[CoversClass(Json::class)]
class JsonTest extends CompactorTestCase
{
    private Compactor $compactor;

    protected function setUp(): void
    {
        $this->compactor = new Json();
    }

    #[DataProvider('filesProvider')]
    public function test_it_supports_json_files(string $file, bool $supports): void
    {
        $contents = <<<'JSON'
            {
                "foo": "bar"

            }
            JSON;
        $actual = $this->compactor->compact($file, $contents);

        self::assertSame($supports, $contents !== $actual);
    }

    #[DataProvider('jsonContentProvider')]
    public function test_it_compacts_json_files(string $content, string $expected): void
    {
        $file = 'file.json';

        $actual = $this->compactor->compact($file, $content);

        self::assertSame($expected, $actual);
    }

    #[DataProvider('jsonContentProvider')]
    public function test_it_compacts__composer_lock_files(string $content, string $expected): void
    {
        $file = 'composer.lock';

        $actual = $this->compactor->compact($file, $content);

        self::assertSame($expected, $actual);
    }

    public static function compactorProvider(): iterable
    {
        yield [new Json()];
    }

    public static function filesProvider(): iterable
    {
        yield 'no extension' => ['test', false];

        yield 'JSON file' => ['test.json', true];
    }

    public static function jsonContentProvider(): iterable
    {
        yield [
            '{}',
            '{}',
        ];

        yield [
            <<<'JSON'
                {
                    "require": {
                        "humbug/php-scoper": "^1.0",
                        "infection/infection": "^1.0"
                    }
                }
                JSON,
            <<<'JSON'
                {"require":{"humbug\/php-scoper":"^1.0","infection\/infection":"^1.0"}}
                JSON,
        ];

        yield 'invalid JSON' => [
            <<<'JSON'
                {
                JSON,
            <<<'JSON'
                {
                JSON,
        ];
    }
}
