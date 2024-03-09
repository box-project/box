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
use KevinGH\Box\Composer\Package\PackageInfo;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use function json_decode;

/**
 * @internal
 */
#[CoversClass(ComposerLock::class)]
class ComposerLockTest extends TestCase
{
    #[DataProvider('composerLockProvider')]
    public function test_it_can_interpret_a_decoded_composer_json_file(
        string $composerJsonContents,
        bool $expectedIsEmpty,
        ?string $expectedRequiredPhpVersion,
        bool $expectedHasRequiredPhpVersion,
        array $expectedPlatformExtensions,
        array $expectedPackages,
    ): void {
        $actual = new ComposerLock(
            '',
            json_decode($composerJsonContents, true),
        );

        self::assertStateIs(
            $actual,
            $expectedIsEmpty,
            $expectedRequiredPhpVersion,
            $expectedHasRequiredPhpVersion,
            $expectedPlatformExtensions,
            $expectedPackages,
        );
    }

    public static function composerLockProvider(): iterable
    {
        yield 'empty json file' => [
            '{}',
            true,
            null,
            false,
            [],
            [],
        ];

        yield 'required PHP version by app' => [
            <<<'JSON'
                {
                    "platform": {
                        "php": "^7.1"
                    },
                    "platform-dev": []
                }
                JSON,
            false,
            '^7.1',
            true,
            [],
            [],
        ];

        yield 'required-dev PHP version by app' => [
            <<<'JSON'
                {
                    "platform-dev": {
                        "php": "^7.1"
                    }
                }
                JSON,
            false,
            null,
            false,
            [],
            [],
        ];

        yield 'extensions required by app' => [
            <<<'JSON'
                {
                    "platform": {
                        "ext-phar": "*",
                        "ext-http": "*"
                    },
                    "platform-dev": {
                        "ext-mbstring": "*"
                    }
                }
                JSON,
            false,
            null,
            false,
            ['phar', 'http'],
            [],
        ];

        yield 'required packages' => [
            <<<'JSON'
                {
                    "packages": [
                        {
                            "name": "beberlei/assert",
                            "version": "v2.9.2",
                            "require": {
                                "ext-mbstring": "*",
                                "php": ">=5.3"
                            },
                            "require-dev": []
                        },
                        {
                            "name": "composer/ca-bundle",
                            "version": "1.1.0",
                            "require": {
                                "ext-openssl": "*",
                                "ext-pcre": "*",
                                "php": "^5.3.2 || ^7.0"
                            },
                            "require-dev": {
                                "ext-pdo_sqlite3": "*"
                            }
                        }
                    ],
                    "packages-dev": [
                        {
                            "name": "acme/foo",
                            "version": "1.1.0",
                            "require": {
                                "ext-openssl": "*"
                            },
                            "require-dev": []
                        }
                    ]
                }
                JSON,
            false,
            null,
            false,
            [],
            [
                new PackageInfo([
                    'name' => 'beberlei/assert',
                    'version' => 'v2.9.2',
                    'require' => [
                        'ext-mbstring' => '*',
                        'php' => '>=5.3',
                    ],
                    'require-dev' => [],
                ]),
                new PackageInfo([
                    'name' => 'composer/ca-bundle',
                    'version' => '1.1.0',
                    'require' => [
                        'ext-openssl' => '*',
                        'ext-pcre' => '*',
                        'php' => '^5.3.2 || ^7.0',
                    ],
                    'require-dev' => [
                        'ext-pdo_sqlite3' => '*',
                    ],
                ]),
            ],
        ];
    }

    private static function assertStateIs(
        ComposerLock $composerLock,
        bool $expectedIsEmpty,
        ?string $expectedRequiredPhpVersion,
        bool $expectedHasRequiredPhpVersion,
        array $expectedPlatformExtensions,
        array $expectedPackages,
    ): void {
        self::assertSame($expectedIsEmpty, $composerLock->isEmpty());
        self::assertSame($expectedRequiredPhpVersion, $composerLock->getRequiredPhpVersion());
        self::assertSame($expectedHasRequiredPhpVersion, $composerLock->hasRequiredPhpVersion());
        ExtensionsAssertion::assertEqual($expectedPlatformExtensions, $composerLock->getPlatformExtensions());
        self::assertEquals($expectedPackages, $composerLock->getPackages());
    }
}
