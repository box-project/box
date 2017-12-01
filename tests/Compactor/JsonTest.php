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

use ErrorException;
use KevinGH\Box\Compactor\Compactor;
use KevinGH\Box\Compactor\DummyFileExtensionCompactor;
use KevinGH\Box\Compactor\Javascript;
use KevinGH\Box\Compactor\Json;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\ApplicationTester;

/**
 * @covers \KevinGH\Box\Compactor\Json
 */
class JsonTest extends TestCase
{
    /**
     * @var Compactor
     */
    private $compactor;

    protected function setUp()
    {
        $this->compactor = new Json();
    }

    /**
     * @dataProvider provideFiles
     */
    public function test_it_supports_JSON_files(string $file, bool $expected)
    {
        $actual = $this->compactor->supports($file);

        $this->assertSame($expected, $actual);
    }

    /**
     * @dataProvider provideJsonContent
     */
    public function test_it_compacts_JSON_files(string $content, string $expected)
    {
        $actual = $this->compactor->compact($content);

        $this->assertSame($expected, $actual);
    }

    public function provideFiles()
    {
        yield 'no extension' => ['test', false];

        yield 'JSON file' => ['test.json', true];
    }

    public function provideJsonContent()
    {
        yield [
            '{}',
            '{}'
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
JSON
        ];

        yield 'invalid JSON' => [
            <<<'JSON'
{
JSON
            ,
            <<<'JSON'
{
JSON
        ];
    }
}
