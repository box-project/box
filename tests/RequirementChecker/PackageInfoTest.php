<?php

declare(strict_types=1);

namespace KevinGH\Box\RequirementChecker;

use KevinGH\Box\RequirementChecker\PackageInfo;
use PHPUnit\Framework\TestCase;
use function json_decode;

/**
 * @covers \KevinGH\Box\RequirementChecker\PackageInfo
 */
final class PackageInfoTest extends TestCase
{
    /**
     * @dataProvider packageInfoProvider
     */
    public function test_it_can_parse_the_decoded_data(
        array $rawPackageInfo,
        string $expectedName,
        ?string $expectedRequiredPhpVersion,
        bool $expectedHasRequiredPhpVersion,
        array $expectedRequiredExtensions,
        ?string $expectedPolyfilledExtension,
    ): void
    {
        $packageInfo = new PackageInfo($rawPackageInfo);

        self::assertStateIs(
            $packageInfo,
            $expectedName,
            $expectedRequiredPhpVersion,
            $expectedHasRequiredPhpVersion,
            $expectedRequiredExtensions,
            $expectedPolyfilledExtension,
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
            null,
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
            null,
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
            null,
        ];

        yield 'has PHP extensions required' => [
            [
                'name' => 'box/test',
                'require' => [
                    'ext-json' => '*',
                    'ext-phar' => '*',
                    'ext-xdebug' => '3.0',
                ],
                'require-dev' => [
                    'ext-zend-opcache' => '*',
                ],
            ],
            'box/test',
            null,
            false,
            [
                'json',
                'phar',
                'xdebug',
            ],
            null,
        ];

        // TODO: need to add support
        yield 'polyfills an extension' => [
            [
                'name' => 'box/test',
                'provide' => [
                    'mbstring' => '*',
                ],
            ],
            'box/test',
            null,
            false,
            [],
            null,
        ];

        yield 'Symfony mbstring polyfill' => [
            [
                'name' => 'symfony/polyfill-mbstring',
            ],
            'symfony/polyfill-mbstring',
            null,
            false,
            [],
            'mbstring',
        ];

        yield 'Symfony PHP polyfill' => [
            [
                'name' => 'symfony/polyfill-php72',
            ],
            'symfony/polyfill-php72',
            null,
            false,
            [],
            null,
        ];

        yield 'phpseclib/mcrypt_compat' => [
            [
                'name' => 'phpseclib/mcrypt_compat',
            ],
            'phpseclib/mcrypt_compat',
            null,
            false,
            [],
            'mcrypt',
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
            null,
        ];
    }

    private static function assertStateIs(
        PackageInfo $actual,
        string $expectedName,
        ?string $expectedRequiredPhpVersion,
        bool $expectedHasRequiredPhpVersion,
        array $expectedRequiredExtensions,
        ?string $expectedPolyfilledExtension,
    ): void
    {
        self::assertSame($expectedName, $actual->getName());
        self::assertSame($expectedRequiredPhpVersion, $actual->getRequiredPhpVersion());
        self::assertSame($expectedHasRequiredPhpVersion, $actual->hasRequiredPhpVersion());
        self::assertSame($expectedRequiredExtensions, $actual->getRequiredExtensions());
        self::assertSame($expectedPolyfilledExtension, $actual->getPolyfilledExtension());
    }
}
