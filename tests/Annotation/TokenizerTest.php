<?php

namespace KevinGH\Box\Annotation;

use Doctrine\Common\Annotations\DocLexer;
use KevinGH\Box\Annotation\Exception\SyntaxException;
use KevinGH\Box\Annotation\TestTokens;
use KevinGH\Box\Annotation\Tokenizer;

class TokenizerTest extends TestTokens
{
    /**
     * @var Tokenizer
     */
    private $tokenizer;

    public function getDocblocks()
    {
        $docblocks = array();
        $tokens = $this->getTokens();

        $docblocks[] = array(
            '// @comment',
            array()
        );

        $docblocks[] = array(
            <<<DOCBLOCK
        /**
         * Empty.
         */
DOCBLOCK
            ,
            array()
        );

        $docblocks[] = array(
            <<<DOCBLOCK
        /**
         * @Annotation
         */
DOCBLOCK
            ,
            array_shift($tokens)
        );

        $docblocks[] = array(
            <<<DOCBLOCK
        /**
         * @Annotation()
         */
DOCBLOCK
            ,
            array_shift($tokens)
        );

        $docblocks[] = array(
            <<<DOCBLOCK
        /**
         * @Annotation ()
         */
DOCBLOCK
            ,
            array_shift($tokens)
        );

        $docblocks[] = array(
            <<<DOCBLOCK
        /**
         * @A
         * @B
         */
DOCBLOCK
            ,
            array_shift($tokens)
        );

        $docblocks[] = array(
            <<<DOCBLOCK
        /**
         * @A()
         * @B()
         */
DOCBLOCK
            ,
            array_shift($tokens)
        );

        $docblocks[] = array(
            <<<DOCBLOCK
        /**
         * @Namespaced\Annotation
         */
DOCBLOCK
            ,
            array_shift($tokens)
        );

        $docblocks[] = array(
            <<<DOCBLOCK
        /**
         * @Namespaced\ Annotation
         */
DOCBLOCK
            ,
            array_shift($tokens)
        );

        $docblocks[] = array(
            <<<DOCBLOCK
        /**
         * @Namespaced\Annotation()
         */
DOCBLOCK
            ,
            array_shift($tokens)
        );

        $docblocks[] = array(
            <<<DOCBLOCK
        /**
         * @Annotation("string")
         */
DOCBLOCK
            ,
            array_shift($tokens)
        );

        $docblocks[] = array(
            <<<DOCBLOCK
        /**
         * @Annotation(
         *     "string"
         * )
         */
DOCBLOCK
            ,
            array_shift($tokens)
        );

        $docblocks[] = array(
            <<<DOCBLOCK
        /**
         * @Annotation(123, "string", 1.23, CONSTANT, false, true, null)
         */
DOCBLOCK
            ,
            array_shift($tokens)
        );

        $docblocks[] = array(
            <<<DOCBLOCK
        /**
         * @Annotation(constant, FALSE, TRUE, NULL)
         */
DOCBLOCK
            ,
            array_shift($tokens)
        );

        $docblocks[] = array(
            <<<DOCBLOCK
        /**
         * @Annotation(key="value")
         */
DOCBLOCK
            ,
            array_shift($tokens)
        );

        $docblocks[] = array(
            <<<DOCBLOCK
        /**
         * @Annotation(a="b", c="d")
         */
DOCBLOCK
            ,
            array_shift($tokens)
        );

        $docblocks[] = array(
            <<<DOCBLOCK
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
DOCBLOCK
            ,
            array_shift($tokens)
        );

        $docblocks[] = array(
            <<<DOCBLOCK
        /**
         * @Annotation({})
         */
DOCBLOCK
            ,
            array_shift($tokens)
        );

        $docblocks[] = array(
            <<<DOCBLOCK
        /**
         * @Annotation(key={})
         */
DOCBLOCK
            ,
            array_shift($tokens)
        );

        $docblocks[] = array(
            <<<DOCBLOCK
        /**
         * @Annotation({"string"})
         */
DOCBLOCK
            ,
            array_shift($tokens)
        );

        $docblocks[] = array(
            <<<DOCBLOCK
        /**
         * @Annotation(
         *     {
         *         "string"
         *     }
         * )
         */
DOCBLOCK
            ,
            array_shift($tokens)
        );

        $docblocks[] = array(
            <<<DOCBLOCK
        /**
         * @Annotation({123, "string", 1.23, CONSTANT, false, true, null})
         */
DOCBLOCK
            ,
            array_shift($tokens)
        );

        $docblocks[] = array(
            <<<DOCBLOCK
        /**
         * @Annotation({key="value"})
         */
DOCBLOCK
            ,
            array_shift($tokens)
        );

        $docblocks[] = array(
            <<<DOCBLOCK
        /**
         * @Annotation({"key"="value"})
         */
DOCBLOCK
            ,
            array_shift($tokens)
        );

        $docblocks[] = array(
            <<<DOCBLOCK
        /**
         * @Annotation({a="b", c="d"})
         */
DOCBLOCK
            ,
            array_shift($tokens)
        );

        $docblocks[] = array(
            <<<DOCBLOCK
        /**
         * @Annotation({a="b", "c"="d", 123="e"})
         */
DOCBLOCK
            ,
            array_shift($tokens)
        );

        $docblocks[] = array(
            <<<DOCBLOCK
        /**
         * @Annotation({key={}})
         */
DOCBLOCK
            ,
            array_shift($tokens)
        );

        $docblocks[] = array(
            <<<DOCBLOCK
        /**
         * @Annotation(a={b={}})
         */
DOCBLOCK
            ,
            array_shift($tokens)
        );

        $docblocks[] = array(
            <<<DOCBLOCK
        /**
         * @Annotation({key: {}})
         */
DOCBLOCK
            ,
            array_shift($tokens)
        );

        $docblocks[] = array(
            <<<DOCBLOCK
        /**
         * @Annotation(a={b: {}})
         */
DOCBLOCK
            ,
            array_shift($tokens)
        );

        $docblocks[] = array(
            <<<DOCBLOCK
        /**
         * @Annotation({key: "value"})
         */
DOCBLOCK
            ,
            array_shift($tokens)
        );

        $docblocks[] = array(
            <<<DOCBLOCK
        /**
         * @Annotation({"key": "value"})
         */
DOCBLOCK
            ,
            array_shift($tokens)
        );

        $docblocks[] = array(
            <<<DOCBLOCK
        /**
         * @Annotation({a: "b", c: "d"})
         */
DOCBLOCK
            ,
            array_shift($tokens)
        );

        $docblocks[] = array(
            <<<DOCBLOCK
        /**
         * @Annotation({a: "b", "c": "d", 123: "e"})
         */
DOCBLOCK
            ,
            array_shift($tokens)
        );

        $docblocks[] = array(
            <<<DOCBLOCK
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
            array_shift($tokens)
        );

        $docblocks[] = array(
            <<<DOCBLOCK
        /**
         * @Annotation(@Nested)
         */
DOCBLOCK
            ,
            array_shift($tokens)
        );

        $docblocks[] = array(
            <<<DOCBLOCK
        /**
         * @Annotation(@Nested())
         */
DOCBLOCK
            ,
            array_shift($tokens)
        );

        $docblocks[] = array(
            <<<DOCBLOCK
        /**
         * @Annotation(@Nested, @Nested)
         */
DOCBLOCK
            ,
            array_shift($tokens)
        );

        $docblocks[] = array(
            <<<DOCBLOCK
        /**
         * @Annotation(@Nested(), @Nested())
         */
DOCBLOCK
            ,
            array_shift($tokens)
        );

        $docblocks[] = array(
            <<<DOCBLOCK
        /**
         * @Annotation(
         *     @Nested(),
         *     @Nested()
         * )
         */
DOCBLOCK
            ,
            array_shift($tokens)
        );

        $docblocks[] = array(
            <<<DOCBLOCK
        /**
         * @Annotation(key=@Nested)
         */
DOCBLOCK
            ,
            array_shift($tokens)
        );

        $docblocks[] = array(
            <<<DOCBLOCK
        /**
         * @Annotation(a=@Nested(),b=@Nested)
         */
DOCBLOCK
            ,
            array_shift($tokens)
        );

        $docblocks[] = array(
            <<<DOCBLOCK
        /**
         * @Annotation({key=@Nested})
         */
DOCBLOCK
            ,
            array_shift($tokens)
        );

        $docblocks[] = array(
            <<<DOCBLOCK
        /**
         * @Annotation({a=@Nested(),b=@Nested})
         */
DOCBLOCK
            ,
            array_shift($tokens)
        );

        $docblocks[] = array(
            <<<DOCBLOCK
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
            array_shift($tokens)
        );

        // tokenizer specific

        $docblocks[] = array(
            <<<DOCBLOCK
        /**
         * \@Escaped
         */
DOCBLOCK
            ,
            array(),
        );

        $docblocks[] = array(
            <<<DOCBLOCK
        /**
         * @!Skipped
         */
DOCBLOCK
            ,
            array(),
        );

        $docblocks[] = array(
            <<<DOCBLOCK
        /**
         * @Ignored
         * @Kept
         */
DOCBLOCK
            ,
            array(
                array(DocLexer::T_AT),
                array(DocLexer::T_IDENTIFIER, 'Kept'),
            ),
            array('Ignored')
        );

        $docblocks[] = array(
            <<<DOCBLOCK
        /**
         * @Not\\Aliased
         * @Original
         */
DOCBLOCK
            ,
            array(
                array(DocLexer::T_AT),
                array(DocLexer::T_IDENTIFIER, 'Not\\Aliased'),
                array(DocLexer::T_AT),
                array(DocLexer::T_IDENTIFIER, 'Is\\Aliased'),
            ),
            array(),
            array(
                'Original' => 'Is\\Aliased'
            )
        );

        $docblocks[] = array(
            <<<DOCBLOCK
        /**
         * @Not\\Aliased
         * @Original\\Aliased
         */
DOCBLOCK
            ,
            array(
                array(DocLexer::T_AT),
                array(DocLexer::T_IDENTIFIER, 'Not\\Aliased'),
                array(DocLexer::T_AT),
                array(DocLexer::T_IDENTIFIER, 'Is\\Aliased'),
            ),
            array(),
            array(
                'Original' => 'Is'
            )
        );

        return $docblocks;
    }

    public function testInit()
    {
        $this->assertInstanceOf(
            'Doctrine\\Common\\Annotations\\DocLexer',
            $this->getPropertyValue($this->tokenizer, 'lexer')
        );
    }

    /**
     * @dataProvider getDocblocks
     */
    public function testParse(
        $docblock,
        $tokens,
        array $ignored = array(),
        array $aliases = array()
    ) {
        if ($ignored) {
            $this->setPropertyValue($this->tokenizer, 'ignored', $ignored);
        }

        $this->assertSame(
            $tokens,
            $this->tokenizer->parse($docblock, $aliases)
        );
    }

    public function testParseInvalidArrayCommas()
    {
        $this->tokenizer->ignore(array('ignored'));

        try {
            $this->tokenizer->parse('/**@a(1,@ignored)*/');

            $this->fail('Expected exception to be thrown.');
        } catch (SyntaxException $exception) {
            $this->assertSame(
                'Expected Value, received \'@\' at position 5.',
                $exception->getMessage()
            );
        }
    }

    public function testParseInvalidIdentifier()
    {
        try {
            $this->tokenizer->parse('/**@\\*/');

            $this->fail('Expected exception to be thrown.');
        } catch (SyntaxException $exception) {
            $this->assertSame(
                'Expected namespace separator or identifier, received \'\\\' at position 1.',
                $exception->getMessage()
            );
        }
    }

    public function testParseInvalidPlainValue()
    {
        try {
            $this->tokenizer->parse('/**@a(!)*/');

            $this->fail('Expected exception to be thrown.');
        } catch (SyntaxException $exception) {
            $this->assertSame(
                'Expected PlainValue, received \'!\' at position 3.',
                $exception->getMessage()
            );
        }
    }

    public function testParseInvalidMatch()
    {
        try {
            $this->tokenizer->parse('/**@a({x)');

            $this->fail('Expected exception to be thrown.');
        } catch (SyntaxException $exception) {
            $this->assertSame(
                'Expected Doctrine\\Common\\Annotations\\DocLexer::T_CLOSE_CURLY_BRACES, received \')\' at position 5.',
                $exception->getMessage()
            );
        }
    }

    public function testParseInvalidMatchAny()
    {
        try {
            $this->tokenizer->parse('/**@a({@:1})');

            $this->fail('Expected exception to be thrown.');
        } catch (SyntaxException $exception) {
            $this->assertSame(
                'Expected Doctrine\\Common\\Annotations\\DocLexer::T_INTEGER or Doctrine\\Common\\Annotations\\DocLexer::T_STRING, received \'@\' at position 4.',
                $exception->getMessage()
            );
        }
    }

    public function testIgnored()
    {
        $ignore = array(
            'abc',
            'def',
        );

        $this->tokenizer->ignore($ignore);

        $this->assertEquals(
            $ignore,
            $this->getPropertyValue($this->tokenizer, 'ignored')
        );
    }

    protected function setUp()
    {
        $this->tokenizer = new Tokenizer();
    }
}
