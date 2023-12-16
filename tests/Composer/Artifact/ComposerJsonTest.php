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

namespace KevinGH\Box\Composer\Artifact;

use KevinGH\Box\Composer\Package\ExtensionsAssertion;
use KevinGH\Box\Composer\Package\RequiredItem;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use function json_decode;

/**
 * @internal
 */
#[CoversClass(ComposerJson::class)]
class ComposerJsonTest extends TestCase
{
    #[DataProvider('composerJsonProvider')]
    public function test_it_can_interpret_a_decoded_composer_json_file(
        string $composerJsonContents,
        ?string $expectedRequiredPhpVersion,
        bool $expectedHasRequiredPhpVersion,
        array $expectedRequiredItems,
        array $expectedConflictingExtensions,
    ): void {
        $actual = self::createComposerJsonFromContents($composerJsonContents);

        self::assertStateIs(
            $actual,
            $expectedRequiredPhpVersion,
            $expectedHasRequiredPhpVersion,
            $expectedRequiredItems,
            $expectedConflictingExtensions,
        );
    }

    public static function composerJsonProvider(): iterable
    {
        yield 'empty json file' => [
            '{}',
            null,
            false,
            [],
            [],
        ];

        yield 'PHP platform requirements' => [
            <<<'JSON'
                {
                    "require": {
                        "php": "^7.1",
                        "ext-phar": "*"
                    },
                    "require-dev": []
                }
                JSON,
            '^7.1',
            true,
            [
                new RequiredItem(['php' => '^7.1']),
                new RequiredItem(['ext-phar' => '*']),
            ],
            [],
        ];

        yield 'PHP platform-dev requirements' => [
            <<<'JSON'
                {
                    "require": [],
                    "require-dev": {
                        "ext-mbstring": "*",
                        "php": ">=5.3"
                    }
                }
                JSON,
            null,
            false,
            [],
            [],
        ];

        yield 'packages required' => [
            <<<'JSON'
                {
                    "require": {
                        "beberlei/assert": "^2.9",
                        "composer/ca-bundle": "^1.1"
                    },
                    "require-dev": {
                        "webmozarts/assert": "^3.2"
                    }
                }
                JSON,
            null,
            false,
            [
                new RequiredItem(['beberlei/assert' => '^2.9']),
                new RequiredItem(['composer/ca-bundle' => '^1.1']),
            ],
            [],
        ];

        yield 'conflicts' => [
            <<<'JSON'
                {
                    "conflict": {
                        "psr/logger": ">=7.1",
                        "ext-phar": "*"
                    }
                }
                JSON,
            null,
            false,
            [],
            ['phar'],
        ];

        yield 'nominal' => [
            <<<'JSON'
                {
                    "require": {
                        "php": "^7.3",
                        "beberlei/assert": "^2.9",
                        "ext-http": "*",
                        "composer/ca-bundle": "^1.1"
                    },
                    "require-dev": {
                        "webmozarts/assert": "^3.2"
                    },
                    "conflict": {
                        "ext-http": "*"
                    }
                }
                JSON,
            '^7.3',
            true,
            [
                new RequiredItem(['php' => '^7.3']),
                new RequiredItem(['beberlei/assert' => '^2.9']),
                new RequiredItem(['ext-http' => '*']),
                new RequiredItem(['composer/ca-bundle' => '^1.1']),
            ],
            ['http'],
        ];
    }

    #[DataProvider('binProvider')]
    public function test_it_can_give_the_first_bin_file(
        string $composerJsonContents,
        ?string $expected,
    ): void {
        $composerJson = self::createComposerJsonFromContents($composerJsonContents);

        $actual = $composerJson->getFirstBin();

        self::assertSame($expected, $actual);
    }

    public static function binProvider(): iterable
    {
        yield 'empty' => [
            '{}',
            null,
        ];

        yield 'empty bin' => [
            <<<'JSON'
                {
                    "bin": null
                }
                JSON,
            null,
        ];

        yield 'single bin' => [
            <<<'JSON'
                {
                    "bin": "bin/app.php"
                }
                JSON,
            'bin/app.php',
        ];

        yield 'single bin in set' => [
            <<<'JSON'
                {
                    "bin": [
                        "bin/app.php"
                    ]
                }
                JSON,
            'bin/app.php',
        ];

        yield 'multiple bins' => [
            <<<'JSON'
                {
                    "bin": [
                        "bin/app-first.php",
                        "bin/app-second.php"
                    ]
                }
                JSON,
            'bin/app-first.php',
        ];
    }

    #[DataProvider('autoloadProvider')]
    public function test_it_can_give_the_autoload_file_paths(
        string $composerJsonContents,
        array $expected,
    ): void {
        $composerJson = self::createComposerJsonFromContents($composerJsonContents);

        $actual = $composerJson->getAutoloadPaths();

        self::assertSame($expected, $actual);
    }

    public static function autoloadProvider(): iterable
    {
        yield 'empty' => [
            '{}',
            [],
        ];

        yield 'empty autoload' => [
            <<<'JSON'
                {
                    "autoload": {}
                }
                JSON,
            [],
        ];

        yield 'PSR-4 autoload' => [
            <<<'JSON'
                {
                    "autoload": {
                        "psr-4": {
                            "Monolog\\": "src/",
                            "Vendor\\Namespace\\": ""
                        }
                    }
                }
                JSON,
            [
                'src/',
                '',
            ],
        ];

        yield 'PSR-4 autoload directories' => [
            <<<'JSON'
                {
                     "autoload": {
                        "psr-4": { "Monolog\\": ["src/", "lib/"] }
                    }
                }
                JSON,
            [
                'src/',
                'lib/',
            ],
        ];

        yield 'PSR-0' => [
            <<<'JSON'
                {
                     "autoload": {
                        "psr-0": {
                            "Monolog\\": "src/",
                            "Vendor\\Namespace\\": "src/",
                            "Vendor_Namespace_": "src/"
                        }
                    }
                }
                JSON,
            [
                'src/',
                'src/',
                'src/',
            ],
        ];

        yield 'PSR-0 directories' => [
            <<<'JSON'
                {
                     "autoload": {
                        "psr-0": { "Monolog\\": ["src/", "lib/"] }
                    }
                }
                JSON,
            [
                'src/',
                'lib/',
            ],
        ];

        yield 'PSR-0 global' => [
            <<<'JSON'
                {
                     "autoload": {
                        "psr-0": { "UniqueGlobalClass": "" }
                    }
                }
                JSON,
            [
                '',
            ],
        ];

        yield 'classmap' => [
            <<<'JSON'
                {
                    "autoload": {
                        "classmap": ["src/", "lib/", "Something.php"]
                    }
                }
                JSON,
            [
                'src/',
                'lib/',
                'Something.php',
            ],
        ];
    }

    private static function createComposerJsonFromContents(string $contents): ComposerJson
    {
        return new ComposerJson(
            '',
            json_decode($contents, true),
        );
    }

    private static function assertStateIs(
        ComposerJson $composerJson,
        ?string $expectedRequiredPhpVersion,
        bool $expectedHasRequiredPhpVersion,
        array $expectedRequiredItems,
        array $expectedConflictingExtensions,
    ): void {
        self::assertSame($expectedRequiredPhpVersion, $composerJson->getRequiredPhpVersion());
        self::assertSame($expectedHasRequiredPhpVersion, $composerJson->hasRequiredPhpVersion());
        self::assertEquals($expectedRequiredItems, $composerJson->getRequiredItems());
        ExtensionsAssertion::assertEqual($expectedConflictingExtensions, $composerJson->getConflictingExtensions());
    }
}
