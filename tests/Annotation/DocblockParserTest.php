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
use Hoa\Compiler\Visitor\Dump;
use PHPUnit\Framework\TestCase;

/**
 * @covers \KevinGH\Box\Annotation\DocblockParser
 */
class DocblockParserTest extends TestCase
{
    /**
     * @var DocblockParser
     */
    private $docblockParser;

    /**
     * {@inheritdoc}
     */
    protected function setUp(): void
    {
        $this->docblockParser = new DocblockParser();
    }

    /**
     * @dataProvider provideDocblocks
     */
    public function test_it_can_parse_PHP_docblocks(string $docblock, string $expected): void
    {
        $actual = (new Dump())->visit(
            $this->docblockParser->parse($docblock)
        );

        $this->assertSame($expected, $actual);
    }

    /**
     * @dataProvider provideInvalidDocblocks
     */
    public function test_it_throws_an_error_if_the_annotation_is_invalid(string $docblock, string $expected): void
    {
        try {
            $this->docblockParser->parse($docblock);

            $this->fail('Expected exception to be thrown.');
        } catch (InvalidDocblock $exception) {
            $this->assertSame(
                $expected,
                $exception->getMessage()
            );
        }
    }

    public function provideDocblocks(): Generator
    {
        yield [
            '// @comment',
            <<<'TRACE'
>  #null

TRACE
        ];

        yield [
            <<<'DOCBLOCK'
        /**
         * Empty.
         */
DOCBLOCK
            ,
            <<<'TRACE'
>  #annotations

TRACE
        ];

        yield [
            <<<'DOCBLOCK'
/**
 * @Annotation
 */
DOCBLOCK
            ,
            <<<'TRACE'
>  #annotations
>  >  #annotation
>  >  >  token(annot:simple_identifier, Annotation)

TRACE
        ];

        yield [
            <<<'DOCBLOCK'
/**
 * @Annotation()
 */
DOCBLOCK
            ,
            <<<'TRACE'
>  #annotations
>  >  #annotation
>  >  >  token(annot:valued_identifier, Annotation)

TRACE
        ];

        yield [
            <<<'DOCBLOCK'
/**
 * @Annotation ()
 */
DOCBLOCK
            ,
            <<<'TRACE'
>  #annotations
>  >  #annotation
>  >  >  token(annot:simple_identifier, Annotation)

TRACE
        ];

        yield [
            <<<'DOCBLOCK'
/**
 * @A
 * @B
 */
DOCBLOCK
            ,
            <<<'TRACE'
>  #annotations
>  >  #annotation
>  >  >  token(annot:simple_identifier, A)
>  >  #annotation
>  >  >  token(annot:simple_identifier, B)

TRACE
        ];

        yield [
            <<<'DOCBLOCK'
/**
 * @A()
 * @B()
 */
DOCBLOCK
            ,
            <<<'TRACE'
>  #annotations
>  >  #annotation
>  >  >  token(annot:valued_identifier, A)
>  >  #annotation
>  >  >  token(annot:valued_identifier, B)

TRACE
        ];

        yield [
            <<<'DOCBLOCK'
/**
 * @Namespaced\Annotation
 */
DOCBLOCK
            ,
            <<<'TRACE'
>  #annotations
>  >  #annotation
>  >  >  token(annot:simple_identifier, Namespaced\Annotation)

TRACE
        ];

        yield [
            <<<'DOCBLOCK'
/**
 * @Namespaced\ Annotation
 */
DOCBLOCK
            ,
            <<<'TRACE'
>  #annotations
>  >  #annotation
>  >  >  token(annot:simple_identifier, Namespaced)

TRACE
        ];

        yield [
            <<<'DOCBLOCK'
/**
 * @Namespaced\Annotation()
 */
DOCBLOCK
            ,
            <<<'TRACE'
>  #annotations
>  >  #annotation
>  >  >  token(annot:valued_identifier, Namespaced\Annotation)

TRACE
        ];

        yield [
            <<<'DOCBLOCK'
/**
 * @Annotation("string")
 */
DOCBLOCK
            ,
            <<<'TRACE'
>  #annotations
>  >  #annotation
>  >  >  token(annot:valued_identifier, Annotation)
>  >  >  #parameters
>  >  >  >  #unnamed_parameter
>  >  >  >  >  #value
>  >  >  >  >  >  #string
>  >  >  >  >  >  >  token(string:string, string)

TRACE
        ];

        yield [
            <<<'DOCBLOCK'
/**
 * @Annotation(
 *     "string"
 * )
 */
DOCBLOCK
            ,
            <<<'TRACE'
>  #annotations
>  >  #annotation
>  >  >  token(annot:valued_identifier, Annotation)
>  >  >  #parameters
>  >  >  >  #unnamed_parameter
>  >  >  >  >  #value
>  >  >  >  >  >  #string
>  >  >  >  >  >  >  token(string:string, string)

TRACE
        ];

        yield [
            <<<'DOCBLOCK'
/**
 * @Annotation(123, "string", 1.23, false, true, null)
 */
DOCBLOCK
            ,
            <<<'TRACE'
>  #annotations
>  >  #annotation
>  >  >  token(annot:valued_identifier, Annotation)
>  >  >  #parameters
>  >  >  >  #unnamed_parameter
>  >  >  >  >  #value
>  >  >  >  >  >  token(value:integer, 123)
>  >  >  >  #unnamed_parameter
>  >  >  >  >  #value
>  >  >  >  >  >  #string
>  >  >  >  >  >  >  token(string:string, string)
>  >  >  >  #unnamed_parameter
>  >  >  >  >  #value
>  >  >  >  >  >  token(value:float, 1.23)
>  >  >  >  #unnamed_parameter
>  >  >  >  >  #value
>  >  >  >  >  >  token(value:boolean, false)
>  >  >  >  #unnamed_parameter
>  >  >  >  >  #value
>  >  >  >  >  >  token(value:boolean, true)
>  >  >  >  #unnamed_parameter
>  >  >  >  >  #value
>  >  >  >  >  >  token(value:null, null)

TRACE
        ];

        yield [
            <<<'DOCBLOCK'
/**
 * @Annotation(FALSE, TRUE, NULL)
 */
DOCBLOCK
            ,
            <<<'TRACE'
>  #annotations
>  >  #annotation
>  >  >  token(annot:valued_identifier, Annotation)
>  >  >  #parameters
>  >  >  >  #unnamed_parameter
>  >  >  >  >  #value
>  >  >  >  >  >  token(value:boolean, FALSE)
>  >  >  >  #unnamed_parameter
>  >  >  >  >  #value
>  >  >  >  >  >  token(value:boolean, TRUE)
>  >  >  >  #unnamed_parameter
>  >  >  >  >  #value
>  >  >  >  >  >  token(value:null, NULL)

TRACE
        ];

        yield [
            <<<'DOCBLOCK'
/**
 * @Annotation(key="value")
 */
DOCBLOCK
            ,
            <<<'TRACE'
>  #annotations
>  >  #annotation
>  >  >  token(annot:valued_identifier, Annotation)
>  >  >  #parameters
>  >  >  >  #named_parameter
>  >  >  >  >  token(value:identifier, key)
>  >  >  >  >  #value
>  >  >  >  >  >  #string
>  >  >  >  >  >  >  token(string:string, value)

TRACE
        ];

        yield [
            <<<'DOCBLOCK'
/**
 * @Annotation(a="b", c="d")
 */
DOCBLOCK
            ,
            <<<'TRACE'
>  #annotations
>  >  #annotation
>  >  >  token(annot:valued_identifier, Annotation)
>  >  >  #parameters
>  >  >  >  #named_parameter
>  >  >  >  >  token(value:identifier, a)
>  >  >  >  >  #value
>  >  >  >  >  >  #string
>  >  >  >  >  >  >  token(string:string, b)
>  >  >  >  #named_parameter
>  >  >  >  >  token(value:identifier, c)
>  >  >  >  >  #value
>  >  >  >  >  >  #string
>  >  >  >  >  >  >  token(string:string, d)

TRACE
        ];

        yield [
            <<<'DOCBLOCK'
/**
 * @Annotation(
 *     a=123,
 *     b="string",
 *     c=1.23,
 *     e=false,
 *     f=true,
 *     g=null
 * )
 */
DOCBLOCK
            ,
            <<<'TRACE'
>  #annotations
>  >  #annotation
>  >  >  token(annot:valued_identifier, Annotation)
>  >  >  #parameters
>  >  >  >  #named_parameter
>  >  >  >  >  token(value:identifier, a)
>  >  >  >  >  #value
>  >  >  >  >  >  token(value:integer, 123)
>  >  >  >  #named_parameter
>  >  >  >  >  token(value:identifier, b)
>  >  >  >  >  #value
>  >  >  >  >  >  #string
>  >  >  >  >  >  >  token(string:string, string)
>  >  >  >  #named_parameter
>  >  >  >  >  token(value:identifier, c)
>  >  >  >  >  #value
>  >  >  >  >  >  token(value:float, 1.23)
>  >  >  >  #named_parameter
>  >  >  >  >  token(value:identifier, e)
>  >  >  >  >  #value
>  >  >  >  >  >  token(value:boolean, false)
>  >  >  >  #named_parameter
>  >  >  >  >  token(value:identifier, f)
>  >  >  >  >  #value
>  >  >  >  >  >  token(value:boolean, true)
>  >  >  >  #named_parameter
>  >  >  >  >  token(value:identifier, g)
>  >  >  >  >  #value
>  >  >  >  >  >  token(value:null, null)

TRACE
        ];

        yield [
            <<<'DOCBLOCK'
/**
 * @Annotation({})
 */
DOCBLOCK
            ,
            <<<'TRACE'
>  #annotations
>  >  #annotation
>  >  >  token(annot:valued_identifier, Annotation)
>  >  >  #parameters
>  >  >  >  #unnamed_parameter
>  >  >  >  >  #value
>  >  >  >  >  >  #list

TRACE
        ];

        yield [
            <<<'DOCBLOCK'
/**
 * @Annotation(key={})
 */
DOCBLOCK
            ,
            <<<'TRACE'
>  #annotations
>  >  #annotation
>  >  >  token(annot:valued_identifier, Annotation)
>  >  >  #parameters
>  >  >  >  #named_parameter
>  >  >  >  >  token(value:identifier, key)
>  >  >  >  >  #value
>  >  >  >  >  >  #list

TRACE
        ];

        yield [
            <<<'DOCBLOCK'
/**
 * @Annotation({"string"})
 */
DOCBLOCK
            ,
            <<<'TRACE'
>  #annotations
>  >  #annotation
>  >  >  token(annot:valued_identifier, Annotation)
>  >  >  #parameters
>  >  >  >  #unnamed_parameter
>  >  >  >  >  #value
>  >  >  >  >  >  #list
>  >  >  >  >  >  >  #value
>  >  >  >  >  >  >  >  #string
>  >  >  >  >  >  >  >  >  token(string:string, string)

TRACE
        ];

        yield [
            <<<'DOCBLOCK'
/**
 * @Annotation(
 *     {
 *         "string"
 *     }
 * )
 */
DOCBLOCK
            ,
            <<<'TRACE'
>  #annotations
>  >  #annotation
>  >  >  token(annot:valued_identifier, Annotation)
>  >  >  #parameters
>  >  >  >  #unnamed_parameter
>  >  >  >  >  #value
>  >  >  >  >  >  #list
>  >  >  >  >  >  >  #value
>  >  >  >  >  >  >  >  #string
>  >  >  >  >  >  >  >  >  token(string:string, string)

TRACE
        ];

        yield [
            <<<'DOCBLOCK'
        /**
         * @Annotation({123, "string", 1.23, false, true, null})
         */
DOCBLOCK
            ,
            <<<'TRACE'
>  #annotations
>  >  #annotation
>  >  >  token(annot:valued_identifier, Annotation)
>  >  >  #parameters
>  >  >  >  #unnamed_parameter
>  >  >  >  >  #value
>  >  >  >  >  >  #list
>  >  >  >  >  >  >  #value
>  >  >  >  >  >  >  >  token(value:integer, 123)
>  >  >  >  >  >  >  #value
>  >  >  >  >  >  >  >  #string
>  >  >  >  >  >  >  >  >  token(string:string, string)
>  >  >  >  >  >  >  #value
>  >  >  >  >  >  >  >  token(value:float, 1.23)
>  >  >  >  >  >  >  #value
>  >  >  >  >  >  >  >  token(value:boolean, false)
>  >  >  >  >  >  >  #value
>  >  >  >  >  >  >  >  token(value:boolean, true)
>  >  >  >  >  >  >  #value
>  >  >  >  >  >  >  >  token(value:null, null)

TRACE
        ];

        yield [
            <<<'DOCBLOCK'
/**
 * @Annotation({key="value"})
 */
DOCBLOCK
            ,
            <<<'TRACE'
>  #annotations
>  >  #annotation
>  >  >  token(annot:valued_identifier, Annotation)
>  >  >  #parameters
>  >  >  >  #unnamed_parameter
>  >  >  >  >  #value
>  >  >  >  >  >  #map
>  >  >  >  >  >  >  #pair_equal
>  >  >  >  >  >  >  >  token(value:identifier, key)
>  >  >  >  >  >  >  >  #value
>  >  >  >  >  >  >  >  >  #string
>  >  >  >  >  >  >  >  >  >  token(string:string, value)

TRACE
        ];

        yield [
            <<<'DOCBLOCK'
/**
 * @Annotation({"key"="value"})
 */
DOCBLOCK
            ,
            <<<'TRACE'
>  #annotations
>  >  #annotation
>  >  >  token(annot:valued_identifier, Annotation)
>  >  >  #parameters
>  >  >  >  #unnamed_parameter
>  >  >  >  >  #value
>  >  >  >  >  >  #map
>  >  >  >  >  >  >  #pair_equal
>  >  >  >  >  >  >  >  #string
>  >  >  >  >  >  >  >  >  token(string:string, key)
>  >  >  >  >  >  >  >  #value
>  >  >  >  >  >  >  >  >  #string
>  >  >  >  >  >  >  >  >  >  token(string:string, value)

TRACE
        ];

        yield [
            <<<'DOCBLOCK'
/**
 * @Annotation({a="b", c="d"})
 */
DOCBLOCK
            ,
            <<<'TRACE'
>  #annotations
>  >  #annotation
>  >  >  token(annot:valued_identifier, Annotation)
>  >  >  #parameters
>  >  >  >  #unnamed_parameter
>  >  >  >  >  #value
>  >  >  >  >  >  #map
>  >  >  >  >  >  >  #pair_equal
>  >  >  >  >  >  >  >  token(value:identifier, a)
>  >  >  >  >  >  >  >  #value
>  >  >  >  >  >  >  >  >  #string
>  >  >  >  >  >  >  >  >  >  token(string:string, b)
>  >  >  >  >  >  >  #pair_equal
>  >  >  >  >  >  >  >  token(value:identifier, c)
>  >  >  >  >  >  >  >  #value
>  >  >  >  >  >  >  >  >  #string
>  >  >  >  >  >  >  >  >  >  token(string:string, d)

TRACE
        ];

        yield [
            <<<'DOCBLOCK'
/**
 * @Annotation({a="b", "c"="d", 123="e"})
 */
DOCBLOCK
            ,
            <<<'TRACE'
>  #annotations
>  >  #annotation
>  >  >  token(annot:valued_identifier, Annotation)
>  >  >  #parameters
>  >  >  >  #unnamed_parameter
>  >  >  >  >  #value
>  >  >  >  >  >  #map
>  >  >  >  >  >  >  #pair_equal
>  >  >  >  >  >  >  >  token(value:identifier, a)
>  >  >  >  >  >  >  >  #value
>  >  >  >  >  >  >  >  >  #string
>  >  >  >  >  >  >  >  >  >  token(string:string, b)
>  >  >  >  >  >  >  #pair_equal
>  >  >  >  >  >  >  >  #string
>  >  >  >  >  >  >  >  >  token(string:string, c)
>  >  >  >  >  >  >  >  #value
>  >  >  >  >  >  >  >  >  #string
>  >  >  >  >  >  >  >  >  >  token(string:string, d)
>  >  >  >  >  >  >  #pair_equal
>  >  >  >  >  >  >  >  token(value:integer, 123)
>  >  >  >  >  >  >  >  #value
>  >  >  >  >  >  >  >  >  #string
>  >  >  >  >  >  >  >  >  >  token(string:string, e)

TRACE
        ];

        yield [
            <<<'DOCBLOCK'
/**
 * @Annotation({key={}})
 */
DOCBLOCK
            ,
            <<<'TRACE'
>  #annotations
>  >  #annotation
>  >  >  token(annot:valued_identifier, Annotation)
>  >  >  #parameters
>  >  >  >  #unnamed_parameter
>  >  >  >  >  #value
>  >  >  >  >  >  #map
>  >  >  >  >  >  >  #pair_equal
>  >  >  >  >  >  >  >  token(value:identifier, key)
>  >  >  >  >  >  >  >  #value
>  >  >  >  >  >  >  >  >  #list

TRACE
        ];

        yield [
            <<<'DOCBLOCK'
/**
 * @Annotation(a={b={}})
 */
DOCBLOCK
            ,
            <<<'TRACE'
>  #annotations
>  >  #annotation
>  >  >  token(annot:valued_identifier, Annotation)
>  >  >  #parameters
>  >  >  >  #named_parameter
>  >  >  >  >  token(value:identifier, a)
>  >  >  >  >  #value
>  >  >  >  >  >  #map
>  >  >  >  >  >  >  #pair_equal
>  >  >  >  >  >  >  >  token(value:identifier, b)
>  >  >  >  >  >  >  >  #value
>  >  >  >  >  >  >  >  >  #list

TRACE
        ];

        yield [
            <<<'DOCBLOCK'
/**
 * @Annotation({key: {}})
 */
DOCBLOCK
            ,
            <<<'TRACE'
>  #annotations
>  >  #annotation
>  >  >  token(annot:valued_identifier, Annotation)
>  >  >  #parameters
>  >  >  >  #unnamed_parameter
>  >  >  >  >  #value
>  >  >  >  >  >  #map
>  >  >  >  >  >  >  #pair_colon
>  >  >  >  >  >  >  >  token(value:identifier, key)
>  >  >  >  >  >  >  >  #value
>  >  >  >  >  >  >  >  >  #list

TRACE
        ];

        yield [
            <<<'DOCBLOCK'
/**
 * @Annotation(a={b: {}})
 */
DOCBLOCK
            ,
            <<<'TRACE'
>  #annotations
>  >  #annotation
>  >  >  token(annot:valued_identifier, Annotation)
>  >  >  #parameters
>  >  >  >  #named_parameter
>  >  >  >  >  token(value:identifier, a)
>  >  >  >  >  #value
>  >  >  >  >  >  #map
>  >  >  >  >  >  >  #pair_colon
>  >  >  >  >  >  >  >  token(value:identifier, b)
>  >  >  >  >  >  >  >  #value
>  >  >  >  >  >  >  >  >  #list

TRACE
        ];

        yield [
            <<<'DOCBLOCK'
/**
 * @Annotation({key: "value"})
 */
DOCBLOCK
            ,
            <<<'TRACE'
>  #annotations
>  >  #annotation
>  >  >  token(annot:valued_identifier, Annotation)
>  >  >  #parameters
>  >  >  >  #unnamed_parameter
>  >  >  >  >  #value
>  >  >  >  >  >  #map
>  >  >  >  >  >  >  #pair_colon
>  >  >  >  >  >  >  >  token(value:identifier, key)
>  >  >  >  >  >  >  >  #value
>  >  >  >  >  >  >  >  >  #string
>  >  >  >  >  >  >  >  >  >  token(string:string, value)

TRACE
        ];

        yield [
            <<<'DOCBLOCK'
/**
 * @Annotation({a: "b", c: "d"})
 */
DOCBLOCK
            ,
            <<<'TRACE'
>  #annotations
>  >  #annotation
>  >  >  token(annot:valued_identifier, Annotation)
>  >  >  #parameters
>  >  >  >  #unnamed_parameter
>  >  >  >  >  #value
>  >  >  >  >  >  #map
>  >  >  >  >  >  >  #pair_colon
>  >  >  >  >  >  >  >  token(value:identifier, a)
>  >  >  >  >  >  >  >  #value
>  >  >  >  >  >  >  >  >  #string
>  >  >  >  >  >  >  >  >  >  token(string:string, b)
>  >  >  >  >  >  >  #pair_colon
>  >  >  >  >  >  >  >  token(value:identifier, c)
>  >  >  >  >  >  >  >  #value
>  >  >  >  >  >  >  >  >  #string
>  >  >  >  >  >  >  >  >  >  token(string:string, d)

TRACE
        ];

        yield [
            <<<'DOCBLOCK'
/**
 * @Annotation({a: "b", "c": "d", 123: "e"})
 */
DOCBLOCK
            ,
            <<<'TRACE'
>  #annotations
>  >  #annotation
>  >  >  token(annot:valued_identifier, Annotation)
>  >  >  #parameters
>  >  >  >  #unnamed_parameter
>  >  >  >  >  #value
>  >  >  >  >  >  #map
>  >  >  >  >  >  >  #pair_colon
>  >  >  >  >  >  >  >  token(value:identifier, a)
>  >  >  >  >  >  >  >  #value
>  >  >  >  >  >  >  >  >  #string
>  >  >  >  >  >  >  >  >  >  token(string:string, b)
>  >  >  >  >  >  >  #pair_colon
>  >  >  >  >  >  >  >  #string
>  >  >  >  >  >  >  >  >  token(string:string, c)
>  >  >  >  >  >  >  >  #value
>  >  >  >  >  >  >  >  >  #string
>  >  >  >  >  >  >  >  >  >  token(string:string, d)
>  >  >  >  >  >  >  #pair_colon
>  >  >  >  >  >  >  >  token(value:integer, 123)
>  >  >  >  >  >  >  >  #value
>  >  >  >  >  >  >  >  >  #string
>  >  >  >  >  >  >  >  >  >  token(string:string, e)

TRACE
        ];

        yield [
            <<<'DOCBLOCK'
/**
 * @Annotation(
 *     {
 *         "a",
 *         {
 *             {
 *                 "c"
 *             },
 *             "b"
 *         }
 *     }
 * )
 */
DOCBLOCK
            ,
            <<<'TRACE'
>  #annotations
>  >  #annotation
>  >  >  token(annot:valued_identifier, Annotation)
>  >  >  #parameters
>  >  >  >  #unnamed_parameter
>  >  >  >  >  #value
>  >  >  >  >  >  #list
>  >  >  >  >  >  >  #value
>  >  >  >  >  >  >  >  #string
>  >  >  >  >  >  >  >  >  token(string:string, a)
>  >  >  >  >  >  >  #value
>  >  >  >  >  >  >  >  #list
>  >  >  >  >  >  >  >  >  #value
>  >  >  >  >  >  >  >  >  >  #list
>  >  >  >  >  >  >  >  >  >  >  #value
>  >  >  >  >  >  >  >  >  >  >  >  #string
>  >  >  >  >  >  >  >  >  >  >  >  >  token(string:string, c)
>  >  >  >  >  >  >  >  >  #value
>  >  >  >  >  >  >  >  >  >  #string
>  >  >  >  >  >  >  >  >  >  >  token(string:string, b)

TRACE
        ];

        yield [
            <<<'DOCBLOCK'
/**
 * @Annotation(@Nested)
 */
DOCBLOCK
            ,
            <<<'TRACE'
>  #annotations
>  >  #annotation
>  >  >  token(annot:valued_identifier, Annotation)
>  >  >  #parameters
>  >  >  >  #unnamed_parameter
>  >  >  >  >  #value
>  >  >  >  >  >  #annotation
>  >  >  >  >  >  >  token(annot:simple_identifier, Nested)

TRACE
        ];

        yield [
            <<<'DOCBLOCK'
/**
 * @Annotation(@Nested())
 */
DOCBLOCK
            ,
            <<<'TRACE'
>  #annotations
>  >  #annotation
>  >  >  token(annot:valued_identifier, Annotation)
>  >  >  #parameters
>  >  >  >  #unnamed_parameter
>  >  >  >  >  #value
>  >  >  >  >  >  #annotation
>  >  >  >  >  >  >  token(annot:valued_identifier, Nested)

TRACE
        ];

        yield [
            <<<'DOCBLOCK'
/**
 * @Annotation(@Nested, @Nested)
 */
DOCBLOCK
            ,
            <<<'TRACE'
>  #annotations
>  >  #annotation
>  >  >  token(annot:valued_identifier, Annotation)
>  >  >  #parameters
>  >  >  >  #unnamed_parameter
>  >  >  >  >  #value
>  >  >  >  >  >  #annotation
>  >  >  >  >  >  >  token(annot:simple_identifier, Nested)
>  >  >  >  #unnamed_parameter
>  >  >  >  >  #value
>  >  >  >  >  >  #annotation
>  >  >  >  >  >  >  token(annot:simple_identifier, Nested)

TRACE
        ];

        yield [
            <<<'DOCBLOCK'
/**
 * @Annotation(
 *     @Nested(),
 *     @Nested()
 * )
 */
DOCBLOCK
            ,
            <<<'TRACE'
>  #annotations
>  >  #annotation
>  >  >  token(annot:valued_identifier, Annotation)
>  >  >  #parameters
>  >  >  >  #unnamed_parameter
>  >  >  >  >  #value
>  >  >  >  >  >  #annotation
>  >  >  >  >  >  >  token(annot:valued_identifier, Nested)
>  >  >  >  #unnamed_parameter
>  >  >  >  >  #value
>  >  >  >  >  >  #annotation
>  >  >  >  >  >  >  token(annot:valued_identifier, Nested)

TRACE
        ];

        yield [
            <<<'DOCBLOCK'
/**
 * @Annotation(key=@Nested)
 */
DOCBLOCK
            ,
            <<<'TRACE'
>  #annotations
>  >  #annotation
>  >  >  token(annot:valued_identifier, Annotation)
>  >  >  #parameters
>  >  >  >  #named_parameter
>  >  >  >  >  token(value:identifier, key)
>  >  >  >  >  #value
>  >  >  >  >  >  #annotation
>  >  >  >  >  >  >  token(annot:simple_identifier, Nested)

TRACE
        ];

        yield [
            <<<'DOCBLOCK'
/**
 * @Annotation(a=@Nested(),b=@Nested)
 */
DOCBLOCK
            ,
            <<<'TRACE'
>  #annotations
>  >  #annotation
>  >  >  token(annot:valued_identifier, Annotation)
>  >  >  #parameters
>  >  >  >  #named_parameter
>  >  >  >  >  token(value:identifier, a)
>  >  >  >  >  #value
>  >  >  >  >  >  #annotation
>  >  >  >  >  >  >  token(annot:valued_identifier, Nested)
>  >  >  >  #named_parameter
>  >  >  >  >  token(value:identifier, b)
>  >  >  >  >  #value
>  >  >  >  >  >  #annotation
>  >  >  >  >  >  >  token(annot:simple_identifier, Nested)

TRACE
        ];

        yield [
            <<<'DOCBLOCK'
/**
 * @Annotation({key=@Nested})
 */
DOCBLOCK
            ,
            <<<'TRACE'
>  #annotations
>  >  #annotation
>  >  >  token(annot:valued_identifier, Annotation)
>  >  >  #parameters
>  >  >  >  #unnamed_parameter
>  >  >  >  >  #value
>  >  >  >  >  >  #map
>  >  >  >  >  >  >  #pair_equal
>  >  >  >  >  >  >  >  token(value:identifier, key)
>  >  >  >  >  >  >  >  #value
>  >  >  >  >  >  >  >  >  #annotation
>  >  >  >  >  >  >  >  >  >  token(annot:simple_identifier, Nested)

TRACE
        ];

        yield [
            <<<'DOCBLOCK'
/**
 * @Annotation({a=@Nested(),b=@Nested})
 */
DOCBLOCK
            ,
            <<<'TRACE'
>  #annotations
>  >  #annotation
>  >  >  token(annot:valued_identifier, Annotation)
>  >  >  #parameters
>  >  >  >  #unnamed_parameter
>  >  >  >  >  #value
>  >  >  >  >  >  #map
>  >  >  >  >  >  >  #pair_equal
>  >  >  >  >  >  >  >  token(value:identifier, a)
>  >  >  >  >  >  >  >  #value
>  >  >  >  >  >  >  >  >  #annotation
>  >  >  >  >  >  >  >  >  >  token(annot:valued_identifier, Nested)
>  >  >  >  >  >  >  #pair_equal
>  >  >  >  >  >  >  >  token(value:identifier, b)
>  >  >  >  >  >  >  >  #value
>  >  >  >  >  >  >  >  >  #annotation
>  >  >  >  >  >  >  >  >  >  token(annot:simple_identifier, Nested)

TRACE
        ];

        yield [
            <<<'DOCBLOCK'
/**
 * @Annotation(
 *     @Nested(
 *         {
 *             "a",
 *             {
 *                 {
 *                     "c"
 *                 },
 *                 "b"
 *             }
 *         }
 *     ),
 *     @Nested(
 *         {
 *             "d",
 *             {
 *                 {
 *                     "f",
 *                 },
 *                 "e"
 *             }
 *         }
 *     )
 * )
 */
DOCBLOCK
            ,
            <<<'TRACE'
>  #annotations
>  >  #annotation
>  >  >  token(annot:valued_identifier, Annotation)
>  >  >  #parameters
>  >  >  >  #unnamed_parameter
>  >  >  >  >  #value
>  >  >  >  >  >  #annotation
>  >  >  >  >  >  >  token(annot:valued_identifier, Nested)
>  >  >  >  >  >  >  #parameters
>  >  >  >  >  >  >  >  #unnamed_parameter
>  >  >  >  >  >  >  >  >  #value
>  >  >  >  >  >  >  >  >  >  #list
>  >  >  >  >  >  >  >  >  >  >  #value
>  >  >  >  >  >  >  >  >  >  >  >  #string
>  >  >  >  >  >  >  >  >  >  >  >  >  token(string:string, a)
>  >  >  >  >  >  >  >  >  >  >  #value
>  >  >  >  >  >  >  >  >  >  >  >  #list
>  >  >  >  >  >  >  >  >  >  >  >  >  #value
>  >  >  >  >  >  >  >  >  >  >  >  >  >  #list
>  >  >  >  >  >  >  >  >  >  >  >  >  >  >  #value
>  >  >  >  >  >  >  >  >  >  >  >  >  >  >  >  #string
>  >  >  >  >  >  >  >  >  >  >  >  >  >  >  >  >  token(string:string, c)
>  >  >  >  >  >  >  >  >  >  >  >  >  #value
>  >  >  >  >  >  >  >  >  >  >  >  >  >  #string
>  >  >  >  >  >  >  >  >  >  >  >  >  >  >  token(string:string, b)
>  >  >  >  #unnamed_parameter
>  >  >  >  >  #value
>  >  >  >  >  >  #annotation
>  >  >  >  >  >  >  token(annot:valued_identifier, Nested)
>  >  >  >  >  >  >  #parameters
>  >  >  >  >  >  >  >  #unnamed_parameter
>  >  >  >  >  >  >  >  >  #value
>  >  >  >  >  >  >  >  >  >  #list
>  >  >  >  >  >  >  >  >  >  >  #value
>  >  >  >  >  >  >  >  >  >  >  >  #string
>  >  >  >  >  >  >  >  >  >  >  >  >  token(string:string, d)
>  >  >  >  >  >  >  >  >  >  >  #value
>  >  >  >  >  >  >  >  >  >  >  >  #list
>  >  >  >  >  >  >  >  >  >  >  >  >  #value
>  >  >  >  >  >  >  >  >  >  >  >  >  >  #list
>  >  >  >  >  >  >  >  >  >  >  >  >  >  >  #value
>  >  >  >  >  >  >  >  >  >  >  >  >  >  >  >  #string
>  >  >  >  >  >  >  >  >  >  >  >  >  >  >  >  >  token(string:string, f)
>  >  >  >  >  >  >  >  >  >  >  >  >  #value
>  >  >  >  >  >  >  >  >  >  >  >  >  >  #string
>  >  >  >  >  >  >  >  >  >  >  >  >  >  >  token(string:string, e)

TRACE
        ];

        yield [
            <<<DOCBLOCK
/**
 * \@Escaped
 */
DOCBLOCK
            ,
            <<<'TRACE'
>  #annotations
>  >  #annotation
>  >  >  token(annot:simple_identifier, Escaped)

TRACE
        ];

        yield [
            <<<'DOCBLOCK'
/**
 * @author Made Up <author@web.com>
 */
DOCBLOCK
            ,
            <<<'TRACE'
>  #annotations
>  >  #annotation
>  >  >  token(annot:simple_identifier, author)

TRACE
        ];
    }

    public function provideInvalidDocblocks(): Generator
    {
        yield [
            '/**@a(1,,)*/',
            <<<'EOF'
Could not parse the following docblock: "/**@a(1,,)*/". Cause: "Unexpected token "," (comma) at line 1 and column 9:
/**@a(1,,)*/
        ↑"
EOF
        ];

        yield [
            '/**@\\*/',
            <<<'EOF'
Could not parse the following docblock: "/**@\*/". Cause: "Unrecognized token "\" at line 1 and column 5:
/**@\*/
    ↑"
EOF
        ];

        yield [
            '/**@a(!)*/',
            <<<'EOF'
Could not parse the following docblock: "/**@a(!)*/". Cause: "Unrecognized token "!" at line 1 and column 7:
/**@a(!)*/
      ↑"
EOF
        ];

        yield [
            '/**@a({x)*/',
            <<<'EOF'
Could not parse the following docblock: "/**@a({x)*/". Cause: "Unexpected token ")" (_parenthesis) at line 1 and column 9:
/**@a({x)*/
        ↑"
EOF
        ];

        yield [
            '/**@a({@:1})*/',
            <<<'EOF'
Could not parse the following docblock: "/**@a({@:1})*/". Cause: "Unrecognized token ":" at line 1 and column 9:
/**@a({@:1})*/
        ↑"
EOF
        ];

        yield [
            <<<'DOCBLOCK'
/**
 * @!Skipped
 */
DOCBLOCK
            ,
            <<<'EOF'
Could not parse the following docblock: "/**
 * @!Skipped
 */". Cause: "Unrecognized token "!" at line 1 and column 9:
/**
 * @!Skipped
 */
        ↑"
EOF
        ];
    }
}
