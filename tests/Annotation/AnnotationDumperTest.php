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
 * @covers \KevinGH\Box\Annotation\AnnotationDumper
 */
class AnnotationDumperTest extends TestCase
{
    /**
     * @var DocblockParser
     */
    private $docblockParser;

    /**
     * @var AnnotationDumper
     */
    private $annotationDumper;

    /**
     * {@inheritdoc}
     */
    protected function setUp(): void
    {
        $this->docblockParser = new DocblockParser();
        $this->annotationDumper = new AnnotationDumper();
    }

    /**
     * @dataProvider provideDocblocks
     */
    public function test_it_can_parse_PHP_docblocks(string $docblock, array $expected, array $ignore = []): void
    {
        $actual = $this->annotationDumper->dump(
            $this->docblockParser->parse($docblock),
            $ignore
        );

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
         * Empty.
         */
DOCBLOCK
            ,
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
 * @Annotation()
 */
DOCBLOCK
            ,
            ['@Annotation()'],
        ];

        yield [
            <<<'DOCBLOCK'
/**
 * @Annotation ()
 */
DOCBLOCK
            ,
            ['@Annotation'],
        ];

        yield [
            <<<'DOCBLOCK'
/**
 * @A
 * @B
 */
DOCBLOCK
            ,
            [
                '@A',
                '@B',
            ],
        ];

        yield [
            <<<'DOCBLOCK'
/**
 * @A()
 * @B()
 */
DOCBLOCK
            ,
            [
                '@A()',
                '@B()',
            ],
        ];

        yield [
            <<<'DOCBLOCK'
/**
 * @Namespaced\Annotation
 */
DOCBLOCK
            ,
            ['@Namespaced\Annotation'],
        ];

        yield [
            <<<'DOCBLOCK'
/**
 * @Namespaced\ Annotation
 */
DOCBLOCK
            ,
            ['@Namespaced'],
        ];

        yield [
            <<<'DOCBLOCK'
/**
 * @Namespaced\Annotation()
 */
DOCBLOCK
            ,
            ['@Namespaced\Annotation()'],
        ];

        yield [
            <<<'DOCBLOCK'
/**
 * @Annotation("string")
 */
DOCBLOCK
            ,
            ['@Annotation("string")'],
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
            ['@Annotation("string")'],
        ];

        yield [
            <<<'DOCBLOCK'
/**
 * @Annotation(123, "string", 1.23, false, true, null)
 */
DOCBLOCK
            ,
            ['@Annotation(123,"string",1.23,false,true,null)'],
        ];

        yield [
            <<<'DOCBLOCK'
/**
 * @Annotation(a="b", c="d")
 */
DOCBLOCK
            ,
            ['@Annotation(a="b",c="d")'],
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
            ['@Annotation(a=123,b="string",c=1.23,e=false,f=true,g=null)'],
        ];

        yield [
            <<<'DOCBLOCK'
/**
 * @Annotation(key={})
 */
DOCBLOCK
            ,
            ['@Annotation(key={})'],
        ];

        yield [
            <<<'DOCBLOCK'
/**
 * @Annotation({"string"})
 */
DOCBLOCK
            ,
            ['@Annotation({"string"})'],
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
            ['@Annotation({"string"})'],
        ];

        yield [
            <<<'DOCBLOCK'
/**
 * @Annotation({123, "string", 1.23, false, true, null})
 */
DOCBLOCK
            ,
            ['@Annotation({123,"string",1.23,false,true,null})'],
        ];

        yield [
            <<<'DOCBLOCK'
/**
 * @Annotation({"key"="value"})
 */
DOCBLOCK
            ,
            ['@Annotation({"key"="value"})'],
        ];

        yield [
            <<<'DOCBLOCK'
/**
 * @Annotation({a="b", c="d"})
 */
DOCBLOCK
            ,
            ['@Annotation({a="b",c="d"})'],
        ];

        yield [
            <<<'DOCBLOCK'
/**
 * @Annotation({a="b", "c"="d", 123="e"})
 */
DOCBLOCK
            ,
            ['@Annotation({a="b","c"="d",123="e"})'],
        ];

        yield [
            <<<'DOCBLOCK'
/**
 * @Annotation({key={}})
 */
DOCBLOCK
            ,
            ['@Annotation({key={}})'],
        ];

        yield [
            <<<'DOCBLOCK'
/**
 * @Annotation(a={b={}})
 */
DOCBLOCK
            ,
            ['@Annotation(a={b={}})'],
        ];

        yield [
            <<<'DOCBLOCK'
/**
 * @Annotation({key: {}})
 */
DOCBLOCK
            ,
            ['@Annotation({key:{}})'],
        ];

        yield [
            <<<'DOCBLOCK'
/**
 * @Annotation(a={b: {}})
 */
DOCBLOCK
            ,
            ['@Annotation(a={b:{}})'],
        ];

        yield [
            <<<'DOCBLOCK'
/**
 * @Annotation({key: "value"})
 */
DOCBLOCK
            ,
            ['@Annotation({key:"value"})'],
        ];

        yield [
            <<<'DOCBLOCK'
/**
 * @Annotation({a: "b", c: "d"})
 */
DOCBLOCK
            ,
            ['@Annotation({a:"b",c:"d"})'],
        ];

        yield [
            <<<'DOCBLOCK'
/**
 * @Annotation({a: "b", "c": "d", 123: "e"})
 */
DOCBLOCK
            ,
            ['@Annotation({a:"b","c":"d",123:"e"})'],
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
            ['@Annotation({"a",{{"c"},"b"}})'],
        ];

        yield [
            <<<'DOCBLOCK'
/**
 * @Annotation(@Nested)
 */
DOCBLOCK
            ,
            ['@Annotation(@Nested)'],
        ];

        yield [
            <<<'DOCBLOCK'
/**
 * @Annotation(@Nested())
 */
DOCBLOCK
            ,
            ['@Annotation(@Nested())'],
        ];

        yield [
            <<<'DOCBLOCK'
/**
 * @Annotation(@Nested, @Nested)
 */
DOCBLOCK
            ,
            ['@Annotation(@Nested,@Nested)'],
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
            ['@Annotation(@Nested(),@Nested())'],
        ];

        yield [
            <<<'DOCBLOCK'
/**
 * @Annotation(key=@Nested)
 */
DOCBLOCK
            ,
            ['@Annotation(key=@Nested)'],
        ];

        yield [
            <<<'DOCBLOCK'
/**
 * @Annotation(a=@Nested(),b=@Nested)
 */
DOCBLOCK
            ,
            ['@Annotation(a=@Nested(),b=@Nested)'],
        ];

        yield [
            <<<'DOCBLOCK'
/**
 * @Annotation({key=@Nested})
 */
DOCBLOCK
            ,
            ['@Annotation({key=@Nested})'],
        ];

        yield [
            <<<'DOCBLOCK'
/**
 * @Annotation({a=@Nested(),b=@Nested})
 */
DOCBLOCK
            ,
            ['@Annotation({a=@Nested(),b=@Nested})'],
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
            ['@Annotation(@Nested({"a",{{"c"},"b"}}),@Nested({"d",{{"f"},"e"}}))'],
        ];

        yield [
            <<<DOCBLOCK
        /**
         * \@Escaped
         */
DOCBLOCK
            ,
            ['@Escaped'],
        ];

        yield 'multiple without parameters' => [
            <<<'DOCBLOCK'
/** @Annotation1 @Annotation2 @Annotation3 */
DOCBLOCK
            ,
            [
                '@Annotation1',
                '@Annotation2',
                '@Annotation3',
            ],
        ];

        yield 'multiple with comments' => [
            <<<'DOCBLOCK'
/**
 * Hello world
 * @Annotation1
 * Hola mundo
 * @Annotation2
 */
DOCBLOCK
            ,
            [
                '@Annotation1',
                '@Annotation2',
            ],
        ];

        yield 'fully qualified with parameter' => [
            <<<'DOCBLOCK'
/**
* @\Ns\Annotation("value")
*/
DOCBLOCK
            ,
            ['@\Ns\Annotation("value")'],
        ];
        yield 'with array' => [
            <<<'DOCBLOCK'
/**
* @return array<string>
*/
DOCBLOCK
            ,
            ['@return'],
        ];

        yield 'fully qualified, nested, multiple parameters' => [
            <<<'DOCBLOCK'
/**
* @\Ns\Name(int=1, annot=@Annot, float=1.2)
*/
DOCBLOCK
            ,
            ['@\Ns\Name(int=1,annot=@Annot,float=1.2)'],
        ];

        yield 'nested, with arrays' => [
            <<<'DOCBLOCK'
/**
* @Annot(
*  v1={1,2,3},
*  v2={@one,@two,@three},
*  v3={one=1,two=2,three=3},
*  v4={one=@one(1),two=@two(2),three=@three(3)}
* )
*/
DOCBLOCK
            ,
            ['@Annot(v1={1,2,3},v2={@one,@two,@three},v3={one=1,two=2,three=3},v4={one=@one(1),two=@two(2),three=@three(3)})'],
        ];

        yield 'ORM Id example' => [
            <<<'DOCBLOCK'
/**
 * @ORM\Id @ORM\Column(type="integer")
 * @ORM\GeneratedValue
 */
DOCBLOCK
            ,
            [
                '@ORM\Id',
                '@ORM\Column(type="integer")',
                '@ORM\GeneratedValue',
            ],
        ];

        yield 'unicode' => [
            <<<'DOCBLOCK'
/**
 * @Fancy😊Annotation
 */
DOCBLOCK
            ,
            ['@Fancy😊Annotation'],
        ];

        yield 'spaces after @' => [
            <<<'DOCBLOCK'
/**
 * @
 * @ Hello world
 */
DOCBLOCK
            ,
            [],
        ];

        yield 'numbers' => [
            <<<'DOCBLOCK'
/**
 * @Annotation(1, 123, -123, 1.2, 123.456, -123.456, 1e2, 123e456, 1.2e-3, -123.456E-789)
 */
DOCBLOCK
            ,
            ['@Annotation(1,123,-123,1.2,123.456,-123.456,1e2,123e456,1.2e-3,-123.456E-789)'],
        ];

        yield 'ORM Column example' => [
            <<<'DOCBLOCK'
/** @ORM\Column(type="string", length=50, nullable=true) */
DOCBLOCK
            ,
            ['@ORM\Column(type="string",length=50,nullable=true)'],
        ];

        yield 'complex ORM M:N' => [
            <<<'DOCBLOCK'
/**
 * @ORM\ManyToMany(targetEntity=CmsGroup::class, inversedBy="users", cascade={"persist"})
 * @ORM\JoinTable(name="cms_users_groups",
 *      joinColumns={@ORM\JoinColumn(name="user_id", referencedColumnName="id")},
 *      inverseJoinColumns={@ORM\JoinColumn(name="group_id", referencedColumnName="id")}
 * )
 */
DOCBLOCK
            ,
            [
                '@ORM\ManyToMany(targetEntity=CmsGroup::class,inversedBy="users",cascade={"persist"})',
                '@ORM\JoinTable(name="cms_users_groups",joinColumns={@ORM\JoinColumn(name="user_id",referencedColumnName="id")},inverseJoinColumns={@ORM\JoinColumn(name="group_id",referencedColumnName="id")})',
            ],
        ];

        yield 'Symfony route' => [
            <<<'DOCBLOCK'
/**
 * @Route("/argument_with_route_param_and_default/{value}", defaults={"value": "value"}, name="argument_with_route_param_and_default")
 */
DOCBLOCK
            ,
            ['@Route("/argument_with_route_param_and_default/{value}",defaults={"value":"value"},name="argument_with_route_param_and_default")'],
        ];

        yield 'SymfonyFrameworkExtraBundle annotations' => [
            <<<'DOCBLOCK'
/**
 * @Route("/is_granted/resolved/conflict")
 * @IsGranted("ISGRANTED_VOTER", subject="request")
 * @Security("is_granted('ISGRANTED_VOTER', request)")
 */
DOCBLOCK
            ,
            [
                '@Route("/is_granted/resolved/conflict")',
                '@IsGranted("ISGRANTED_VOTER",subject="request")',
                '@Security("is_granted(\'ISGRANTED_VOTER\', request)")',
            ],
        ];

        yield 'JMS Serializer field' => [
            <<<'DOCBLOCK'
/**
 * @Type("array<string,string>")
 * @SerializedName("addresses")
 * @XmlElement(namespace="http://example.com/namespace2")
 * @XmlMap(inline = false, entry = "address", keyAttribute = "id", namespace="http://example.com/namespace2")
 */
DOCBLOCK
            ,
            [
                '@Type("array<string,string>")',
                '@SerializedName("addresses")',
                '@XmlElement(namespace="http://example.com/namespace2")',
                '@XmlMap(inline=false,entry="address",keyAttribute="id",namespace="http://example.com/namespace2")',
            ],
        ];

        yield 'string escaping' => [
            <<<'DOCBLOCK'
/**
 * @Annotation("", "foo", "b\"a\"r", "ba\\z", "bla\h", "\\\\hello\\\\")
 */
DOCBLOCK
            ,
            ['@Annotation("","foo","b\"a\"r","ba\\\\z","bla\h","\\\\\\\\hello\\\\\\\\")'],
        ];
        yield 'constants' => [
            <<<'DOCBLOCK'
/**
 * @Annotation(Foo\Bar::BAZ, \Foo\Bar\Baz::BLAH)
 */
DOCBLOCK
            ,
            ['@Annotation(Foo\Bar::BAZ,\Foo\Bar\Baz::BLAH)'],
        ];
        yield [
            <<<'DOCBLOCK'
/**
 * @TrailingComma(
 *     123,
 *     @Foo(1, 2, 3,),
 *     @Bar,
 * )
 */
DOCBLOCK
            ,
            ['@TrailingComma(123,@Foo(1,2,3),@Bar)'],
        ];

        yield 'inline annotation' => [
            <<<'DOCBLOCK'
/**
 * Hello world from @Annotation
 */
DOCBLOCK
            ,
            ['@Annotation'],
        ];

        yield 'one-line annotation' => [
            <<<'DOCBLOCK'
/** @var string */
DOCBLOCK
            ,
            ['@var'],
        ];

        yield [
            <<<'DOCBLOCK'
/**
 * @Ignored
 * @Kept
 */
DOCBLOCK
            ,
            ['@Kept'],
            ['Ignored'],
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
            ['Ignored'],
        ];

        yield [
            <<<'DOCBLOCK'
/**
 * @Kept(@Ignored)
 */
DOCBLOCK
            ,
            ['@Kept()'],
            ['Ignored'],
        ];
    }
}
