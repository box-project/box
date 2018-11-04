<?php

namespace KevinGH\Box\Annotation;

use Doctrine\Common\Annotations\DocLexer;
use PHPUnit\Framework\TestCase;
use ReflectionObject;

/**
 * Includes a data provider for testing against a battery of tokens.
 *
 * @author Kevin Herrera <kevin@herrera.io>
 */
class TestTokens extends TestCase
{
    /**
     * The battery of test tokens.
     *
     * @return array The array of array of tokens.
     */
    public function getTokens()
    {
        $tokens = array();

        /**
         * @Annotation
         */
        $tokens[] = array(
            array(DocLexer::T_AT),
            array(DocLexer::T_IDENTIFIER, 'Annotation'),
        );

        /**
         * @Annotation()
         */
        $tokens[] = array(
            array(DocLexer::T_AT),
            array(DocLexer::T_IDENTIFIER, 'Annotation'),
            array(DocLexer::T_OPEN_PARENTHESIS),
            array(DocLexer::T_CLOSE_PARENTHESIS),
        );

        /**
         * @Annotation ()
         */
        $tokens[] = array(
            array(DocLexer::T_AT),
            array(DocLexer::T_IDENTIFIER, 'Annotation'),
            array(DocLexer::T_OPEN_PARENTHESIS),
            array(DocLexer::T_CLOSE_PARENTHESIS),
        );

        /**
         * @A
         * @B
         */
        $tokens[] = array(
            array(DocLexer::T_AT),
            array(DocLexer::T_IDENTIFIER, 'A'),
            array(DocLexer::T_AT),
            array(DocLexer::T_IDENTIFIER, 'B'),
        );

        /**
         * @A()
         * @B()
         */
        $tokens[] = array(
            array(DocLexer::T_AT),
            array(DocLexer::T_IDENTIFIER, 'A'),
            array(DocLexer::T_OPEN_PARENTHESIS),
            array(DocLexer::T_CLOSE_PARENTHESIS),
            array(DocLexer::T_AT),
            array(DocLexer::T_IDENTIFIER, 'B'),
            array(DocLexer::T_OPEN_PARENTHESIS),
            array(DocLexer::T_CLOSE_PARENTHESIS),
        );

        /**
         * @Namespaced\Annotation
         */
        $tokens[] = array(
            array(DocLexer::T_AT),
            array(DocLexer::T_IDENTIFIER, 'Namespaced\\Annotation'),
        );

        /**
         * @Namespaced\ Annotation
         */
        $tokens[] = array(
            array(DocLexer::T_AT),
            array(DocLexer::T_IDENTIFIER, 'Namespaced\\Annotation'),
        );

        /**
         * @Namespaced\Annotation()
         */
        $tokens[] = array(
            array(DocLexer::T_AT),
            array(DocLexer::T_IDENTIFIER, 'Namespaced\\Annotation'),
            array(DocLexer::T_OPEN_PARENTHESIS),
            array(DocLexer::T_CLOSE_PARENTHESIS),
        );

        /**
         * @Annotation("string")
         */
        $tokens[] = array(
            array(DocLexer::T_AT),
            array(DocLexer::T_IDENTIFIER, 'Annotation'),
            array(DocLexer::T_OPEN_PARENTHESIS),
            array(DocLexer::T_STRING, 'string'),
            array(DocLexer::T_CLOSE_PARENTHESIS),
        );

        /**
         * @Annotation(
         *     "string"
         * )
         */
        $tokens[] = array(
            array(DocLexer::T_AT),
            array(DocLexer::T_IDENTIFIER, 'Annotation'),
            array(DocLexer::T_OPEN_PARENTHESIS),
            array(DocLexer::T_STRING, 'string'),
            array(DocLexer::T_CLOSE_PARENTHESIS),
        );

        /**
         * @Annotation(123, "string", 1.23, CONSTANT, false, true, null)
         */
        $tokens[] = array(
            array(DocLexer::T_AT),
            array(DocLexer::T_IDENTIFIER, 'Annotation'),
            array(DocLexer::T_OPEN_PARENTHESIS),
            array(DocLexer::T_INTEGER, '123'),
            array(DocLexer::T_COMMA),
            array(DocLexer::T_STRING, 'string'),
            array(DocLexer::T_COMMA),
            array(DocLexer::T_FLOAT, '1.23'),
            array(DocLexer::T_COMMA),
            array(DocLexer::T_IDENTIFIER, 'CONSTANT'),
            array(DocLexer::T_COMMA),
            array(DocLexer::T_FALSE, 'false'),
            array(DocLexer::T_COMMA),
            array(DocLexer::T_TRUE, 'true'),
            array(DocLexer::T_COMMA),
            array(DocLexer::T_NULL, 'null'),
            array(DocLexer::T_CLOSE_PARENTHESIS),
        );

        /**
         * @Annotation(constant, FALSE, TRUE, NULL)
         */
        $tokens[] = array(
            array(DocLexer::T_AT),
            array(DocLexer::T_IDENTIFIER, 'Annotation'),
            array(DocLexer::T_OPEN_PARENTHESIS),
            array(DocLexer::T_IDENTIFIER, 'constant'),
            array(DocLexer::T_COMMA),
            array(DocLexer::T_FALSE, 'FALSE'),
            array(DocLexer::T_COMMA),
            array(DocLexer::T_TRUE, 'TRUE'),
            array(DocLexer::T_COMMA),
            array(DocLexer::T_NULL, 'NULL'),
            array(DocLexer::T_CLOSE_PARENTHESIS),
        );

        /**
         * @Annotation(key="value")
         */
        $tokens[] = array(
            array(DocLexer::T_AT),
            array(DocLexer::T_IDENTIFIER, 'Annotation'),
            array(DocLexer::T_OPEN_PARENTHESIS),
            array(DocLexer::T_IDENTIFIER, 'key'),
            array(DocLexer::T_EQUALS),
            array(DocLexer::T_STRING, 'value'),
            array(DocLexer::T_CLOSE_PARENTHESIS),
        );

        /**
         * @Annotation(a="b", c="d")
         */
        $tokens[] = array(
            array(DocLexer::T_AT),
            array(DocLexer::T_IDENTIFIER, 'Annotation'),
            array(DocLexer::T_OPEN_PARENTHESIS),
            array(DocLexer::T_IDENTIFIER, 'a'),
            array(DocLexer::T_EQUALS),
            array(DocLexer::T_STRING, 'b'),
            array(DocLexer::T_COMMA),
            array(DocLexer::T_IDENTIFIER, 'c'),
            array(DocLexer::T_EQUALS),
            array(DocLexer::T_STRING, 'd'),
            array(DocLexer::T_CLOSE_PARENTHESIS),
        );

        /**
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
        $tokens[] = array(
            array(DocLexer::T_AT),
            array(DocLexer::T_IDENTIFIER, 'Annotation'),
            array(DocLexer::T_OPEN_PARENTHESIS),
            array(DocLexer::T_IDENTIFIER, 'a'),
            array(DocLexer::T_EQUALS),
            array(DocLexer::T_INTEGER, '123'),
            array(DocLexer::T_COMMA),
            array(DocLexer::T_IDENTIFIER, 'b'),
            array(DocLexer::T_EQUALS),
            array(DocLexer::T_STRING, 'string'),
            array(DocLexer::T_COMMA),
            array(DocLexer::T_IDENTIFIER, 'c'),
            array(DocLexer::T_EQUALS),
            array(DocLexer::T_FLOAT, '1.23'),
            array(DocLexer::T_COMMA),
            array(DocLexer::T_IDENTIFIER, 'd'),
            array(DocLexer::T_EQUALS),
            array(DocLexer::T_IDENTIFIER, 'CONSTANT'),
            array(DocLexer::T_COMMA),
            array(DocLexer::T_IDENTIFIER, 'e'),
            array(DocLexer::T_EQUALS),
            array(DocLexer::T_FALSE, 'false'),
            array(DocLexer::T_COMMA),
            array(DocLexer::T_IDENTIFIER, 'f'),
            array(DocLexer::T_EQUALS),
            array(DocLexer::T_TRUE, 'true'),
            array(DocLexer::T_COMMA),
            array(DocLexer::T_IDENTIFIER, 'g'),
            array(DocLexer::T_EQUALS),
            array(DocLexer::T_NULL, 'null'),
            array(DocLexer::T_CLOSE_PARENTHESIS),
        );

        /**
         * @Annotation({})
         */
        $tokens[] = array(
            array(DocLexer::T_AT),
            array(DocLexer::T_IDENTIFIER, 'Annotation'),
            array(DocLexer::T_OPEN_PARENTHESIS),
            array(DocLexer::T_OPEN_CURLY_BRACES),
            array(DocLexer::T_CLOSE_CURLY_BRACES),
            array(DocLexer::T_CLOSE_PARENTHESIS),
        );

        /**
         * @Annotation(key={})
         */
        $tokens[] = array(
            array(DocLexer::T_AT),
            array(DocLexer::T_IDENTIFIER, 'Annotation'),
            array(DocLexer::T_OPEN_PARENTHESIS),
            array(DocLexer::T_IDENTIFIER, 'key'),
            array(DocLexer::T_EQUALS),
            array(DocLexer::T_OPEN_CURLY_BRACES),
            array(DocLexer::T_CLOSE_CURLY_BRACES),
            array(DocLexer::T_CLOSE_PARENTHESIS),
        );

        /**
         * @Annotation({"string"})
         */
        $tokens[] = array(
            array(DocLexer::T_AT),
            array(DocLexer::T_IDENTIFIER, 'Annotation'),
            array(DocLexer::T_OPEN_PARENTHESIS),
            array(DocLexer::T_OPEN_CURLY_BRACES),
            array(DocLexer::T_STRING, 'string'),
            array(DocLexer::T_CLOSE_CURLY_BRACES),
            array(DocLexer::T_CLOSE_PARENTHESIS),
        );

        /**
         * @Annotation(
         *     {
         *         "string"
         *     }
         * )
         */
        $tokens[] = array(
            array(DocLexer::T_AT),
            array(DocLexer::T_IDENTIFIER, 'Annotation'),
            array(DocLexer::T_OPEN_PARENTHESIS),
            array(DocLexer::T_OPEN_CURLY_BRACES),
            array(DocLexer::T_STRING, 'string'),
            array(DocLexer::T_CLOSE_CURLY_BRACES),
            array(DocLexer::T_CLOSE_PARENTHESIS),
        );

        /**
         * @Annotation({123, "string", 1.23, CONSTANT, false, true, null})
         */
        $tokens[] = array(
            array(DocLexer::T_AT),
            array(DocLexer::T_IDENTIFIER, 'Annotation'),
            array(DocLexer::T_OPEN_PARENTHESIS),
            array(DocLexer::T_OPEN_CURLY_BRACES),
            array(DocLexer::T_INTEGER, '123'),
            array(DocLexer::T_COMMA),
            array(DocLexer::T_STRING, 'string'),
            array(DocLexer::T_COMMA),
            array(DocLexer::T_FLOAT, '1.23'),
            array(DocLexer::T_COMMA),
            array(DocLexer::T_IDENTIFIER, 'CONSTANT'),
            array(DocLexer::T_COMMA),
            array(DocLexer::T_FALSE, 'false'),
            array(DocLexer::T_COMMA),
            array(DocLexer::T_TRUE, 'true'),
            array(DocLexer::T_COMMA),
            array(DocLexer::T_NULL, 'null'),
            array(DocLexer::T_CLOSE_CURLY_BRACES),
            array(DocLexer::T_CLOSE_PARENTHESIS),
        );

        /**
         * @Annotation({key="value"})
         */
        $tokens[] = array(
            array(DocLexer::T_AT),
            array(DocLexer::T_IDENTIFIER, 'Annotation'),
            array(DocLexer::T_OPEN_PARENTHESIS),
            array(DocLexer::T_OPEN_CURLY_BRACES),
            array(DocLexer::T_IDENTIFIER, 'key'),
            array(DocLexer::T_EQUALS),
            array(DocLexer::T_STRING, 'value'),
            array(DocLexer::T_CLOSE_CURLY_BRACES),
            array(DocLexer::T_CLOSE_PARENTHESIS),
        );

        /**
         * @Annotation({"key"="value"})
         */
        $tokens[] = array(
            array(DocLexer::T_AT),
            array(DocLexer::T_IDENTIFIER, 'Annotation'),
            array(DocLexer::T_OPEN_PARENTHESIS),
            array(DocLexer::T_OPEN_CURLY_BRACES),
            array(DocLexer::T_STRING, 'key'),
            array(DocLexer::T_EQUALS),
            array(DocLexer::T_STRING, 'value'),
            array(DocLexer::T_CLOSE_CURLY_BRACES),
            array(DocLexer::T_CLOSE_PARENTHESIS),
        );

        /**
         * @Annotation({a="b", c="d"})
         */
        $tokens[] = array(
            array(DocLexer::T_AT),
            array(DocLexer::T_IDENTIFIER, 'Annotation'),
            array(DocLexer::T_OPEN_PARENTHESIS),
            array(DocLexer::T_OPEN_CURLY_BRACES),
            array(DocLexer::T_IDENTIFIER, 'a'),
            array(DocLexer::T_EQUALS),
            array(DocLexer::T_STRING, 'b'),
            array(DocLexer::T_COMMA),
            array(DocLexer::T_IDENTIFIER, 'c'),
            array(DocLexer::T_EQUALS),
            array(DocLexer::T_STRING, 'd'),
            array(DocLexer::T_CLOSE_CURLY_BRACES),
            array(DocLexer::T_CLOSE_PARENTHESIS),
        );

        /**
         * @Annotation({a="b", "c"="d", 123="e"})
         */
        $tokens[] = array(
            array(DocLexer::T_AT),
            array(DocLexer::T_IDENTIFIER, 'Annotation'),
            array(DocLexer::T_OPEN_PARENTHESIS),
            array(DocLexer::T_OPEN_CURLY_BRACES),
            array(DocLexer::T_IDENTIFIER, 'a'),
            array(DocLexer::T_EQUALS),
            array(DocLexer::T_STRING, 'b'),
            array(DocLexer::T_COMMA),
            array(DocLexer::T_STRING, 'c'),
            array(DocLexer::T_EQUALS),
            array(DocLexer::T_STRING, 'd'),
            array(DocLexer::T_COMMA),
            array(DocLexer::T_INTEGER, '123'),
            array(DocLexer::T_EQUALS),
            array(DocLexer::T_STRING, 'e'),
            array(DocLexer::T_CLOSE_CURLY_BRACES),
            array(DocLexer::T_CLOSE_PARENTHESIS),
        );

        /**
         * @Annotation({key={}})
         */
        $tokens[] = array(
            array(DocLexer::T_AT),
            array(DocLexer::T_IDENTIFIER, 'Annotation'),
            array(DocLexer::T_OPEN_PARENTHESIS),
            array(DocLexer::T_OPEN_CURLY_BRACES),
            array(DocLexer::T_IDENTIFIER, 'key'),
            array(DocLexer::T_EQUALS),
            array(DocLexer::T_OPEN_CURLY_BRACES),
            array(DocLexer::T_CLOSE_CURLY_BRACES),
            array(DocLexer::T_CLOSE_CURLY_BRACES),
            array(DocLexer::T_CLOSE_PARENTHESIS),
        );

        /**
         * @Annotation(a={b={}})
         */
        $tokens[] = array(
            array(DocLexer::T_AT),
            array(DocLexer::T_IDENTIFIER, 'Annotation'),
            array(DocLexer::T_OPEN_PARENTHESIS),
            array(DocLexer::T_IDENTIFIER, 'a'),
            array(DocLexer::T_EQUALS),
            array(DocLexer::T_OPEN_CURLY_BRACES),
            array(DocLexer::T_IDENTIFIER, 'b'),
            array(DocLexer::T_EQUALS),
            array(DocLexer::T_OPEN_CURLY_BRACES),
            array(DocLexer::T_CLOSE_CURLY_BRACES),
            array(DocLexer::T_CLOSE_CURLY_BRACES),
            array(DocLexer::T_CLOSE_PARENTHESIS),
        );

        /**
         * @Annotation({key: {}})
         */
        $tokens[] = array(
            array(DocLexer::T_AT),
            array(DocLexer::T_IDENTIFIER, 'Annotation'),
            array(DocLexer::T_OPEN_PARENTHESIS),
            array(DocLexer::T_OPEN_CURLY_BRACES),
            array(DocLexer::T_IDENTIFIER, 'key'),
            array(DocLexer::T_COLON),
            array(DocLexer::T_OPEN_CURLY_BRACES),
            array(DocLexer::T_CLOSE_CURLY_BRACES),
            array(DocLexer::T_CLOSE_CURLY_BRACES),
            array(DocLexer::T_CLOSE_PARENTHESIS),
        );

        /**
         * @Annotation(a={b: {}})
         */
        $tokens[] = array(
            array(DocLexer::T_AT),
            array(DocLexer::T_IDENTIFIER, 'Annotation'),
            array(DocLexer::T_OPEN_PARENTHESIS),
            array(DocLexer::T_IDENTIFIER, 'a'),
            array(DocLexer::T_EQUALS),
            array(DocLexer::T_OPEN_CURLY_BRACES),
            array(DocLexer::T_IDENTIFIER, 'b'),
            array(DocLexer::T_COLON),
            array(DocLexer::T_OPEN_CURLY_BRACES),
            array(DocLexer::T_CLOSE_CURLY_BRACES),
            array(DocLexer::T_CLOSE_CURLY_BRACES),
            array(DocLexer::T_CLOSE_PARENTHESIS),
        );

        /**
         * @Annotation({key: "value"})
         */
        $tokens[] = array(
            array(DocLexer::T_AT),
            array(DocLexer::T_IDENTIFIER, 'Annotation'),
            array(DocLexer::T_OPEN_PARENTHESIS),
            array(DocLexer::T_OPEN_CURLY_BRACES),
            array(DocLexer::T_IDENTIFIER, 'key'),
            array(DocLexer::T_COLON),
            array(DocLexer::T_STRING, 'value'),
            array(DocLexer::T_CLOSE_CURLY_BRACES),
            array(DocLexer::T_CLOSE_PARENTHESIS),
        );

        /**
         * @Annotation({"key": "value"})
         */
        $tokens[] = array(
            array(DocLexer::T_AT),
            array(DocLexer::T_IDENTIFIER, 'Annotation'),
            array(DocLexer::T_OPEN_PARENTHESIS),
            array(DocLexer::T_OPEN_CURLY_BRACES),
            array(DocLexer::T_STRING, 'key'),
            array(DocLexer::T_COLON),
            array(DocLexer::T_STRING, 'value'),
            array(DocLexer::T_CLOSE_CURLY_BRACES),
            array(DocLexer::T_CLOSE_PARENTHESIS),
        );

        /**
         * @Annotation({a: "b", c: "d"})
         */
        $tokens[] = array(
            array(DocLexer::T_AT),
            array(DocLexer::T_IDENTIFIER, 'Annotation'),
            array(DocLexer::T_OPEN_PARENTHESIS),
            array(DocLexer::T_OPEN_CURLY_BRACES),
            array(DocLexer::T_IDENTIFIER, 'a'),
            array(DocLexer::T_COLON),
            array(DocLexer::T_STRING, 'b'),
            array(DocLexer::T_COMMA),
            array(DocLexer::T_IDENTIFIER, 'c'),
            array(DocLexer::T_COLON),
            array(DocLexer::T_STRING, 'd'),
            array(DocLexer::T_CLOSE_CURLY_BRACES),
            array(DocLexer::T_CLOSE_PARENTHESIS),
        );

        /**
         * @Annotation({a: "b", "c": "d", 123: "e"})
         */
        $tokens[] = array(
            array(DocLexer::T_AT),
            array(DocLexer::T_IDENTIFIER, 'Annotation'),
            array(DocLexer::T_OPEN_PARENTHESIS),
            array(DocLexer::T_OPEN_CURLY_BRACES),
            array(DocLexer::T_IDENTIFIER, 'a'),
            array(DocLexer::T_COLON),
            array(DocLexer::T_STRING, 'b'),
            array(DocLexer::T_COMMA),
            array(DocLexer::T_STRING, 'c'),
            array(DocLexer::T_COLON),
            array(DocLexer::T_STRING, 'd'),
            array(DocLexer::T_COMMA),
            array(DocLexer::T_INTEGER, '123'),
            array(DocLexer::T_COLON),
            array(DocLexer::T_STRING, 'e'),
            array(DocLexer::T_CLOSE_CURLY_BRACES),
            array(DocLexer::T_CLOSE_PARENTHESIS),
        );

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
        $tokens[] = array(
            array(DocLexer::T_AT),
            array(DocLexer::T_IDENTIFIER, 'Annotation'),
            array(DocLexer::T_OPEN_PARENTHESIS),
            array(DocLexer::T_OPEN_CURLY_BRACES),
            array(DocLexer::T_STRING, 'a'),
            array(DocLexer::T_COMMA),
            array(DocLexer::T_OPEN_CURLY_BRACES),
            array(DocLexer::T_OPEN_CURLY_BRACES),
            array(DocLexer::T_STRING, 'c'),
            array(DocLexer::T_CLOSE_CURLY_BRACES),
            array(DocLexer::T_COMMA),
            array(DocLexer::T_STRING, 'b'),
            array(DocLexer::T_CLOSE_CURLY_BRACES),
            array(DocLexer::T_CLOSE_CURLY_BRACES),
            array(DocLexer::T_CLOSE_PARENTHESIS),
        );

        /**
         * @Annotation(@Nested)
         */
        $tokens[] = array(
            array(DocLexer::T_AT),
            array(DocLexer::T_IDENTIFIER, 'Annotation'),
            array(DocLexer::T_OPEN_PARENTHESIS),
            array(DocLexer::T_AT),
            array(DocLexer::T_IDENTIFIER, 'Nested'),
            array(DocLexer::T_CLOSE_PARENTHESIS),
        );

        /**
         * @Annotation(@Nested())
         */
        $tokens[] = array(
            array(DocLexer::T_AT),
            array(DocLexer::T_IDENTIFIER, 'Annotation'),
            array(DocLexer::T_OPEN_PARENTHESIS),
            array(DocLexer::T_AT),
            array(DocLexer::T_IDENTIFIER, 'Nested'),
            array(DocLexer::T_OPEN_PARENTHESIS),
            array(DocLexer::T_CLOSE_PARENTHESIS),
            array(DocLexer::T_CLOSE_PARENTHESIS),
        );

        /**
         * @Annotation(@Nested, @Nested)
         */
        $tokens[] = array(
            array(DocLexer::T_AT),
            array(DocLexer::T_IDENTIFIER, 'Annotation'),
            array(DocLexer::T_OPEN_PARENTHESIS),
            array(DocLexer::T_AT),
            array(DocLexer::T_IDENTIFIER, 'Nested'),
            array(DocLexer::T_COMMA),
            array(DocLexer::T_AT),
            array(DocLexer::T_IDENTIFIER, 'Nested'),
            array(DocLexer::T_CLOSE_PARENTHESIS),
        );

        /**
         * @Annotation(@Nested(), @Nested())
         */
        $tokens[] = array(
            array(DocLexer::T_AT),
            array(DocLexer::T_IDENTIFIER, 'Annotation'),
            array(DocLexer::T_OPEN_PARENTHESIS),
            array(DocLexer::T_AT),
            array(DocLexer::T_IDENTIFIER, 'Nested'),
            array(DocLexer::T_OPEN_PARENTHESIS),
            array(DocLexer::T_CLOSE_PARENTHESIS),
            array(DocLexer::T_COMMA),
            array(DocLexer::T_AT),
            array(DocLexer::T_IDENTIFIER, 'Nested'),
            array(DocLexer::T_OPEN_PARENTHESIS),
            array(DocLexer::T_CLOSE_PARENTHESIS),
            array(DocLexer::T_CLOSE_PARENTHESIS),
        );

        /**
         * @Annotation(
         *     @Nested(),
         *     @Nested()
         * )
         */
        $tokens[] = array(
            array(DocLexer::T_AT),
            array(DocLexer::T_IDENTIFIER, 'Annotation'),
            array(DocLexer::T_OPEN_PARENTHESIS),
            array(DocLexer::T_AT),
            array(DocLexer::T_IDENTIFIER, 'Nested'),
            array(DocLexer::T_OPEN_PARENTHESIS),
            array(DocLexer::T_CLOSE_PARENTHESIS),
            array(DocLexer::T_COMMA),
            array(DocLexer::T_AT),
            array(DocLexer::T_IDENTIFIER, 'Nested'),
            array(DocLexer::T_OPEN_PARENTHESIS),
            array(DocLexer::T_CLOSE_PARENTHESIS),
            array(DocLexer::T_CLOSE_PARENTHESIS),
        );

        /**
         * @Annotation(key=@Nested)
         */
        $tokens[] = array(
            array(DocLexer::T_AT),
            array(DocLexer::T_IDENTIFIER, 'Annotation'),
            array(DocLexer::T_OPEN_PARENTHESIS),
            array(DocLexer::T_IDENTIFIER, 'key'),
            array(DocLexer::T_EQUALS),
            array(DocLexer::T_AT),
            array(DocLexer::T_IDENTIFIER, 'Nested'),
            array(DocLexer::T_CLOSE_PARENTHESIS),
        );

        /**
         * @Annotation(a=@Nested(),b=@Nested)
         */
        $tokens[] = array(
            array(DocLexer::T_AT),
            array(DocLexer::T_IDENTIFIER, 'Annotation'),
            array(DocLexer::T_OPEN_PARENTHESIS),
            array(DocLexer::T_IDENTIFIER, 'a'),
            array(DocLexer::T_EQUALS),
            array(DocLexer::T_AT),
            array(DocLexer::T_IDENTIFIER, 'Nested'),
            array(DocLexer::T_OPEN_PARENTHESIS),
            array(DocLexer::T_CLOSE_PARENTHESIS),
            array(DocLexer::T_COMMA),
            array(DocLexer::T_IDENTIFIER, 'b'),
            array(DocLexer::T_EQUALS),
            array(DocLexer::T_AT),
            array(DocLexer::T_IDENTIFIER, 'Nested'),
            array(DocLexer::T_CLOSE_PARENTHESIS),
        );

        /**
         * @Annotation({key=@Nested})
         */
        $tokens[] = array(
            array(DocLexer::T_AT),
            array(DocLexer::T_IDENTIFIER, 'Annotation'),
            array(DocLexer::T_OPEN_PARENTHESIS),
            array(DocLexer::T_OPEN_CURLY_BRACES),
            array(DocLexer::T_IDENTIFIER, 'key'),
            array(DocLexer::T_EQUALS),
            array(DocLexer::T_AT),
            array(DocLexer::T_IDENTIFIER, 'Nested'),
            array(DocLexer::T_CLOSE_CURLY_BRACES),
            array(DocLexer::T_CLOSE_PARENTHESIS),
        );

        /**
         * @Annotation({a=@Nested(),b=@Nested})
         */
        $tokens[] = array(
            array(DocLexer::T_AT),
            array(DocLexer::T_IDENTIFIER, 'Annotation'),
            array(DocLexer::T_OPEN_PARENTHESIS),
            array(DocLexer::T_OPEN_CURLY_BRACES),
            array(DocLexer::T_IDENTIFIER, 'a'),
            array(DocLexer::T_EQUALS),
            array(DocLexer::T_AT),
            array(DocLexer::T_IDENTIFIER, 'Nested'),
            array(DocLexer::T_OPEN_PARENTHESIS),
            array(DocLexer::T_CLOSE_PARENTHESIS),
            array(DocLexer::T_COMMA),
            array(DocLexer::T_IDENTIFIER, 'b'),
            array(DocLexer::T_EQUALS),
            array(DocLexer::T_AT),
            array(DocLexer::T_IDENTIFIER, 'Nested'),
            array(DocLexer::T_CLOSE_CURLY_BRACES),
            array(DocLexer::T_CLOSE_PARENTHESIS),
        );

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
        $tokens[] = array(
            array(DocLexer::T_AT),
            array(DocLexer::T_IDENTIFIER, 'Annotation'),
            array(DocLexer::T_OPEN_PARENTHESIS),
            array(DocLexer::T_AT),
            array(DocLexer::T_IDENTIFIER, 'Nested'),
            array(DocLexer::T_OPEN_PARENTHESIS),
            array(DocLexer::T_OPEN_CURLY_BRACES),
            array(DocLexer::T_STRING, 'a'),
            array(DocLexer::T_COMMA),
            array(DocLexer::T_OPEN_CURLY_BRACES),
            array(DocLexer::T_OPEN_CURLY_BRACES),
            array(DocLexer::T_STRING, 'c'),
            array(DocLexer::T_CLOSE_CURLY_BRACES),
            array(DocLexer::T_COMMA),
            array(DocLexer::T_STRING, 'b'),
            array(DocLexer::T_CLOSE_CURLY_BRACES),
            array(DocLexer::T_CLOSE_CURLY_BRACES),
            array(DocLexer::T_CLOSE_PARENTHESIS),
            array(DocLexer::T_COMMA),
            array(DocLexer::T_AT),
            array(DocLexer::T_IDENTIFIER, 'Nested'),
            array(DocLexer::T_OPEN_PARENTHESIS),
            array(DocLexer::T_OPEN_CURLY_BRACES),
            array(DocLexer::T_STRING, 'd'),
            array(DocLexer::T_COMMA),
            array(DocLexer::T_OPEN_CURLY_BRACES),
            array(DocLexer::T_OPEN_CURLY_BRACES),
            array(DocLexer::T_STRING, 'f'),
            array(DocLexer::T_COMMA),
            array(DocLexer::T_CLOSE_CURLY_BRACES),
            array(DocLexer::T_COMMA),
            array(DocLexer::T_STRING, 'e'),
            array(DocLexer::T_CLOSE_CURLY_BRACES),
            array(DocLexer::T_CLOSE_CURLY_BRACES),
            array(DocLexer::T_CLOSE_PARENTHESIS),
            array(DocLexer::T_CLOSE_PARENTHESIS),
        );

        return $tokens;
    }

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

    final protected function setPropertyValue($object, string $property, $value)
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
