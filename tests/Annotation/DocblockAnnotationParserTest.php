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

namespace KevinGH\Box\Annotation;

use Generator;
use PHPUnit\Framework\TestCase;

/**
 * @covers \KevinGH\Box\Annotation\DocblockAnnotationParser
 */
class DocblockAnnotationParserTest extends TestCase
{
    /**
     * @var DocblockAnnotationParser
     */
    private $annotationParser;

    /**
     * {@inheritdoc}
     */
    protected function setUp(): void
    {
        $this->annotationParser = new DocblockAnnotationParser(
            new DocblockParser(),
            new AnnotationDumper(),
            ['ignored']
        );
    }

    /**
     * @dataProvider provideDocblocks
     */
    public function test_it_can_parse_PHP_docblocks(string $docblock, array $expected): void
    {
        $actual = $this->annotationParser->parse($docblock);

        $this->assertSame($expected, $actual);
    }

    public function provideDocblocks(): Generator
    {
        yield [
            '// @comment',
            [],
        ];

        yield [
            <<<'DOCBLOCK'
/**
 * @Annotation
 */
DOCBLOCK
            ,
            ['@Annotation'],
        ];

        yield [
            <<<'DOCBLOCK'
/**
 * @ignored
 * @Kept
 */
DOCBLOCK
            ,
            ['@Kept'],
        ];
    }
}
