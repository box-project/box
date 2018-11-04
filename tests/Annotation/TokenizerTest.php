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
use KevinGH\Box\Annotation\Exception\SyntaxException;

/**
 * @covers \KevinGH\Box\Annotation\Tokenizer
 */
class TokenizerTest extends TestTokens
{
    /**
     * @var Tokenizer
     */
    private $tokenizer;

    public function getDocblocks(): array
    {
        $docblocks = [];
        $tokens = $this->getTokens();

        $docblocks[] = [
            '// @comment',
            [],
        ];

        $docblocks[] = [
            <<<'DOCBLOCK'
        /**
         * Empty.
         */
DOCBLOCK
            ,
            [],
        ];

        $docblocks[] = [
            <<<'DOCBLOCK'
        /**
         * @Annotation
         */
DOCBLOCK
            ,
            array_shift($tokens),
        ];

        $docblocks[] = [
            <<<'DOCBLOCK'
        /**
         * @Annotation()
         */
DOCBLOCK
            ,
            array_shift($tokens),
        ];

        $docblocks[] = [
            <<<'DOCBLOCK'
        /**
         * @Annotation ()
         */
DOCBLOCK
            ,
            array_shift($tokens),
        ];

        $docblocks[] = [
            <<<'DOCBLOCK'
        /**
         * @A
         * @B
         */
DOCBLOCK
            ,
            array_shift($tokens),
        ];

        $docblocks[] = [
            <<<'DOCBLOCK'
        /**
         * @A()
         * @B()
         */
DOCBLOCK
            ,
            array_shift($tokens),
        ];

        $docblocks[] = [
            <<<DOCBLOCK
        /**
         * @Namespaced\Annotation
         */
DOCBLOCK
            ,
            array_shift($tokens),
        ];

        $docblocks[] = [
            <<<DOCBLOCK
        /**
         * @Namespaced\ Annotation
         */
DOCBLOCK
            ,
            array_shift($tokens),
        ];

        $docblocks[] = [
            <<<DOCBLOCK
        /**
         * @Namespaced\Annotation()
         */
DOCBLOCK
            ,
            array_shift($tokens),
        ];

        $docblocks[] = [
            <<<'DOCBLOCK'
        /**
         * @Annotation("string")
         */
DOCBLOCK
            ,
            array_shift($tokens),
        ];

        $docblocks[] = [
            <<<'DOCBLOCK'
        /**
         * @Annotation(
         *     "string"
         * )
         */
DOCBLOCK
            ,
            array_shift($tokens),
        ];

        $docblocks[] = [
            <<<'DOCBLOCK'
        /**
         * @Annotation(123, "string", 1.23, CONSTANT, false, true, null)
         */
DOCBLOCK
            ,
            array_shift($tokens),
        ];

        $docblocks[] = [
            <<<'DOCBLOCK'
        /**
         * @Annotation(constant, FALSE, TRUE, NULL)
         */
DOCBLOCK
            ,
            array_shift($tokens),
        ];

        $docblocks[] = [
            <<<'DOCBLOCK'
        /**
         * @Annotation(key="value")
         */
DOCBLOCK
            ,
            array_shift($tokens),
        ];

        $docblocks[] = [
            <<<'DOCBLOCK'
        /**
         * @Annotation(a="b", c="d")
         */
DOCBLOCK
            ,
            array_shift($tokens),
        ];

        $docblocks[] = [
            <<<'DOCBLOCK'
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
            array_shift($tokens),
        ];

        $docblocks[] = [
            <<<'DOCBLOCK'
        /**
         * @Annotation({})
         */
DOCBLOCK
            ,
            array_shift($tokens),
        ];

        $docblocks[] = [
            <<<'DOCBLOCK'
        /**
         * @Annotation(key={})
         */
DOCBLOCK
            ,
            array_shift($tokens),
        ];

        $docblocks[] = [
            <<<'DOCBLOCK'
        /**
         * @Annotation({"string"})
         */
DOCBLOCK
            ,
            array_shift($tokens),
        ];

        $docblocks[] = [
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
            array_shift($tokens),
        ];

        $docblocks[] = [
            <<<'DOCBLOCK'
        /**
         * @Annotation({123, "string", 1.23, CONSTANT, false, true, null})
         */
DOCBLOCK
            ,
            array_shift($tokens),
        ];

        $docblocks[] = [
            <<<'DOCBLOCK'
        /**
         * @Annotation({key="value"})
         */
DOCBLOCK
            ,
            array_shift($tokens),
        ];

        $docblocks[] = [
            <<<'DOCBLOCK'
        /**
         * @Annotation({"key"="value"})
         */
DOCBLOCK
            ,
            array_shift($tokens),
        ];

        $docblocks[] = [
            <<<'DOCBLOCK'
        /**
         * @Annotation({a="b", c="d"})
         */
DOCBLOCK
            ,
            array_shift($tokens),
        ];

        $docblocks[] = [
            <<<'DOCBLOCK'
        /**
         * @Annotation({a="b", "c"="d", 123="e"})
         */
DOCBLOCK
            ,
            array_shift($tokens),
        ];

        $docblocks[] = [
            <<<'DOCBLOCK'
        /**
         * @Annotation({key={}})
         */
DOCBLOCK
            ,
            array_shift($tokens),
        ];

        $docblocks[] = [
            <<<'DOCBLOCK'
        /**
         * @Annotation(a={b={}})
         */
DOCBLOCK
            ,
            array_shift($tokens),
        ];

        $docblocks[] = [
            <<<'DOCBLOCK'
        /**
         * @Annotation({key: {}})
         */
DOCBLOCK
            ,
            array_shift($tokens),
        ];

        $docblocks[] = [
            <<<'DOCBLOCK'
        /**
         * @Annotation(a={b: {}})
         */
DOCBLOCK
            ,
            array_shift($tokens),
        ];

        $docblocks[] = [
            <<<'DOCBLOCK'
        /**
         * @Annotation({key: "value"})
         */
DOCBLOCK
            ,
            array_shift($tokens),
        ];

        $docblocks[] = [
            <<<'DOCBLOCK'
        /**
         * @Annotation({"key": "value"})
         */
DOCBLOCK
            ,
            array_shift($tokens),
        ];

        $docblocks[] = [
            <<<'DOCBLOCK'
        /**
         * @Annotation({a: "b", c: "d"})
         */
DOCBLOCK
            ,
            array_shift($tokens),
        ];

        $docblocks[] = [
            <<<'DOCBLOCK'
        /**
         * @Annotation({a: "b", "c": "d", 123: "e"})
         */
DOCBLOCK
            ,
            array_shift($tokens),
        ];

        $docblocks[] = [
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
            array_shift($tokens),
        ];

        $docblocks[] = [
            <<<'DOCBLOCK'
        /**
         * @Annotation(@Nested)
         */
DOCBLOCK
            ,
            array_shift($tokens),
        ];

        $docblocks[] = [
            <<<'DOCBLOCK'
        /**
         * @Annotation(@Nested())
         */
DOCBLOCK
            ,
            array_shift($tokens),
        ];

        $docblocks[] = [
            <<<'DOCBLOCK'
        /**
         * @Annotation(@Nested, @Nested)
         */
DOCBLOCK
            ,
            array_shift($tokens),
        ];

        $docblocks[] = [
            <<<'DOCBLOCK'
        /**
         * @Annotation(@Nested(), @Nested())
         */
DOCBLOCK
            ,
            array_shift($tokens),
        ];

        $docblocks[] = [
            <<<'DOCBLOCK'
        /**
         * @Annotation(
         *     @Nested(),
         *     @Nested()
         * )
         */
DOCBLOCK
            ,
            array_shift($tokens),
        ];

        $docblocks[] = [
            <<<'DOCBLOCK'
        /**
         * @Annotation(key=@Nested)
         */
DOCBLOCK
            ,
            array_shift($tokens),
        ];

        $docblocks[] = [
            <<<'DOCBLOCK'
        /**
         * @Annotation(a=@Nested(),b=@Nested)
         */
DOCBLOCK
            ,
            array_shift($tokens),
        ];

        $docblocks[] = [
            <<<'DOCBLOCK'
        /**
         * @Annotation({key=@Nested})
         */
DOCBLOCK
            ,
            array_shift($tokens),
        ];

        $docblocks[] = [
            <<<'DOCBLOCK'
        /**
         * @Annotation({a=@Nested(),b=@Nested})
         */
DOCBLOCK
            ,
            array_shift($tokens),
        ];

        $docblocks[] = [
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
            array_shift($tokens),
        ];

        // tokenizer specific

        $docblocks[] = [
            <<<DOCBLOCK
        /**
         * \@Escaped
         */
DOCBLOCK
            ,
            [],
        ];

        $docblocks[] = [
            <<<'DOCBLOCK'
        /**
         * @!Skipped
         */
DOCBLOCK
            ,
            [],
        ];

        $docblocks[] = [
            <<<'DOCBLOCK'
        /**
         * @Ignored
         * @Kept
         */
DOCBLOCK
            ,
            [
                [DocLexer::T_AT],
                [DocLexer::T_IDENTIFIER, 'Kept'],
            ],
            ['Ignored'],
        ];

        $docblocks[] = [
            <<<'DOCBLOCK'
        /**
         * @Not\Aliased
         * @Original
         */
DOCBLOCK
            ,
            [
                [DocLexer::T_AT],
                [DocLexer::T_IDENTIFIER, 'Not\\Aliased'],
                [DocLexer::T_AT],
                [DocLexer::T_IDENTIFIER, 'Is\\Aliased'],
            ],
            [],
            [
                'Original' => 'Is\\Aliased',
            ],
        ];

        $docblocks[] = [
            <<<'DOCBLOCK'
        /**
         * @Not\Aliased
         * @Original\Aliased
         */
DOCBLOCK
            ,
            [
                [DocLexer::T_AT],
                [DocLexer::T_IDENTIFIER, 'Not\\Aliased'],
                [DocLexer::T_AT],
                [DocLexer::T_IDENTIFIER, 'Is\\Aliased'],
            ],
            [],
            [
                'Original' => 'Is',
            ],
        ];

        return $docblocks;
    }

    public function testInit(): void
    {
        $this->assertInstanceOf(
            'Doctrine\\Common\\Annotations\\DocLexer',
            $this->getPropertyValue($this->tokenizer, 'lexer')
        );
    }

    /**
     * @dataProvider getDocblocks
     *
     * @param mixed $docblock
     * @param mixed $tokens
     */
    public function testParse(
        $docblock,
        $tokens,
        array $ignored = [],
        array $aliases = []
    ): void {
        if ($ignored) {
            $this->setPropertyValue($this->tokenizer, 'ignored', $ignored);
        }

        $this->assertSame(
            $tokens,
            $this->tokenizer->parse($docblock, $aliases)
        );
    }

    public function testParseInvalidArrayCommas(): void
    {
        $this->tokenizer->ignore(['ignored']);

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

    public function testParseInvalidIdentifier(): void
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

    public function testParseInvalidPlainValue(): void
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

    public function testParseInvalidMatch(): void
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

    public function testParseInvalidMatchAny(): void
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

    public function testIgnored(): void
    {
        $ignore = [
            'abc',
            'def',
        ];

        $this->tokenizer->ignore($ignore);

        $this->assertEquals(
            $ignore,
            $this->getPropertyValue($this->tokenizer, 'ignored')
        );
    }

    protected function setUp(): void
    {
        $this->tokenizer = new Tokenizer();
    }
}
