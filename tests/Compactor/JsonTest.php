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

use PHPUnit\Framework\TestCase;
use function serialize;
use function unserialize;

/**
 * @covers \KevinGH\Box\Compactor\Json
 */
class JsonTest extends TestCase
{
    private Compactor $compactor;

    protected function setUp(): void
    {
        $this->compactor = new Json();
    }

    /**
     * @dataProvider filesProvider
     */
    public function test_it_supports_json_files(string $file, bool $supports): void
    {
        $contents = <<<'JSON'
            {
                "foo": "bar"

            }
            JSON;
        $actual = $this->compactor->compact($file, $contents);

        $this->assertSame($supports, $contents !== $actual);
    }

    /**
     * @dataProvider jsonContentProvider
     */
    public function test_it_compacts_json_files(string $content, string $expected): void
    {
        $file = 'file.json';

        $actual = $this->compactor->compact($file, $content);

        $this->assertSame($expected, $actual);
    }

    /**
     * @dataProvider jsonContentProvider
     */
    public function test_it_compacts__composer_lock_files(string $content, string $expected): void
    {
        $file = 'composer.lock';

        $actual = $this->compactor->compact($file, $content);

        $this->assertSame($expected, $actual);
    }

    public function test_it_is_serializable(): void
    {
        $this->assertEquals(
            $this->compactor,
            unserialize(serialize($this->compactor)),
        );
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
                JSON
            ,
            <<<'JSON'
                {"require":{"humbug\/php-scoper":"^1.0","infection\/infection":"^1.0"}}
                JSON,
        ];

        yield 'invalid JSON' => [
            <<<'JSON'
                {
                JSON
            ,
            <<<'JSON'
                {
                JSON,
        ];
    }
}
