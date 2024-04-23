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

namespace KevinGH\Box\Compactor;

use Fidry\Console\DisplayNormalizer;
use KevinGH\Box\Annotation\CompactedFormatter;
use KevinGH\Box\Annotation\DocblockAnnotationParser;
use phpDocumentor\Reflection\DocBlockFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * @internal
 */
#[CoversClass(Php::class)]
class PhpTest extends CompactorTestCase
{
    #[DataProvider('filesProvider')]
    public function test_it_supports_php_files(string $file, bool $supports): void
    {
        $compactor = new Php(
            new DocblockAnnotationParser(
                DocBlockFactory::createInstance(),
                new CompactedFormatter(),
                [],
            ),
        );

        $contents = <<<'PHP_WRAP'
            <?php


            // PHP file with a lot of spaces

            $x = '';


            PHP_WRAP;
        $actual = $compactor->compact($file, $contents);

        self::assertSame($supports, $contents !== $actual);
    }

    #[DataProvider('phpContentProvider')]
    public function test_it_compacts_php_files(
        DocblockAnnotationParser $annotationParser,
        string $content,
        string $expected
    ): void {
        $file = 'foo.php';

        $actual = (new Php($annotationParser))->compact($file, $content);
        // We are not interested in different trailing spaces
        $actual = DisplayNormalizer::removeTrailingSpaces($actual);

        self::assertSame($expected, $actual);
    }

    public static function compactorProvider(): iterable
    {
        yield 'empty' => [
            new Php(
                new DocblockAnnotationParser(
                    DocBlockFactory::createInstance(),
                    new CompactedFormatter(),
                    [],
                ),
            ),
        ];

        yield 'nominal' => [
            new Php(
                new DocblockAnnotationParser(
                    DocBlockFactory::createInstance(),
                    new CompactedFormatter(),
                    ['@author'],
                ),
            ),
        ];
    }

    public static function filesProvider(): iterable
    {
        yield 'no extension' => ['test', false];

        yield 'PHP file' => ['test.php', true];
    }

    public static function phpContentProvider(): iterable
    {
        $regularAnnotationParser = new DocblockAnnotationParser(
            DocBlockFactory::createInstance(),
            new CompactedFormatter(),
            [],
        );

        yield 'simple PHP file with comments' => [
            $regularAnnotationParser,
            <<<'PHP'
                <?php

                /**
                 * A comment.
                 */
                class AClass
                {
                    /**
                     * A comment.
                     */
                    public function aMethod()
                    {
                        \$test = true;
                    }

                    // Inline comment.
                    public function bMethod()
                    {
                        \$test = true; // Inline comment.
                    }

                    # Inline comment.
                    public function cMethod()
                    {
                        \$test = true; # Inline comment.


                    }

                    /* Trailing comment */
                    public function dMethod()
                    {
                        \$test = true; /* Trailing comment */
                    }
                }
                PHP,
            <<<'PHP'
                <?php




                class AClass
                {



                public function aMethod()
                {
                \$test = true;
                }


                public function bMethod()
                {
                \$test = true;
                }


                public function cMethod()
                {
                \$test = true;


                }


                public function dMethod()
                {
                \$test = true;
                }
                }
                PHP,
        ];

        yield 'PHP file with annotations' => [
            new DocblockAnnotationParser(
                DocBlockFactory::createInstance(),
                new CompactedFormatter(),
                ['ignored'],
            ),
            <<<'PHP'
                <?php

                /**
                 * This is an example entity class.
                 *
                 * @Entity()
                 * @Table(name="test")
                 */
                class Test
                {
                    /**
                     * The unique identifier.
                     *
                     * @ORM\Column(type="integer")
                     * @ORM\GeneratedValue()
                     * @ORM\Id()
                     */
                    private \$id;

                    /**
                     * A foreign key.
                     *
                     * @ORM\ManyToMany(targetEntity="SomethingElse")
                     * @ORM\JoinTable(
                     *     name="aJoinTable",
                     *     joinColumns={
                     *         @ORM\JoinColumn(name="joined",referencedColumnName="foreign")
                     *     },
                     *     inverseJoinColumns={
                     *         @ORM\JoinColumn(name="foreign",referencedColumnName="joined")
                     *     }
                     * )
                     */
                    private \$foreign;

                    /**
                     * @ignored
                     */
                    private \$none;
                }
                PHP,
            <<<'PHP'
                <?php

                /**
                @Entity()
                @Table(name="test")


                */
                class Test
                {
                /**
                @ORM\Column(type="integer")
                @ORM\GeneratedValue()
                @ORM\Id()


                */
                private \$id;

                /**
                @ORM\ManyToMany(targetEntity="SomethingElse")
                @ORM\JoinTable(name="aJoinTable",joinColumns={@ORM\JoinColumn(name="joined",referencedColumnName="foreign")},inverseJoinColumns={@ORM\JoinColumn(name="foreign",referencedColumnName="joined")})










                */
                private \$foreign;




                private \$none;
                }
                PHP,
        ];

        yield 'legacy issue 14' => [
            new DocblockAnnotationParser(
                DocBlockFactory::createInstance(),
                new CompactedFormatter(),
                ['author', 'inline'],
            ),
            <<<'PHP'
                <?php

                // autoload_real.php @generated by Composer

                /**
                 * @author Made Up <author@web.com>
                 */
                class ComposerAutoloaderInitc22fe6e3e5ad79bad24655b3e52999df
                {
                    private static \$loader;

                    /** @inline annotation */
                    public static function loadClassLoader(\$class)
                    {
                        if ('Composer\Autoload\ClassLoader' === \$class) {
                            require __DIR__ . '/ClassLoader.php';
                        }
                    }

                    public static function getLoader()
                    {
                        if (null !== self::\$loader) {
                            return self::\$loader;
                        }

                        spl_autoload_register(array('ComposerAutoloaderInitc22fe6e3e5ad79bad24655b3e52999df', 'loadClassLoader'), true, true);
                        self::\$loader = \$loader = new \Composer\Autoload\ClassLoader();
                        spl_autoload_unregister(array('ComposerAutoloaderInitc22fe6e3e5ad79bad24655b3e52999df', 'loadClassLoader'));

                        \$vendorDir = dirname(__DIR__);
                        \$baseDir = dirname(\$vendorDir);

                        \$includePaths = require __DIR__ . '/include_paths.php';
                        array_push(\$includePaths, get_include_path());
                        set_include_path(join(PATH_SEPARATOR, \$includePaths));

                        \$map = require __DIR__ . '/autoload_namespaces.php';
                        foreach (\$map as \$namespace => \$path) {
                            \$loader->set(\$namespace, \$path);
                        }

                        \$map = require __DIR__ . '/autoload_psr4.php';
                        foreach (\$map as \$namespace => \$path) {
                            \$loader->setPsr4(\$namespace, \$path);
                        }

                        \$classMap = require __DIR__ . '/autoload_classmap.php';
                        if (\$classMap) {
                            \$loader->addClassMap(\$classMap);
                        }

                        \$loader->register(true);

                        return \$loader;
                    }
                        }

                PHP,
            <<<'PHP'
                <?php






                class ComposerAutoloaderInitc22fe6e3e5ad79bad24655b3e52999df
                {
                private static \$loader;


                public static function loadClassLoader(\$class)
                {
                if ('Composer\Autoload\ClassLoader' === \$class) {
                require __DIR__ . '/ClassLoader.php';
                }
                }

                public static function getLoader()
                {
                if (null !== self::\$loader) {
                return self::\$loader;
                }

                spl_autoload_register(array('ComposerAutoloaderInitc22fe6e3e5ad79bad24655b3e52999df', 'loadClassLoader'), true, true);
                self::\$loader = \$loader = new \Composer\Autoload\ClassLoader();
                spl_autoload_unregister(array('ComposerAutoloaderInitc22fe6e3e5ad79bad24655b3e52999df', 'loadClassLoader'));

                \$vendorDir = dirname(__DIR__);
                \$baseDir = dirname(\$vendorDir);

                \$includePaths = require __DIR__ . '/include_paths.php';
                array_push(\$includePaths, get_include_path());
                set_include_path(join(PATH_SEPARATOR, \$includePaths));

                \$map = require __DIR__ . '/autoload_namespaces.php';
                foreach (\$map as \$namespace => \$path) {
                \$loader->set(\$namespace, \$path);
                }

                \$map = require __DIR__ . '/autoload_psr4.php';
                foreach (\$map as \$namespace => \$path) {
                \$loader->setPsr4(\$namespace, \$path);
                }

                \$classMap = require __DIR__ . '/autoload_classmap.php';
                if (\$classMap) {
                \$loader->addClassMap(\$classMap);
                }

                \$loader->register(true);

                return \$loader;
                }
                }

                PHP,
        ];

        yield 'Invalid PHP file' => [
            $regularAnnotationParser,
            '<ph',
            '<ph',
        ];

        yield 'Invalid annotation with ignored param' => [
            $regularAnnotationParser,
            <<<'PHP'
                <?php

                /**
                 * @param (string|stdClass $x
                 */
                function foo($x) {
                }
                PHP,
            <<<'PHP'
                <?php

                /**
                @param (string|stdClass $x
                */
                function foo($x) {
                }
                PHP,
        ];

        yield 'Invalid annotation' => [
            $regularAnnotationParser,
            <<<'PHP'
                <?php

                /**
                 * comment
                 *
                 * @a({@:1})
                 */
                function foo($x) {
                }
                PHP,
            <<<'PHP'
                <?php

                /**
                @a({@:1})


                */
                function foo($x) {
                }
                PHP,
        ];

        yield 'Simple single line PHP 8.0 attribute' => [
            $regularAnnotationParser,
            <<<'PHP'
                <?php

                class MyJson implements JsonSerializable {
                    // This method has an attribute.
                    #[\ReturnTypeWillChange]
                    public jsonSerialize() {}
                }
                PHP,
            <<<'PHP'
                <?php

                class MyJson implements JsonSerializable {

                #[\ReturnTypeWillChange]
                public jsonSerialize() {}
                }
                PHP,
        ];

        yield 'Simple multi-line PHP 8.0 attribute' => [
            $regularAnnotationParser,
            <<<'PHP'
                <?php

                class MyJson implements JsonSerializable {
                    #[
                        \ReturnTypeWillChange
                    ]
                    public jsonSerialize() {}
                }
                PHP,
            <<<'PHP'
                <?php

                class MyJson implements JsonSerializable {
                #[
                \ReturnTypeWillChange
                ]
                public jsonSerialize() {}
                }
                PHP,
        ];

        yield 'Single line PHP 8.0 attribute containing short array' => [
            $regularAnnotationParser,
            <<<'PHP'
                <?php

                #[AttributeWithParams('foo', bar: ['bar' => 'foobar'])]
                function foo() {}
                PHP,
            <<<'PHP'
                <?php

                #[AttributeWithParams('foo', bar: ['bar' => 'foobar'])]
                function foo() {}
                PHP,
        ];

        yield 'Single line containing two separate PHP 8.0 attributes' => [
            $regularAnnotationParser,
            <<<'PHP'
                <?php

                #[CustomAttribute] #[AttributeWithParams('foo')]
                function foo() {}
                PHP,
            <<<'PHP'
                <?php

                #[CustomAttribute] #[AttributeWithParams('foo')]
                function foo() {}
                PHP,
        ];

        yield 'Single line PHP 8.0 attribute followed by a comment' => [
            $regularAnnotationParser,
            <<<'PHP'
                <?php

                #[CustomAttribute] // This is a comment
                function foo() {}
                PHP,
            <<<'PHP'
                <?php

                #[CustomAttribute]
                function foo() {}
                PHP,
        ];

        yield 'Single line PHP 8.0 attribute group' => [
            $regularAnnotationParser,
            <<<'PHP'
                <?php

                #[CustomAttribute, AttributeWithParams('foo'), AttributeWithParams('foo', bar: ['bar' => 'foobar'])]
                function foo() {}
                PHP,
            <<<'PHP'
                <?php

                #[CustomAttribute, AttributeWithParams('foo'), AttributeWithParams('foo', bar: ['bar' => 'foobar'])]
                function foo() {}
                PHP,
        ];

        yield 'Multi-line PHP 8.0 attribute containing short array and inline comments' => [
            $regularAnnotationParser,
            <<<'PHP'
                <?php

                #[
                    CustomAttribute,                // comment
                    AttributeWithParams(/* another comment */ 'foo'),
                    AttributeWithParams('foo', bar: ['bar' => 'foobar'])
                ]
                function foo() {}
                PHP,
            <<<'PHP'
                <?php

                #[
                CustomAttribute,
                AttributeWithParams( 'foo'),
                AttributeWithParams('foo', bar: ['bar' => 'foobar'])
                ]
                function foo() {}
                PHP,
        ];

        yield 'Inline parameter attribute group followed by another attribute' => [
            $regularAnnotationParser,
            <<<'PHP'
                <?php

                function foo(#[ParamAttribute, AttributeWithParams(/* comment */ 'foo')] int $param, #[ParamAttr] $more) {}
                PHP,
            <<<'PHP'
                <?php

                function foo(#[ParamAttribute, AttributeWithParams( 'foo')] int $param, #[ParamAttr] $more) {}
                PHP,
        ];

        yield 'Multi-line PHP 8.0 attribute for parameter' => [
            $regularAnnotationParser,
            <<<'PHP'
                <?php

                function foo(#[
                    AttributeWithParams(
                        'foo'
                    )
                                                                ] int $param) {}
                PHP,
            <<<'PHP'
                <?php

                function foo(#[
                AttributeWithParams(
                'foo'
                )
                ] int $param) {}
                PHP,
        ];

        yield 'Single line PHP 8.0 attribute containing text looking like a PHP close tag' => [
            $regularAnnotationParser,
            <<<'PHP'
                <?php

                #[DeprecationReason('reason: <https://some-website/reason?>')]
                function foo() {}
                PHP,
            <<<'PHP'
                <?php

                #[DeprecationReason('reason: <https://some-website/reason?>')]
                function foo() {}
                PHP,
        ];

        yield 'Multi-line PHP 8.0 attribute containing text looking like a PHP close tag' => [
            $regularAnnotationParser,
            <<<'PHP'
                <?php

                #[DeprecationReason(
                    'reason: <https://some-website/reason?>'
                )]
                function foo() {}
                PHP,
            <<<'PHP'
                <?php

                #[DeprecationReason(
                'reason: <https://some-website/reason?>'
                )]
                function foo() {}
                PHP,
        ];
    }

    #[DataProvider('phpContentWithAnnotationsDisabledProvider')]
    public function test_it_does_not_touch_the_doc_blocks_if_disabled(string $content, string $expected): void
    {
        $file = 'foo.php';

        $actual = (new Php(null))->compact($file, $content);
        // We are not interested in different trailing spaces
        $actual = DisplayNormalizer::removeTrailingSpaces($actual);

        self::assertSame($expected, $actual);
    }

    public static function phpContentWithAnnotationsDisabledProvider(): iterable
    {
        yield 'simple PHP file with comments' => [
            <<<'PHP'
                <?php

                /**
                 * A comment.
                 */
                class AClass
                {
                    /**
                     * A comment.
                     */
                    public function aMethod()
                    {
                        \$test = true;
                    }

                    // Inline comment.
                    public function bMethod()
                    {
                        \$test = true; // Inline comment.
                    }

                    # Inline comment.
                    public function cMethod()
                    {
                        \$test = true; # Inline comment.


                    }

                    /* Trailing comment */
                    public function dMethod()
                    {
                        \$test = true; /* Trailing comment */
                    }
                }
                PHP,
            <<<'PHP'
                <?php




                class AClass
                {



                public function aMethod()
                {
                \$test = true;
                }


                public function bMethod()
                {
                \$test = true;
                }


                public function cMethod()
                {
                \$test = true;


                }


                public function dMethod()
                {
                \$test = true;
                }
                }
                PHP,
        ];

        yield 'PHP file with annotations' => [
            <<<'PHP'
                <?php

                /**
                 * This is an example entity class.
                 *
                 * @Entity()
                 * @Table(name="test")
                 */
                class Test
                {
                    /**
                     * The unique identifier.
                     *
                     * @ORM\Column(type="integer")
                     * @ORM\GeneratedValue()
                     * @ORM\Id()
                     */
                    private \$id;

                    /**
                     * A foreign key.
                     *
                     * @ORM\ManyToMany(targetEntity="SomethingElse")
                     * @ORM\JoinTable(
                     *     name="aJoinTable",
                     *     joinColumns={
                     *         @ORM\JoinColumn(name="joined",referencedColumnName="foreign")
                     *     },
                     *     inverseJoinColumns={
                     *         @ORM\JoinColumn(name="foreign",referencedColumnName="joined")
                     *     }
                     * )
                     */
                    private \$foreign;

                    /**
                     * @ignored
                     */
                    private \$none;
                }
                PHP,
            <<<'PHP'
                <?php

                /**
                 * This is an example entity class.
                 *
                 * @Entity()
                 * @Table(name="test")
                 */
                class Test
                {
                /**
                     * The unique identifier.
                     *
                     * @ORM\Column(type="integer")
                     * @ORM\GeneratedValue()
                     * @ORM\Id()
                     */
                private \$id;

                /**
                     * A foreign key.
                     *
                     * @ORM\ManyToMany(targetEntity="SomethingElse")
                     * @ORM\JoinTable(
                     *     name="aJoinTable",
                     *     joinColumns={
                     *         @ORM\JoinColumn(name="joined",referencedColumnName="foreign")
                     *     },
                     *     inverseJoinColumns={
                     *         @ORM\JoinColumn(name="foreign",referencedColumnName="joined")
                     *     }
                     * )
                     */
                private \$foreign;

                /**
                     * @ignored
                     */
                private \$none;
                }
                PHP,
        ];

        yield 'Invalid PHP file' => [
            '<ph',
            '<ph',
        ];

        yield 'Invalid annotation with ignored param' => [
            <<<'PHP'
                <?php

                /**
                 * @param (string|stdClass $x
                 */
                function foo($x) {
                }
                PHP,
            <<<'PHP'
                <?php

                /**
                 * @param (string|stdClass $x
                 */
                function foo($x) {
                }
                PHP,
        ];

        yield 'Invalid annotation' => [
            <<<'PHP'
                <?php

                /**
                 * comment
                 *
                 * @a({@:1})
                 */
                function foo($x) {
                }
                PHP,
            <<<'PHP'
                <?php

                /**
                 * comment
                 *
                 * @a({@:1})
                 */
                function foo($x) {
                }
                PHP,
        ];

        yield 'Simple single line PHP 8.0 attribute' => [
            <<<'PHP'
                <?php

                class MyJson implements JsonSerializable {
                    // This method has an attribute.
                    #[\ReturnTypeWillChange]
                    public jsonSerialize() {}
                }
                PHP,
            <<<'PHP'
                <?php

                class MyJson implements JsonSerializable {

                #[\ReturnTypeWillChange]
                public jsonSerialize() {}
                }
                PHP,
        ];

        yield 'Simple multi-line PHP 8.0 attribute' => [
            <<<'PHP'
                <?php

                class MyJson implements JsonSerializable {
                    #[
                        \ReturnTypeWillChange
                    ]
                    public jsonSerialize() {}
                }
                PHP,
            <<<'PHP'
                <?php

                class MyJson implements JsonSerializable {
                #[
                \ReturnTypeWillChange
                ]
                public jsonSerialize() {}
                }
                PHP,
        ];

        yield 'Single line PHP 8.0 attribute containing short array' => [
            <<<'PHP'
                <?php

                #[AttributeWithParams('foo', bar: ['bar' => 'foobar'])]
                function foo() {}
                PHP,
            <<<'PHP'
                <?php

                #[AttributeWithParams('foo', bar: ['bar' => 'foobar'])]
                function foo() {}
                PHP,
        ];

        yield 'Single line containing two separate PHP 8.0 attributes' => [
            <<<'PHP'
                <?php

                #[CustomAttribute] #[AttributeWithParams('foo')]
                function foo() {}
                PHP,
            <<<'PHP'
                <?php

                #[CustomAttribute] #[AttributeWithParams('foo')]
                function foo() {}
                PHP,
        ];

        yield 'Single line PHP 8.0 attribute followed by a comment' => [
            <<<'PHP'
                <?php

                #[CustomAttribute] // This is a comment
                function foo() {}
                PHP,
            <<<'PHP'
                <?php

                #[CustomAttribute]
                function foo() {}
                PHP,
        ];

        yield 'Single line PHP 8.0 attribute group' => [
            <<<'PHP'
                <?php

                #[CustomAttribute, AttributeWithParams('foo'), AttributeWithParams('foo', bar: ['bar' => 'foobar'])]
                function foo() {}
                PHP,
            <<<'PHP'
                <?php

                #[CustomAttribute, AttributeWithParams('foo'), AttributeWithParams('foo', bar: ['bar' => 'foobar'])]
                function foo() {}
                PHP,
        ];

        yield 'Multi-line PHP 8.0 attribute containing short array and inline comments' => [
            <<<'PHP'
                <?php

                #[
                    CustomAttribute,                // comment
                    AttributeWithParams(/* another comment */ 'foo'),
                    AttributeWithParams('foo', bar: ['bar' => 'foobar'])
                ]
                function foo() {}
                PHP,
            <<<'PHP'
                <?php

                #[
                CustomAttribute,
                AttributeWithParams( 'foo'),
                AttributeWithParams('foo', bar: ['bar' => 'foobar'])
                ]
                function foo() {}
                PHP,
        ];

        yield 'Inline parameter attribute group followed by another attribute' => [
            <<<'PHP'
                <?php

                function foo(#[ParamAttribute, AttributeWithParams(/* comment */ 'foo')] int $param, #[ParamAttr] $more) {}
                PHP,
            <<<'PHP'
                <?php

                function foo(#[ParamAttribute, AttributeWithParams( 'foo')] int $param, #[ParamAttr] $more) {}
                PHP,
        ];

        yield 'Multi-line PHP 8.0 attribute for parameter' => [
            <<<'PHP'
                <?php

                function foo(#[
                    AttributeWithParams(
                        'foo'
                    )
                                                                ] int $param) {}
                PHP,
            <<<'PHP'
                <?php

                function foo(#[
                AttributeWithParams(
                'foo'
                )
                ] int $param) {}
                PHP,
        ];

        yield 'Single line PHP 8.0 attribute containing text looking like a PHP close tag' => [
            <<<'PHP'
                <?php

                #[DeprecationReason('reason: <https://some-website/reason?>')]
                function foo() {}
                PHP,
            <<<'PHP'
                <?php

                #[DeprecationReason('reason: <https://some-website/reason?>')]
                function foo() {}
                PHP,
        ];

        yield 'Multi-line PHP 8.0 attribute containing text looking like a PHP close tag' => [
            <<<'PHP'
                <?php

                #[DeprecationReason(
                    'reason: <https://some-website/reason?>'
                )]
                function foo() {}
                PHP,
            <<<'PHP'
                <?php

                #[DeprecationReason(
                'reason: <https://some-website/reason?>'
                )]
                function foo() {}
                PHP,
        ];
    }
}
