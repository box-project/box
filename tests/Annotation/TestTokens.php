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

use Doctrine\Common\Annotations\DocLexer;
use PHPUnit\Framework\TestCase;
use ReflectionObject;

/**
 * Includes a data provider for testing against a battery of tokens.
 *
 * @covers \KevinGH\Box\Annotation\Tokens
 */
class TestTokens extends TestCase
{
    /**
     * The battery of test tokens.
     *
     * @return array the array of array of tokens
     */
    public function getTokens(): array
    {
        $tokens = [];

        /*
         * @Annotation
         */
        $tokens[] = [
            [DocLexer::T_AT],
            [DocLexer::T_IDENTIFIER, 'Annotation'],
        ];

        /*
         * @Annotation()
         */
        $tokens[] = [
            [DocLexer::T_AT],
            [DocLexer::T_IDENTIFIER, 'Annotation'],
            [DocLexer::T_OPEN_PARENTHESIS],
            [DocLexer::T_CLOSE_PARENTHESIS],
        ];

        /*
         * @Annotation ()
         */
        $tokens[] = [
            [DocLexer::T_AT],
            [DocLexer::T_IDENTIFIER, 'Annotation'],
            [DocLexer::T_OPEN_PARENTHESIS],
            [DocLexer::T_CLOSE_PARENTHESIS],
        ];

        /*
         * @A
         * @B
         */
        $tokens[] = [
            [DocLexer::T_AT],
            [DocLexer::T_IDENTIFIER, 'A'],
            [DocLexer::T_AT],
            [DocLexer::T_IDENTIFIER, 'B'],
        ];

        /*
         * @A()
         * @B()
         */
        $tokens[] = [
            [DocLexer::T_AT],
            [DocLexer::T_IDENTIFIER, 'A'],
            [DocLexer::T_OPEN_PARENTHESIS],
            [DocLexer::T_CLOSE_PARENTHESIS],
            [DocLexer::T_AT],
            [DocLexer::T_IDENTIFIER, 'B'],
            [DocLexer::T_OPEN_PARENTHESIS],
            [DocLexer::T_CLOSE_PARENTHESIS],
        ];

        /*
         * @Namespaced\Annotation
         */
        $tokens[] = [
            [DocLexer::T_AT],
            [DocLexer::T_IDENTIFIER, 'Namespaced\\Annotation'],
        ];

        /*
         * @Namespaced\ Annotation
         */
        $tokens[] = [
            [DocLexer::T_AT],
            [DocLexer::T_IDENTIFIER, 'Namespaced\\Annotation'],
        ];

        /*
         * @Namespaced\Annotation()
         */
        $tokens[] = [
            [DocLexer::T_AT],
            [DocLexer::T_IDENTIFIER, 'Namespaced\\Annotation'],
            [DocLexer::T_OPEN_PARENTHESIS],
            [DocLexer::T_CLOSE_PARENTHESIS],
        ];

        /*
         * @Annotation("string")
         */
        $tokens[] = [
            [DocLexer::T_AT],
            [DocLexer::T_IDENTIFIER, 'Annotation'],
            [DocLexer::T_OPEN_PARENTHESIS],
            [DocLexer::T_STRING, 'string'],
            [DocLexer::T_CLOSE_PARENTHESIS],
        ];

        /*
         * @Annotation(
         *     "string"
         * )
         */
        $tokens[] = [
            [DocLexer::T_AT],
            [DocLexer::T_IDENTIFIER, 'Annotation'],
            [DocLexer::T_OPEN_PARENTHESIS],
            [DocLexer::T_STRING, 'string'],
            [DocLexer::T_CLOSE_PARENTHESIS],
        ];

        /*
         * @Annotation(123, "string", 1.23, CONSTANT, false, true, null)
         */
        $tokens[] = [
            [DocLexer::T_AT],
            [DocLexer::T_IDENTIFIER, 'Annotation'],
            [DocLexer::T_OPEN_PARENTHESIS],
            [DocLexer::T_INTEGER, '123'],
            [DocLexer::T_COMMA],
            [DocLexer::T_STRING, 'string'],
            [DocLexer::T_COMMA],
            [DocLexer::T_FLOAT, '1.23'],
            [DocLexer::T_COMMA],
            [DocLexer::T_IDENTIFIER, 'CONSTANT'],
            [DocLexer::T_COMMA],
            [DocLexer::T_FALSE, 'false'],
            [DocLexer::T_COMMA],
            [DocLexer::T_TRUE, 'true'],
            [DocLexer::T_COMMA],
            [DocLexer::T_NULL, 'null'],
            [DocLexer::T_CLOSE_PARENTHESIS],
        ];

        /*
         * @Annotation(constant, FALSE, TRUE, NULL)
         */
        $tokens[] = [
            [DocLexer::T_AT],
            [DocLexer::T_IDENTIFIER, 'Annotation'],
            [DocLexer::T_OPEN_PARENTHESIS],
            [DocLexer::T_IDENTIFIER, 'constant'],
            [DocLexer::T_COMMA],
            [DocLexer::T_FALSE, 'FALSE'],
            [DocLexer::T_COMMA],
            [DocLexer::T_TRUE, 'TRUE'],
            [DocLexer::T_COMMA],
            [DocLexer::T_NULL, 'NULL'],
            [DocLexer::T_CLOSE_PARENTHESIS],
        ];

        /*
         * @Annotation(key="value")
         */
        $tokens[] = [
            [DocLexer::T_AT],
            [DocLexer::T_IDENTIFIER, 'Annotation'],
            [DocLexer::T_OPEN_PARENTHESIS],
            [DocLexer::T_IDENTIFIER, 'key'],
            [DocLexer::T_EQUALS],
            [DocLexer::T_STRING, 'value'],
            [DocLexer::T_CLOSE_PARENTHESIS],
        ];

        /*
         * @Annotation(a="b", c="d")
         */
        $tokens[] = [
            [DocLexer::T_AT],
            [DocLexer::T_IDENTIFIER, 'Annotation'],
            [DocLexer::T_OPEN_PARENTHESIS],
            [DocLexer::T_IDENTIFIER, 'a'],
            [DocLexer::T_EQUALS],
            [DocLexer::T_STRING, 'b'],
            [DocLexer::T_COMMA],
            [DocLexer::T_IDENTIFIER, 'c'],
            [DocLexer::T_EQUALS],
            [DocLexer::T_STRING, 'd'],
            [DocLexer::T_CLOSE_PARENTHESIS],
        ];

        /*
         * @Annotation(
         *     a=123,
         *     b="string",
         *     c=1.23,
         *     d=CONSTANT,
         *     e=false,
         *     f=true,
         *     g=null
         * )
         */
        $tokens[] = [
            [DocLexer::T_AT],
            [DocLexer::T_IDENTIFIER, 'Annotation'],
            [DocLexer::T_OPEN_PARENTHESIS],
            [DocLexer::T_IDENTIFIER, 'a'],
            [DocLexer::T_EQUALS],
            [DocLexer::T_INTEGER, '123'],
            [DocLexer::T_COMMA],
            [DocLexer::T_IDENTIFIER, 'b'],
            [DocLexer::T_EQUALS],
            [DocLexer::T_STRING, 'string'],
            [DocLexer::T_COMMA],
            [DocLexer::T_IDENTIFIER, 'c'],
            [DocLexer::T_EQUALS],
            [DocLexer::T_FLOAT, '1.23'],
            [DocLexer::T_COMMA],
            [DocLexer::T_IDENTIFIER, 'd'],
            [DocLexer::T_EQUALS],
            [DocLexer::T_IDENTIFIER, 'CONSTANT'],
            [DocLexer::T_COMMA],
            [DocLexer::T_IDENTIFIER, 'e'],
            [DocLexer::T_EQUALS],
            [DocLexer::T_FALSE, 'false'],
            [DocLexer::T_COMMA],
            [DocLexer::T_IDENTIFIER, 'f'],
            [DocLexer::T_EQUALS],
            [DocLexer::T_TRUE, 'true'],
            [DocLexer::T_COMMA],
            [DocLexer::T_IDENTIFIER, 'g'],
            [DocLexer::T_EQUALS],
            [DocLexer::T_NULL, 'null'],
            [DocLexer::T_CLOSE_PARENTHESIS],
        ];

        /*
         * @Annotation({})
         */
        $tokens[] = [
            [DocLexer::T_AT],
            [DocLexer::T_IDENTIFIER, 'Annotation'],
            [DocLexer::T_OPEN_PARENTHESIS],
            [DocLexer::T_OPEN_CURLY_BRACES],
            [DocLexer::T_CLOSE_CURLY_BRACES],
            [DocLexer::T_CLOSE_PARENTHESIS],
        ];

        /*
         * @Annotation(key={})
         */
        $tokens[] = [
            [DocLexer::T_AT],
            [DocLexer::T_IDENTIFIER, 'Annotation'],
            [DocLexer::T_OPEN_PARENTHESIS],
            [DocLexer::T_IDENTIFIER, 'key'],
            [DocLexer::T_EQUALS],
            [DocLexer::T_OPEN_CURLY_BRACES],
            [DocLexer::T_CLOSE_CURLY_BRACES],
            [DocLexer::T_CLOSE_PARENTHESIS],
        ];

        /*
         * @Annotation({"string"})
         */
        $tokens[] = [
            [DocLexer::T_AT],
            [DocLexer::T_IDENTIFIER, 'Annotation'],
            [DocLexer::T_OPEN_PARENTHESIS],
            [DocLexer::T_OPEN_CURLY_BRACES],
            [DocLexer::T_STRING, 'string'],
            [DocLexer::T_CLOSE_CURLY_BRACES],
            [DocLexer::T_CLOSE_PARENTHESIS],
        ];

        /*
         * @Annotation(
         *     {
         *         "string"
         *     }
         * )
         */
        $tokens[] = [
            [DocLexer::T_AT],
            [DocLexer::T_IDENTIFIER, 'Annotation'],
            [DocLexer::T_OPEN_PARENTHESIS],
            [DocLexer::T_OPEN_CURLY_BRACES],
            [DocLexer::T_STRING, 'string'],
            [DocLexer::T_CLOSE_CURLY_BRACES],
            [DocLexer::T_CLOSE_PARENTHESIS],
        ];

        /*
         * @Annotation({123, "string", 1.23, CONSTANT, false, true, null})
         */
        $tokens[] = [
            [DocLexer::T_AT],
            [DocLexer::T_IDENTIFIER, 'Annotation'],
            [DocLexer::T_OPEN_PARENTHESIS],
            [DocLexer::T_OPEN_CURLY_BRACES],
            [DocLexer::T_INTEGER, '123'],
            [DocLexer::T_COMMA],
            [DocLexer::T_STRING, 'string'],
            [DocLexer::T_COMMA],
            [DocLexer::T_FLOAT, '1.23'],
            [DocLexer::T_COMMA],
            [DocLexer::T_IDENTIFIER, 'CONSTANT'],
            [DocLexer::T_COMMA],
            [DocLexer::T_FALSE, 'false'],
            [DocLexer::T_COMMA],
            [DocLexer::T_TRUE, 'true'],
            [DocLexer::T_COMMA],
            [DocLexer::T_NULL, 'null'],
            [DocLexer::T_CLOSE_CURLY_BRACES],
            [DocLexer::T_CLOSE_PARENTHESIS],
        ];

        /*
         * @Annotation({key="value"})
         */
        $tokens[] = [
            [DocLexer::T_AT],
            [DocLexer::T_IDENTIFIER, 'Annotation'],
            [DocLexer::T_OPEN_PARENTHESIS],
            [DocLexer::T_OPEN_CURLY_BRACES],
            [DocLexer::T_IDENTIFIER, 'key'],
            [DocLexer::T_EQUALS],
            [DocLexer::T_STRING, 'value'],
            [DocLexer::T_CLOSE_CURLY_BRACES],
            [DocLexer::T_CLOSE_PARENTHESIS],
        ];

        /*
         * @Annotation({"key"="value"})
         */
        $tokens[] = [
            [DocLexer::T_AT],
            [DocLexer::T_IDENTIFIER, 'Annotation'],
            [DocLexer::T_OPEN_PARENTHESIS],
            [DocLexer::T_OPEN_CURLY_BRACES],
            [DocLexer::T_STRING, 'key'],
            [DocLexer::T_EQUALS],
            [DocLexer::T_STRING, 'value'],
            [DocLexer::T_CLOSE_CURLY_BRACES],
            [DocLexer::T_CLOSE_PARENTHESIS],
        ];

        /*
         * @Annotation({a="b", c="d"})
         */
        $tokens[] = [
            [DocLexer::T_AT],
            [DocLexer::T_IDENTIFIER, 'Annotation'],
            [DocLexer::T_OPEN_PARENTHESIS],
            [DocLexer::T_OPEN_CURLY_BRACES],
            [DocLexer::T_IDENTIFIER, 'a'],
            [DocLexer::T_EQUALS],
            [DocLexer::T_STRING, 'b'],
            [DocLexer::T_COMMA],
            [DocLexer::T_IDENTIFIER, 'c'],
            [DocLexer::T_EQUALS],
            [DocLexer::T_STRING, 'd'],
            [DocLexer::T_CLOSE_CURLY_BRACES],
            [DocLexer::T_CLOSE_PARENTHESIS],
        ];

        /*
         * @Annotation({a="b", "c"="d", 123="e"})
         */
        $tokens[] = [
            [DocLexer::T_AT],
            [DocLexer::T_IDENTIFIER, 'Annotation'],
            [DocLexer::T_OPEN_PARENTHESIS],
            [DocLexer::T_OPEN_CURLY_BRACES],
            [DocLexer::T_IDENTIFIER, 'a'],
            [DocLexer::T_EQUALS],
            [DocLexer::T_STRING, 'b'],
            [DocLexer::T_COMMA],
            [DocLexer::T_STRING, 'c'],
            [DocLexer::T_EQUALS],
            [DocLexer::T_STRING, 'd'],
            [DocLexer::T_COMMA],
            [DocLexer::T_INTEGER, '123'],
            [DocLexer::T_EQUALS],
            [DocLexer::T_STRING, 'e'],
            [DocLexer::T_CLOSE_CURLY_BRACES],
            [DocLexer::T_CLOSE_PARENTHESIS],
        ];

        /*
         * @Annotation({key={}})
         */
        $tokens[] = [
            [DocLexer::T_AT],
            [DocLexer::T_IDENTIFIER, 'Annotation'],
            [DocLexer::T_OPEN_PARENTHESIS],
            [DocLexer::T_OPEN_CURLY_BRACES],
            [DocLexer::T_IDENTIFIER, 'key'],
            [DocLexer::T_EQUALS],
            [DocLexer::T_OPEN_CURLY_BRACES],
            [DocLexer::T_CLOSE_CURLY_BRACES],
            [DocLexer::T_CLOSE_CURLY_BRACES],
            [DocLexer::T_CLOSE_PARENTHESIS],
        ];

        /*
         * @Annotation(a={b={}})
         */
        $tokens[] = [
            [DocLexer::T_AT],
            [DocLexer::T_IDENTIFIER, 'Annotation'],
            [DocLexer::T_OPEN_PARENTHESIS],
            [DocLexer::T_IDENTIFIER, 'a'],
            [DocLexer::T_EQUALS],
            [DocLexer::T_OPEN_CURLY_BRACES],
            [DocLexer::T_IDENTIFIER, 'b'],
            [DocLexer::T_EQUALS],
            [DocLexer::T_OPEN_CURLY_BRACES],
            [DocLexer::T_CLOSE_CURLY_BRACES],
            [DocLexer::T_CLOSE_CURLY_BRACES],
            [DocLexer::T_CLOSE_PARENTHESIS],
        ];

        /*
         * @Annotation({key: {}})
         */
        $tokens[] = [
            [DocLexer::T_AT],
            [DocLexer::T_IDENTIFIER, 'Annotation'],
            [DocLexer::T_OPEN_PARENTHESIS],
            [DocLexer::T_OPEN_CURLY_BRACES],
            [DocLexer::T_IDENTIFIER, 'key'],
            [DocLexer::T_COLON],
            [DocLexer::T_OPEN_CURLY_BRACES],
            [DocLexer::T_CLOSE_CURLY_BRACES],
            [DocLexer::T_CLOSE_CURLY_BRACES],
            [DocLexer::T_CLOSE_PARENTHESIS],
        ];

        /*
         * @Annotation(a={b: {}})
         */
        $tokens[] = [
            [DocLexer::T_AT],
            [DocLexer::T_IDENTIFIER, 'Annotation'],
            [DocLexer::T_OPEN_PARENTHESIS],
            [DocLexer::T_IDENTIFIER, 'a'],
            [DocLexer::T_EQUALS],
            [DocLexer::T_OPEN_CURLY_BRACES],
            [DocLexer::T_IDENTIFIER, 'b'],
            [DocLexer::T_COLON],
            [DocLexer::T_OPEN_CURLY_BRACES],
            [DocLexer::T_CLOSE_CURLY_BRACES],
            [DocLexer::T_CLOSE_CURLY_BRACES],
            [DocLexer::T_CLOSE_PARENTHESIS],
        ];

        /*
         * @Annotation({key: "value"})
         */
        $tokens[] = [
            [DocLexer::T_AT],
            [DocLexer::T_IDENTIFIER, 'Annotation'],
            [DocLexer::T_OPEN_PARENTHESIS],
            [DocLexer::T_OPEN_CURLY_BRACES],
            [DocLexer::T_IDENTIFIER, 'key'],
            [DocLexer::T_COLON],
            [DocLexer::T_STRING, 'value'],
            [DocLexer::T_CLOSE_CURLY_BRACES],
            [DocLexer::T_CLOSE_PARENTHESIS],
        ];

        /*
         * @Annotation({"key": "value"})
         */
        $tokens[] = [
            [DocLexer::T_AT],
            [DocLexer::T_IDENTIFIER, 'Annotation'],
            [DocLexer::T_OPEN_PARENTHESIS],
            [DocLexer::T_OPEN_CURLY_BRACES],
            [DocLexer::T_STRING, 'key'],
            [DocLexer::T_COLON],
            [DocLexer::T_STRING, 'value'],
            [DocLexer::T_CLOSE_CURLY_BRACES],
            [DocLexer::T_CLOSE_PARENTHESIS],
        ];

        /*
         * @Annotation({a: "b", c: "d"})
         */
        $tokens[] = [
            [DocLexer::T_AT],
            [DocLexer::T_IDENTIFIER, 'Annotation'],
            [DocLexer::T_OPEN_PARENTHESIS],
            [DocLexer::T_OPEN_CURLY_BRACES],
            [DocLexer::T_IDENTIFIER, 'a'],
            [DocLexer::T_COLON],
            [DocLexer::T_STRING, 'b'],
            [DocLexer::T_COMMA],
            [DocLexer::T_IDENTIFIER, 'c'],
            [DocLexer::T_COLON],
            [DocLexer::T_STRING, 'd'],
            [DocLexer::T_CLOSE_CURLY_BRACES],
            [DocLexer::T_CLOSE_PARENTHESIS],
        ];

        /*
         * @Annotation({a: "b", "c": "d", 123: "e"})
         */
        $tokens[] = [
            [DocLexer::T_AT],
            [DocLexer::T_IDENTIFIER, 'Annotation'],
            [DocLexer::T_OPEN_PARENTHESIS],
            [DocLexer::T_OPEN_CURLY_BRACES],
            [DocLexer::T_IDENTIFIER, 'a'],
            [DocLexer::T_COLON],
            [DocLexer::T_STRING, 'b'],
            [DocLexer::T_COMMA],
            [DocLexer::T_STRING, 'c'],
            [DocLexer::T_COLON],
            [DocLexer::T_STRING, 'd'],
            [DocLexer::T_COMMA],
            [DocLexer::T_INTEGER, '123'],
            [DocLexer::T_COLON],
            [DocLexer::T_STRING, 'e'],
            [DocLexer::T_CLOSE_CURLY_BRACES],
            [DocLexer::T_CLOSE_PARENTHESIS],
        ];

        /*
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
        $tokens[] = [
            [DocLexer::T_AT],
            [DocLexer::T_IDENTIFIER, 'Annotation'],
            [DocLexer::T_OPEN_PARENTHESIS],
            [DocLexer::T_OPEN_CURLY_BRACES],
            [DocLexer::T_STRING, 'a'],
            [DocLexer::T_COMMA],
            [DocLexer::T_OPEN_CURLY_BRACES],
            [DocLexer::T_OPEN_CURLY_BRACES],
            [DocLexer::T_STRING, 'c'],
            [DocLexer::T_CLOSE_CURLY_BRACES],
            [DocLexer::T_COMMA],
            [DocLexer::T_STRING, 'b'],
            [DocLexer::T_CLOSE_CURLY_BRACES],
            [DocLexer::T_CLOSE_CURLY_BRACES],
            [DocLexer::T_CLOSE_PARENTHESIS],
        ];

        /*
         * @Annotation(@Nested)
         */
        $tokens[] = [
            [DocLexer::T_AT],
            [DocLexer::T_IDENTIFIER, 'Annotation'],
            [DocLexer::T_OPEN_PARENTHESIS],
            [DocLexer::T_AT],
            [DocLexer::T_IDENTIFIER, 'Nested'],
            [DocLexer::T_CLOSE_PARENTHESIS],
        ];

        /*
         * @Annotation(@Nested())
         */
        $tokens[] = [
            [DocLexer::T_AT],
            [DocLexer::T_IDENTIFIER, 'Annotation'],
            [DocLexer::T_OPEN_PARENTHESIS],
            [DocLexer::T_AT],
            [DocLexer::T_IDENTIFIER, 'Nested'],
            [DocLexer::T_OPEN_PARENTHESIS],
            [DocLexer::T_CLOSE_PARENTHESIS],
            [DocLexer::T_CLOSE_PARENTHESIS],
        ];

        /*
         * @Annotation(@Nested, @Nested)
         */
        $tokens[] = [
            [DocLexer::T_AT],
            [DocLexer::T_IDENTIFIER, 'Annotation'],
            [DocLexer::T_OPEN_PARENTHESIS],
            [DocLexer::T_AT],
            [DocLexer::T_IDENTIFIER, 'Nested'],
            [DocLexer::T_COMMA],
            [DocLexer::T_AT],
            [DocLexer::T_IDENTIFIER, 'Nested'],
            [DocLexer::T_CLOSE_PARENTHESIS],
        ];

        /*
         * @Annotation(@Nested(), @Nested())
         */
        $tokens[] = [
            [DocLexer::T_AT],
            [DocLexer::T_IDENTIFIER, 'Annotation'],
            [DocLexer::T_OPEN_PARENTHESIS],
            [DocLexer::T_AT],
            [DocLexer::T_IDENTIFIER, 'Nested'],
            [DocLexer::T_OPEN_PARENTHESIS],
            [DocLexer::T_CLOSE_PARENTHESIS],
            [DocLexer::T_COMMA],
            [DocLexer::T_AT],
            [DocLexer::T_IDENTIFIER, 'Nested'],
            [DocLexer::T_OPEN_PARENTHESIS],
            [DocLexer::T_CLOSE_PARENTHESIS],
            [DocLexer::T_CLOSE_PARENTHESIS],
        ];

        /*
         * @Annotation(
         *     @Nested(),
         *     @Nested()
         * )
         */
        $tokens[] = [
            [DocLexer::T_AT],
            [DocLexer::T_IDENTIFIER, 'Annotation'],
            [DocLexer::T_OPEN_PARENTHESIS],
            [DocLexer::T_AT],
            [DocLexer::T_IDENTIFIER, 'Nested'],
            [DocLexer::T_OPEN_PARENTHESIS],
            [DocLexer::T_CLOSE_PARENTHESIS],
            [DocLexer::T_COMMA],
            [DocLexer::T_AT],
            [DocLexer::T_IDENTIFIER, 'Nested'],
            [DocLexer::T_OPEN_PARENTHESIS],
            [DocLexer::T_CLOSE_PARENTHESIS],
            [DocLexer::T_CLOSE_PARENTHESIS],
        ];

        /*
         * @Annotation(key=@Nested)
         */
        $tokens[] = [
            [DocLexer::T_AT],
            [DocLexer::T_IDENTIFIER, 'Annotation'],
            [DocLexer::T_OPEN_PARENTHESIS],
            [DocLexer::T_IDENTIFIER, 'key'],
            [DocLexer::T_EQUALS],
            [DocLexer::T_AT],
            [DocLexer::T_IDENTIFIER, 'Nested'],
            [DocLexer::T_CLOSE_PARENTHESIS],
        ];

        /*
         * @Annotation(a=@Nested(),b=@Nested)
         */
        $tokens[] = [
            [DocLexer::T_AT],
            [DocLexer::T_IDENTIFIER, 'Annotation'],
            [DocLexer::T_OPEN_PARENTHESIS],
            [DocLexer::T_IDENTIFIER, 'a'],
            [DocLexer::T_EQUALS],
            [DocLexer::T_AT],
            [DocLexer::T_IDENTIFIER, 'Nested'],
            [DocLexer::T_OPEN_PARENTHESIS],
            [DocLexer::T_CLOSE_PARENTHESIS],
            [DocLexer::T_COMMA],
            [DocLexer::T_IDENTIFIER, 'b'],
            [DocLexer::T_EQUALS],
            [DocLexer::T_AT],
            [DocLexer::T_IDENTIFIER, 'Nested'],
            [DocLexer::T_CLOSE_PARENTHESIS],
        ];

        /*
         * @Annotation({key=@Nested})
         */
        $tokens[] = [
            [DocLexer::T_AT],
            [DocLexer::T_IDENTIFIER, 'Annotation'],
            [DocLexer::T_OPEN_PARENTHESIS],
            [DocLexer::T_OPEN_CURLY_BRACES],
            [DocLexer::T_IDENTIFIER, 'key'],
            [DocLexer::T_EQUALS],
            [DocLexer::T_AT],
            [DocLexer::T_IDENTIFIER, 'Nested'],
            [DocLexer::T_CLOSE_CURLY_BRACES],
            [DocLexer::T_CLOSE_PARENTHESIS],
        ];

        /*
         * @Annotation({a=@Nested(),b=@Nested})
         */
        $tokens[] = [
            [DocLexer::T_AT],
            [DocLexer::T_IDENTIFIER, 'Annotation'],
            [DocLexer::T_OPEN_PARENTHESIS],
            [DocLexer::T_OPEN_CURLY_BRACES],
            [DocLexer::T_IDENTIFIER, 'a'],
            [DocLexer::T_EQUALS],
            [DocLexer::T_AT],
            [DocLexer::T_IDENTIFIER, 'Nested'],
            [DocLexer::T_OPEN_PARENTHESIS],
            [DocLexer::T_CLOSE_PARENTHESIS],
            [DocLexer::T_COMMA],
            [DocLexer::T_IDENTIFIER, 'b'],
            [DocLexer::T_EQUALS],
            [DocLexer::T_AT],
            [DocLexer::T_IDENTIFIER, 'Nested'],
            [DocLexer::T_CLOSE_CURLY_BRACES],
            [DocLexer::T_CLOSE_PARENTHESIS],
        ];

        /*
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
        $tokens[] = [
            [DocLexer::T_AT],
            [DocLexer::T_IDENTIFIER, 'Annotation'],
            [DocLexer::T_OPEN_PARENTHESIS],
            [DocLexer::T_AT],
            [DocLexer::T_IDENTIFIER, 'Nested'],
            [DocLexer::T_OPEN_PARENTHESIS],
            [DocLexer::T_OPEN_CURLY_BRACES],
            [DocLexer::T_STRING, 'a'],
            [DocLexer::T_COMMA],
            [DocLexer::T_OPEN_CURLY_BRACES],
            [DocLexer::T_OPEN_CURLY_BRACES],
            [DocLexer::T_STRING, 'c'],
            [DocLexer::T_CLOSE_CURLY_BRACES],
            [DocLexer::T_COMMA],
            [DocLexer::T_STRING, 'b'],
            [DocLexer::T_CLOSE_CURLY_BRACES],
            [DocLexer::T_CLOSE_CURLY_BRACES],
            [DocLexer::T_CLOSE_PARENTHESIS],
            [DocLexer::T_COMMA],
            [DocLexer::T_AT],
            [DocLexer::T_IDENTIFIER, 'Nested'],
            [DocLexer::T_OPEN_PARENTHESIS],
            [DocLexer::T_OPEN_CURLY_BRACES],
            [DocLexer::T_STRING, 'd'],
            [DocLexer::T_COMMA],
            [DocLexer::T_OPEN_CURLY_BRACES],
            [DocLexer::T_OPEN_CURLY_BRACES],
            [DocLexer::T_STRING, 'f'],
            [DocLexer::T_COMMA],
            [DocLexer::T_CLOSE_CURLY_BRACES],
            [DocLexer::T_COMMA],
            [DocLexer::T_STRING, 'e'],
            [DocLexer::T_CLOSE_CURLY_BRACES],
            [DocLexer::T_CLOSE_CURLY_BRACES],
            [DocLexer::T_CLOSE_PARENTHESIS],
            [DocLexer::T_CLOSE_PARENTHESIS],
        ];

        return $tokens;
    }

    /**
     * @param mixed $object
     *
     * @return mixed
     */
    final protected function getPropertyValue($object, string $property)
    {
        $reflectionObject = new ReflectionObject($object);

        while (false === $reflectionObject->hasProperty($property) && false !== $reflectionObject->getParentClass()) {
            $reflectionObject = $reflectionObject->getParentClass();
        }

        $propertyReflection = $reflectionObject->getProperty($property);
        $propertyReflection->setAccessible(true);

        return $propertyReflection->getValue($object);
    }

    final protected function setPropertyValue($object, string $property, $value): void
    {
        $reflectionObject = new ReflectionObject($object);

        while (false === $reflectionObject->hasProperty($property) && false !== $reflectionObject->getParentClass()) {
            $reflectionObject = $reflectionObject->getParentClass();
        }

        $propertyReflection = $reflectionObject->getProperty($property);
        $propertyReflection->setAccessible(true);

        $propertyReflection->setValue($object, $value);
    }
}
