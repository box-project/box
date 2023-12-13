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

namespace KevinGH\Box\Composer\Package;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use function json_decode;

/**
 * @internal
 */
#[CoversClass(PackageInfo::class)]
final class PackageInfoTest extends TestCase
{
    #[DataProvider('packageInfoProvider')]
    public function test_it_can_parse_the_decoded_data(
        array $rawPackageInfo,
        string $expectedName,
        ?string $expectedRequiredPhpVersion,
        bool $expectedHasRequiredPhpVersion,
        array $expectedRequiredExtensions,
        array $expectedPolyfilledExtensions,
        array $expectedConflictingExtensions,
    ): void {
        $packageInfo = new PackageInfo($rawPackageInfo);

        self::assertStateIs(
            $packageInfo,
            $expectedName,
            $expectedRequiredPhpVersion,
            $expectedHasRequiredPhpVersion,
            $expectedRequiredExtensions,
            $expectedPolyfilledExtensions,
            $expectedConflictingExtensions,
        );
    }

    public static function packageInfoProvider(): iterable
    {
        yield 'minimal' => [
            [
                'name' => 'box/test',
            ],
            'box/test',
            null,
            false,
            [],
            [],
            [],
        ];

        yield 'has a PHP version required' => [
            [
                'name' => 'box/test',
                'require' => [
                    'php' => '^8.2',
                ],
            ],
            'box/test',
            '^8.2',
            true,
            [],
            [],
            [],
        ];

        yield 'has a PHP version required as a dev dep' => [
            [
                'name' => 'box/test',
                'require-dev' => [
                    'php' => '^8.2',
                ],
            ],
            'box/test',
            null,
            false,
            [],
            [],
            [],
        ];

        yield 'has PHP extensions required' => [
            [
                'name' => 'box/test',
                'require' => [
                    'ext-json' => '*',
                    'ext-phar' => '*',
                    'ext-xdebug' => '3.0',
                    'ext-zend-opcache' => '*',
                ],
                'require-dev' => [
                    'ext-http' => '*',
                ],
            ],
            'box/test',
            null,
            false,
            [
                'json',
                'phar',
                'xdebug',
                'zend opcache',
            ],
            [],
            [],
        ];

        yield 'polyfills extensions' => [
            [
                'name' => 'box/test',
                'provide' => [
                    'ext-mbstring' => '*',
                    'ext-ctype' => '*',
                    'psr/log-implementation' => '1.0|2.0|3.0',
                ],
            ],
            'box/test',
            null,
            false,
            [],
            [
                'mbstring',
                'ctype',
            ],
            [],
        ];

        yield 'Symfony mbstring polyfill' => [
            [
                'name' => 'symfony/polyfill-mbstring',
            ],
            'symfony/polyfill-mbstring',
            null,
            false,
            [],
            ['mbstring'],
            [],
        ];

        yield 'Symfony PHP polyfill' => [
            [
                'name' => 'symfony/polyfill-php72',
            ],
            'symfony/polyfill-php72',
            null,
            false,
            [],
            [],
            [],
        ];

        yield 'phpseclib/mcrypt_compat' => [
            [
                'name' => 'phpseclib/mcrypt_compat',
            ],
            'phpseclib/mcrypt_compat',
            null,
            false,
            [],
            ['mcrypt'],
            [],
        ];

        yield 'package with conflicts' => [
            [
                'name' => 'laminas/laminas-servicemanager',
                'conflict' => [
                    'ext-psr' => '*',
                    'ext-http' => '*',
                    'laminas/laminas-code' => '<3.3.1',
                    'zendframework/zend-code' => '<3.3.1',
                    'zendframework/zend-servicemanager' => '*',
                ],
            ],
            'laminas/laminas-servicemanager',
            null,
            false,
            [],
            [],
            ['psr', 'http'],
        ];

        yield 'nominal' => [
            json_decode(
                <<<'JSON'
                    {
                        "name": "amphp/amp",
                        "version": "v2.6.2",
                        "source": {
                            "type": "git",
                            "url": "https://github.com/amphp/amp.git",
                            "reference": "9d5100cebffa729aaffecd3ad25dc5aeea4f13bb"
                        },
                        "dist": {
                            "type": "zip",
                            "url": "https://api.github.com/repos/amphp/amp/zipball/9d5100cebffa729aaffecd3ad25dc5aeea4f13bb",
                            "reference": "9d5100cebffa729aaffecd3ad25dc5aeea4f13bb",
                            "shasum": ""
                        },
                        "require": {
                            "php": ">=7.1"
                        },
                        "require-dev": {
                            "amphp/php-cs-fixer-config": "dev-master",
                            "amphp/phpunit-util": "^1",
                            "ext-json": "*",
                            "jetbrains/phpstorm-stubs": "^2019.3",
                            "phpunit/phpunit": "^7 | ^8 | ^9",
                            "psalm/phar": "^3.11@dev",
                            "react/promise": "^2"
                        },
                        "type": "library",
                        "extra": {
                            "branch-alias": {
                                "dev-master": "2.x-dev"
                            }
                        },
                        "autoload": {
                            "files": [
                                "lib/functions.php",
                                "lib/Internal/functions.php"
                            ],
                            "psr-4": {
                                "Amp\\": "lib"
                            }
                        },
                        "notification-url": "https://packagist.org/downloads/",
                        "license": [
                            "MIT"
                        ],
                        "authors": [
                            {
                                "name": "Daniel Lowrey",
                                "email": "rdlowrey@php.net"
                            },
                            {
                                "name": "Aaron Piotrowski",
                                "email": "aaron@trowski.com"
                            },
                            {
                                "name": "Bob Weinand",
                                "email": "bobwei9@hotmail.com"
                            },
                            {
                                "name": "Niklas Keller",
                                "email": "me@kelunik.com"
                            }
                        ],
                        "description": "A non-blocking concurrency framework for PHP applications.",
                        "homepage": "https://amphp.org/amp",
                        "keywords": [
                            "async",
                            "asynchronous",
                            "awaitable",
                            "concurrency",
                            "event",
                            "event-loop",
                            "future",
                            "non-blocking",
                            "promise"
                        ],
                        "support": {
                            "irc": "irc://irc.freenode.org/amphp",
                            "issues": "https://github.com/amphp/amp/issues",
                            "source": "https://github.com/amphp/amp/tree/v2.6.2"
                        },
                        "funding": [
                            {
                                "url": "https://github.com/amphp",
                                "type": "github"
                            }
                        ],
                        "time": "2022-02-20T17:52:18+00:00"
                    }
                    JSON,
                true,
            ),
            'amphp/amp',
            '>=7.1',
            true,
            [],
            [],
            [],
        ];
    }

    private static function assertStateIs(
        PackageInfo $actual,
        string $expectedName,
        ?string $expectedRequiredPhpVersion,
        bool $expectedHasRequiredPhpVersion,
        array $expectedRequiredExtensions,
        array $expectedPolyfilledExtensions,
        array $expectedConflictingExtensions,
    ): void {
        self::assertSame($expectedName, $actual->getName());
        self::assertSame($expectedRequiredPhpVersion, $actual->getRequiredPhpVersion());
        self::assertSame($expectedHasRequiredPhpVersion, $actual->hasRequiredPhpVersion());
        ExtensionsAssertion::assertEqual($expectedRequiredExtensions, $actual->getRequiredExtensions());
        ExtensionsAssertion::assertEqual($expectedPolyfilledExtensions, $actual->getPolyfilledExtensions());
        ExtensionsAssertion::assertEqual($expectedConflictingExtensions, $actual->getConflictingExtensions());
    }
}
