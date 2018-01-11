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

use KevinGH\Box\Compactor;
use KevinGH\Box\Compactor\Javascript;
use PHPUnit\Framework\TestCase;

/**
 * @covers \KevinGH\Box\Compactor\Javascript
 */
class JavascriptTest extends TestCase
{
    /**
     * @var Compactor
     */
    private $compactor;

    protected function setUp(): void
    {
        $this->compactor = new Javascript();
    }

    /**
     * @dataProvider provideFiles
     */
    public function test_it_supports_javascript_files(string $file, bool $supports): void
    {
        $contents = <<<'JS'


var foo = function (  ) {
    // with a lot of spaces
    
    var bar = ' ';
}

JS;
        $actual = $this->compactor->compact($file, $contents);

        $this->assertSame($supports, $contents !== $actual);
    }

    /**
     * @dataProvider provideJavascriptContent
     */
    public function test_it_compacts_javascript_files(string $content, string $expected): void
    {
        $file = 'foo.js';

        $actual = $this->compactor->compact($file, $content);

        $this->assertSame($expected, $actual);
    }

    public function provideFiles()
    {
        yield 'no extension' => ['test', false];

        yield 'JS file' => ['test.js', true];

        yield 'minified JS file' => ['test', false];
    }

    public function provideJavascriptContent()
    {
        yield [
            'new Array();',
            'new Array();',
        ];

        yield [
            <<<'JS'
(function(){
    var Array = function(){};
    
    return new Array(1, 2, 3, 4);
})();
JS
            ,
            <<<'JS'
(function(){var Array=function(){};return new Array(1,2,3,4);})();
JS
        ];

        yield 'invalid JavaScript' => [
            <<<'JS'
var x = "Unclosed string
JS
            ,
            <<<'JS'
var x = "Unclosed string
JS
        ];
    }
}
