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

use phpDocumentor\Reflection\DocBlockFactory;
use PHPUnit\Framework\TestCase;

/**
 * @covers \KevinGH\Box\Annotation\DocblockAnnotationParser
 */
class DocblockAnnotationParserTest extends TestCase
{
    private DocblockAnnotationParser $annotationParser;

    protected function setUp(): void
    {
        $this->annotationParser = new DocblockAnnotationParser(
            DocBlockFactory::createInstance(),
            new CompactedFormatter(),
            ['ignored'],
        );
    }

    /**
     * @dataProvider docblocksProvider
     */
    public function test_it_can_parse_php_docblocks(string $docblock, array $expected): void
    {
        $actual = $this->annotationParser->parse($docblock);

        $this->assertSame($expected, $actual);
    }

    public static function docblocksProvider(): iterable
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

        yield [
            <<<'DOCBLOCK'
                /**
                 * @IGNORED
                 * @Kept
                 */
                DOCBLOCK
            ,
            ['@Kept'],
        ];
    }
}
