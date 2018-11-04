<?php

namespace KevinGH\Box\Annotation\Convert;

use Doctrine\Common\Annotations\DocLexer;
use KevinGH\Box\Annotation\Convert\ToArray;
use KevinGH\Box\Annotation\Sequence;
use KevinGH\Box\Annotation\TestTokens;

class ToArrayTest extends TestTokens
{
    /**
     * @var ToArray
     */
    private $converter;

    public function getArrays()
    {
        $arrays = array();
        $tokens = $this->getTOkens();

        /**
         * @Annotation
         */
        $arrays[] = array(
            array_shift($tokens),
            array(
                (object) array(
                    'name' => 'Annotation',
                    'values' => array(),
                ),
            )
        );

        /**
         * @Annotation()
         */
        $arrays[] = array(
            array_shift($tokens),
            array(
                (object) array(
                    'name' => 'Annotation',
                    'values' => array(),
                ),
            )
        );

        /**
         * @Annotation ()
         */
        $arrays[] = array(
            array_shift($tokens),
            array(
                (object) array(
                    'name' => 'Annotation',
                    'values' => array(),
                ),
            )
        );

        /**
         * @A
         * @B
         */
        $arrays[] = array(
            array_shift($tokens),
            array(
                (object) array(
                    'name' => 'A',
                    'values' => array(),
                ),
                (object) array(
                    'name' => 'B',
                    'values' => array(),
                ),
            )
        );

        /**
         * @A()
         * @B()
         */
        $arrays[] = array(
            array_shift($tokens),
            array(
                (object) array(
                    'name' => 'A',
                    'values' => array(),
                ),
                (object) array(
                    'name' => 'B',
                    'values' => array(),
                ),
            )
        );

        /**
         * @Namespaced\Annotation
         */
        $arrays[] = array(
            array_shift($tokens),
            array(
                (object) array(
                    'name' => 'Namespaced\\Annotation',
                    'values' => array(),
                ),
            )
        );

        /**
         * @Namespaced\ Annotation
         */
        $arrays[] = array(
            array_shift($tokens),
            array(
                (object) array(
                    'name' => 'Namespaced\\Annotation',
                    'values' => array(),
                ),
            )
        );

        /**
         * @Namespaced\Annotation()
         */
        $arrays[] = array(
            array_shift($tokens),
            array(
                (object) array(
                    'name' => 'Namespaced\\Annotation',
                    'values' => array(),
                ),
            )
        );

        /**
         * @Annotation("string")
         */
        $arrays[] = array(
            array_shift($tokens),
            array(
                (object) array(
                    'name' => 'Annotation',
                    'values' => array(
                        'string'
                    ),
                ),
            )
        );

        /**
         * @Annotation(
         *     "string"
         * )
         */
        $arrays[] = array(
            array_shift($tokens),
            array(
                (object) array(
                    'name' => 'Annotation',
                    'values' => array(
                        'string'
                    ),
                ),
            )
        );

        /**
         * @Annotation(123, "string", 1.23, CONSTANT, false, true, null)
         */
        $arrays[] = array(
            array_shift($tokens),
            array(
                (object) array(
                    'name' => 'Annotation',
                    'values' => array(
                        123,
                        'string',
                        1.23,
                        'CONSTANT',
                        false,
                        true,
                        null
                    ),
                ),
            )
        );

        /**
         * @Annotation(constant, FALSE, TRUE, NULL)
         */
        $arrays[] = array(
            array_shift($tokens),
            array(
                (object) array(
                    'name' => 'Annotation',
                    'values' => array(
                        'constant',
                        false,
                        true,
                        null
                    ),
                ),
            )
        );

        /**
         * @Annotation(key="value")
         */
        $arrays[] = array(
            array_shift($tokens),
            array(
                (object) array(
                    'name' => 'Annotation',
                    'values' => array(
                        'key' => 'value'
                    ),
                ),
            )
        );

        /**
         * @Annotation(a="b", c="d")
         */
        $arrays[] = array(
            array_shift($tokens),
            array(
                (object) array(
                    'name' => 'Annotation',
                    'values' => array(
                        'a' => 'b',
                        'c' => 'd'
                    ),
                ),
            )
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
        $arrays[] = array(
            array_shift($tokens),
            array(
                (object) array(
                    'name' => 'Annotation',
                    'values' => array(
                        'a' => 123,
                        'b' => 'string',
                        'c' => 1.23,
                        'd' => 'CONSTANT',
                        'e' => false,
                        'f' => true,
                        'g' => null
                    ),
                ),
            )
        );

        /**
         * @Annotation({})
         */
        $arrays[] = array(
            array_shift($tokens),
            array(
                (object) array(
                    'name' => 'Annotation',
                    'values' => array(
                        array()
                    ),
                ),
            )
        );

        /**
         * @Annotation(key={})
         */
        $arrays[] = array(
            array_shift($tokens),
            array(
                (object) array(
                    'name' => 'Annotation',
                    'values' => array(
                        'key' => array()
                    ),
                ),
            )
        );

        /**
         * @Annotation({"string"})
         */
        $arrays[] = array(
            array_shift($tokens),
            array(
                (object) array(
                    'name' => 'Annotation',
                    'values' => array(
                        array('string')
                    ),
                ),
            )
        );

        /**
         * @Annotation(
         *     {
         *         "string"
         *     }
         * )
         */
        $arrays[] = array(
            array_shift($tokens),
            array(
                (object) array(
                    'name' => 'Annotation',
                    'values' => array(
                        array('string')
                    ),
                ),
            )
        );

        /**
         * @Annotation({123, "string", 1.23, CONSTANT, false, true, null})
         */
        $arrays[] = array(
            array_shift($tokens),
            array(
                (object) array(
                    'name' => 'Annotation',
                    'values' => array(
                        array(
                            123,
                            'string',
                            1.23,
                            'CONSTANT',
                            false,
                            true,
                            null
                        )
                    ),
                ),
            )
        );

        /**
         * @Annotation({key="value"})
         */
        $arrays[] = array(
            array_shift($tokens),
            array(
                (object) array(
                    'name' => 'Annotation',
                    'values' => array(
                        array(
                            'key' => 'value'
                        )
                    ),
                ),
            )
        );

        /**
         * @Annotation({"key"="value"})
         */
        $arrays[] = array(
            array_shift($tokens),
            array(
                (object) array(
                    'name' => 'Annotation',
                    'values' => array(
                        array(
                            'key' => 'value'
                        )
                    ),
                ),
            )
        );

        /**
         * @Annotation({a="b", c="d"})
         */
        $arrays[] = array(
            array_shift($tokens),
            array(
                (object) array(
                    'name' => 'Annotation',
                    'values' => array(
                        array(
                            'a' => 'b',
                            'c' => 'd'
                        )
                    ),
                ),
            )
        );

        /**
         * @Annotation({a="b", "c"="d", 123="e"})
         */
        $arrays[] = array(
            array_shift($tokens),
            array(
                (object) array(
                    'name' => 'Annotation',
                    'values' => array(
                        array(
                            'a' => 'b',
                            'c' => 'd',
                            123 => 'e'
                        )
                    ),
                ),
            )
        );

        /**
         * @Annotation({key={}})
         */
        $arrays[] = array(
            array_shift($tokens),
            array(
                (object) array(
                    'name' => 'Annotation',
                    'values' => array(
                        array(
                            'key' => array()
                        )
                    ),
                ),
            )
        );

        /**
         * @Annotation(a={b={}})
         */
        $arrays[] = array(
            array_shift($tokens),
            array(
                (object) array(
                    'name' => 'Annotation',
                    'values' => array(
                        'a' => array(
                            'b' => array()
                        )
                    ),
                ),
            )
        );

        /**
         * @Annotation({key: {}})
         */
        $arrays[] = array(
            array_shift($tokens),
            array(
                (object) array(
                    'name' => 'Annotation',
                    'values' => array(
                        array(
                            'key' => array()
                        )
                    ),
                ),
            )
        );

        /**
         * @Annotation(a={b: {}})
         */
        $arrays[] = array(
            array_shift($tokens),
            array(
                (object) array(
                    'name' => 'Annotation',
                    'values' => array(
                        'a' => array(
                            'b' => array()
                        )
                    ),
                ),
            )
        );

        /**
         * @Annotation({key: "value"})
         */
        $arrays[] = array(
            array_shift($tokens),
            array(
                (object) array(
                    'name' => 'Annotation',
                    'values' => array(
                        array(
                            'key' => 'value'
                        )
                    ),
                ),
            )
        );

        /**
         * @Annotation({"key": "value"})
         */
        $arrays[] = array(
            array_shift($tokens),
            array(
                (object) array(
                    'name' => 'Annotation',
                    'values' => array(
                        array(
                            'key' => 'value'
                        )
                    ),
                ),
            )
        );

        /**
         * @Annotation({a: "b", c: "d"})
         */
        $arrays[] = array(
            array_shift($tokens),
            array(
                (object) array(
                    'name' => 'Annotation',
                    'values' => array(
                        array(
                            'a' => 'b',
                            'c' => 'd'
                        )
                    ),
                ),
            )
        );

        /**
         * @Annotation({a: "b", "c": "d", 123: "e"})
         */
        $arrays[] = array(
            array_shift($tokens),
            array(
                (object) array(
                    'name' => 'Annotation',
                    'values' => array(
                        array(
                            'a' => 'b',
                            'c' => 'd',
                            123 => 'e'
                        )
                    ),
                ),
            )
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
        $arrays[] = array(
            array_shift($tokens),
            array(
                (object) array(
                    'name' => 'Annotation',
                    'values' => array(
                        array(
                            'a',
                            array(
                                array(
                                    'c'
                                ),
                                'b'
                            )
                        )
                    ),
                ),
            )
        );

        /**
         * @Annotation(@Nested)
         */
        $arrays[] = array(
            array_shift($tokens),
            array(
                (object) array(
                    'name' => 'Annotation',
                    'values' => array(
                        (object) array(
                            'name' => 'Nested',
                            'values' => array(),
                        ),
                    ),
                ),
            )
        );

        /**
         * @Annotation(@Nested())
         */
        $arrays[] = array(
            array_shift($tokens),
            array(
                (object) array(
                    'name' => 'Annotation',
                    'values' => array(
                        (object) array(
                            'name' => 'Nested',
                            'values' => array(),
                        ),
                    ),
                ),
            )
        );

        /**
         * @Annotation(@Nested, @Nested)
         */
        $arrays[] = array(
            array_shift($tokens),
            array(
                (object) array(
                    'name' => 'Annotation',
                    'values' => array(
                        (object) array(
                            'name' => 'Nested',
                            'values' => array(),
                        ),
                        (object) array(
                            'name' => 'Nested',
                            'values' => array(),
                        ),
                    ),
                ),
            )
        );

        /**
         * @Annotation(@Nested(), @Nested())
         */
        $arrays[] = array(
            array_shift($tokens),
            array(
                (object) array(
                    'name' => 'Annotation',
                    'values' => array(
                        (object) array(
                            'name' => 'Nested',
                            'values' => array(),
                        ),
                        (object) array(
                            'name' => 'Nested',
                            'values' => array(),
                        ),
                    ),
                ),
            )
        );

        /**
         * @Annotation(
         * @Nested(),
         * @Nested()
         * )
         */
        $arrays[] = array(
            array_shift($tokens),
            array(
                (object) array(
                    'name' => 'Annotation',
                    'values' => array(
                        (object) array(
                            'name' => 'Nested',
                            'values' => array(),
                        ),
                        (object) array(
                            'name' => 'Nested',
                            'values' => array(),
                        ),
                    ),
                ),
            )
        );

        /**
         * @Annotation(key=@Nested)
         */
        $arrays[] = array(
            array_shift($tokens),
            array(
                (object) array(
                    'name' => 'Annotation',
                    'values' => array(
                        'key' => (object) array(
                            'name' => 'Nested',
                            'values' => array(),
                        ),
                    ),
                ),
            )
        );

        /**
         * @Annotation(a=@Nested(),b=@Nested)
         */
        $arrays[] = array(
            array_shift($tokens),
            array(
                (object) array(
                    'name' => 'Annotation',
                    'values' => array(
                        'a' => (object) array(
                            'name' => 'Nested',
                            'values' => array(),
                        ),
                        'b' => (object) array(
                            'name' => 'Nested',
                            'values' => array(),
                        ),
                    ),
                ),
            )
        );

        /**
         * @Annotation({key=@Nested})
         */
        $arrays[] = array(
            array_shift($tokens),
            array(
                (object) array(
                    'name' => 'Annotation',
                    'values' => array(
                        array(
                            'key' => (object) array(
                                'name' => 'Nested',
                                'values' => array(),
                            ),
                        )
                    ),
                ),
            )
        );

        /**
         * @Annotation({a=@Nested(),b=@Nested})
         */
        $arrays[] = array(
            array_shift($tokens),
            array(
                (object) array(
                    'name' => 'Annotation',
                    'values' => array(
                        array(
                            'a' => (object) array(
                                'name' => 'Nested',
                                'values' => array(),
                            ),
                            'b' => (object) array(
                                'name' => 'Nested',
                                'values' => array(),
                            ),
                        )
                    ),
                ),
            )
        );

        /**
         * @Annotation(
         * @Nested(
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
         * @Nested(
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
        $arrays[] = array(
            array_shift($tokens),
            array(
                (object) array(
                    'name' => 'Annotation',
                    'values' => array(
                        (object) array(
                            'name' => 'Nested',
                            'values' => array(
                                array(
                                    'a',
                                    array(
                                        array(
                                            'c'
                                        ),
                                        'b'
                                    )
                                )
                            ),
                        ),
                        (object) array(
                            'name' => 'Nested',
                            'values' => array(
                                array(
                                    'd',
                                    array(
                                        array(
                                            'f'
                                        ),
                                        'e'
                                    )
                                )
                            ),
                        ),
                    ),
                ),
            )
        );

        return $arrays;
    }

    /**
     * @dataProvider getArrays
     */
    public function testConvert($tokens, $expected)
    {
        $this->assertEquals(
            $expected,
            $this->converter->convert(
                new Sequence($tokens)
            )
        );
    }

    protected function setUp()
    {
        $this->converter = new ToArray();
    }
}
