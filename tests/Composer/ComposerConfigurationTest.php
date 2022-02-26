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

namespace KevinGH\Box\Composer;

use function json_decode;
use function KevinGH\Box\FileSystem\mkdir;
use KevinGH\Box\Test\FileSystemTestCase;

/**
 * @covers \KevinGH\Box\Composer\ComposerConfiguration
 */
class ComposerConfigurationTest extends FileSystemTestCase
{
    private const COMPOSER_LOCK_SAMPLE = <<<'JSON'
        {
            "_readme": [
                "This file locks the dependencies of your project to a known state",
                "Read more about it at https://getcomposer.org/doc/01-basic-usage.md#composer-lock-the-lock-file",
                "This file is @generated automatically"
            ],
            "content-hash": "c9ae998336c74a11e44be3255b6abceb",
            "packages": [
                {
                    "name": "amphp/amp",
                    "version": "v2.0.6",
                    "source": {
                        "type": "git",
                        "url": "https://github.com/amphp/amp.git",
                        "reference": "4a742beb59615f36ed998e2dc210e36576e44c44"
                    },
                    "dist": {
                        "type": "zip",
                        "url": "https://api.github.com/repos/amphp/amp/zipball/4a742beb59615f36ed998e2dc210e36576e44c44",
                        "reference": "4a742beb59615f36ed998e2dc210e36576e44c44",
                        "shasum": ""
                    },
                    "require": {
                        "php": ">=7"
                    },
                    "require-dev": {
                        "amphp/phpunit-util": "^1",
                        "friendsofphp/php-cs-fixer": "^2.3",
                        "phpstan/phpstan": "^0.8.5",
                        "phpunit/phpunit": "^6.0.9",
                        "react/promise": "^2"
                    },
                    "type": "library",
                    "extra": {
                        "branch-alias": {
                            "dev-master": "2.0.x-dev"
                        }
                    },
                    "autoload": {
                        "psr-4": {
                            "Amp\\": "lib"
                        },
                        "files": [
                            "lib/functions.php",
                            "lib/Internal/functions.php"
                        ]
                    },
                    "notification-url": "https://packagist.org/downloads/",
                    "license": [
                        "MIT"
                    ],
                    "authors": [
                        {
                            "name": "Bob Weinand",
                            "email": "bobwei9@hotmail.com"
                        },
                        {
                            "name": "Niklas Keller",
                            "email": "me@kelunik.com"
                        },
                        {
                            "name": "Daniel Lowrey",
                            "email": "rdlowrey@php.net"
                        },
                        {
                            "name": "Aaron Piotrowski",
                            "email": "aaron@trowski.com"
                        }
                    ],
                    "description": "A non-blocking concurrency framework for PHP applications.",
                    "homepage": "http://amphp.org/amp",
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
                    "time": "2018-01-27T19:18:05+00:00"
                }
            ],
            "packages-dev": [
                {
                    "name": "bamarni/composer-bin-plugin",
                    "version": "v1.2.0",
                    "source": {
                        "type": "git",
                        "url": "https://github.com/bamarni/composer-bin-plugin.git",
                        "reference": "62fef740245a85f00665e81ea8f0aa0b72afe6e7"
                    },
                    "dist": {
                        "type": "zip",
                        "url": "https://api.github.com/repos/bamarni/composer-bin-plugin/zipball/62fef740245a85f00665e81ea8f0aa0b72afe6e7",
                        "reference": "62fef740245a85f00665e81ea8f0aa0b72afe6e7",
                        "shasum": ""
                    },
                    "require": {
                        "composer-plugin-api": "^1.0"
                    },
                    "require-dev": {
                        "composer/composer": "dev-master",
                        "symfony/console": "^2.5 || ^3.0"
                    },
                    "type": "composer-plugin",
                    "extra": {
                        "class": "Bamarni\\Composer\\Bin\\Plugin",
                        "branch-alias": {
                            "dev-master": "1.1-dev"
                        }
                    },
                    "autoload": {
                        "psr-4": {
                            "Bamarni\\Composer\\Bin\\": "src"
                        }
                    },
                    "notification-url": "https://packagist.org/downloads/",
                    "license": [
                        "MIT"
                    ],
                    "time": "2017-09-11T13:13:58+00:00"
                },
                {
                    "name": "doctrine/instantiator",
                    "version": "1.1.0",
                    "source": {
                        "type": "git",
                        "url": "https://github.com/doctrine/instantiator.git",
                        "reference": "185b8868aa9bf7159f5f953ed5afb2d7fcdc3bda"
                    },
                    "dist": {
                        "type": "zip",
                        "url": "https://api.github.com/repos/doctrine/instantiator/zipball/185b8868aa9bf7159f5f953ed5afb2d7fcdc3bda",
                        "reference": "185b8868aa9bf7159f5f953ed5afb2d7fcdc3bda",
                        "shasum": ""
                    },
                    "require": {
                        "php": "^7.1"
                    },
                    "require-dev": {
                        "athletic/athletic": "~0.1.8",
                        "ext-pdo": "*",
                        "ext-phar": "*",
                        "phpunit/phpunit": "^6.2.3",
                        "squizlabs/php_codesniffer": "^3.0.2"
                    },
                    "type": "library",
                    "extra": {
                        "branch-alias": {
                            "dev-master": "1.2.x-dev"
                        }
                    },
                    "autoload": {
                        "psr-4": {
                            "Doctrine\\Instantiator\\": "src/Doctrine/Instantiator/"
                        }
                    },
                    "notification-url": "https://packagist.org/downloads/",
                    "license": [
                        "MIT"
                    ],
                    "authors": [
                        {
                            "name": "Marco Pivetta",
                            "email": "ocramius@gmail.com",
                            "homepage": "http://ocramius.github.com/"
                        }
                    ],
                    "description": "A small, lightweight utility to instantiate objects in PHP without invoking their constructors",
                    "homepage": "https://github.com/doctrine/instantiator",
                    "keywords": [
                        "constructor",
                        "instantiate"
                    ],
                    "time": "2017-07-22T11:58:36+00:00"
                }
            ],
            "aliases": [],
            "minimum-stability": "stable",
            "stability-flags": [],
            "prefer-stable": false,
            "prefer-lowest": false,
            "platform": {
                "php": "^7.1",
                "ext-phar": "*"
            },
            "platform-dev": []
        }
        JSON;

    /**
     * @dataProvider excludeDevFilesSettingProvider
     */
    public function test_it_returns_an_empty_list_when_trying_to_retrieve_the_list_of_dev_packages_when_no_composer_json_file_is_found(bool $excludeDevPackages): void
    {
        $this->assertSame(
            [],
            ComposerConfiguration::retrieveDevPackages(
                $this->tmp,
                null,
                null,
                $excludeDevPackages,
            ),
        );

        $this->assertSame(
            [],
            ComposerConfiguration::retrieveDevPackages(
                $this->tmp,
                [],
                null,
                $excludeDevPackages,
            ),
        );
    }

    /**
     * @dataProvider excludeDevFilesSettingProvider
     */
    public function test_it_can_retrieve_the_dev_packages_found_in_the_lock_file(): void
    {
        $decodedComposerJson = [];
        $decodedComposerLock = json_decode(self::COMPOSER_LOCK_SAMPLE, true);

        mkdir('vendor/bamarni/composer-bin-plugin');
        mkdir('vendor/doctrine/instantiator');

        $expected = [
            $this->tmp.'/vendor/bamarni/composer-bin-plugin',
            $this->tmp.'/vendor/doctrine/instantiator',
        ];

        $actual = ComposerConfiguration::retrieveDevPackages($this->tmp, $decodedComposerJson, $decodedComposerLock, true);

        $this->assertSame($expected, $actual);

        $this->assertSame(
            [],
            ComposerConfiguration::retrieveDevPackages(
                $this->tmp,
                $decodedComposerJson,
                $decodedComposerLock,
                false,
            ),
        );
    }

    public function test_it_can_retrieve_the_dev_packages_found_in_the_lock_file_2(): void
    {
        $decodedComposerJson = ['config' => []];
        $decodedComposerLock = json_decode(self::COMPOSER_LOCK_SAMPLE, true);

        mkdir('vendor/bamarni/composer-bin-plugin');
        mkdir('vendor/doctrine/instantiator');

        $expected = [
            $this->tmp.'/vendor/bamarni/composer-bin-plugin',
            $this->tmp.'/vendor/doctrine/instantiator',
        ];

        $actual = ComposerConfiguration::retrieveDevPackages($this->tmp, $decodedComposerJson, $decodedComposerLock, true);

        $this->assertSame($expected, $actual);

        $this->assertSame(
            [],
            ComposerConfiguration::retrieveDevPackages(
                $this->tmp,
                $decodedComposerJson,
                $decodedComposerLock,
                false,
            ),
        );
    }

    public function test_it_ignores_non_existent_dev_packages_found_in_the_lock_file(): void
    {
        $decodedComposerJson = [];
        $decodedComposerLock = json_decode(self::COMPOSER_LOCK_SAMPLE, true);

        mkdir('vendor/bamarni/composer-bin-plugin');
        // Doctrine Instantiator vendor does not exists

        $expected = [
            $this->tmp.'/vendor/bamarni/composer-bin-plugin',
        ];

        $actual = ComposerConfiguration::retrieveDevPackages($this->tmp, $decodedComposerJson, $decodedComposerLock, true);

        $this->assertSame($expected, $actual);

        $this->assertSame(
            [],
            ComposerConfiguration::retrieveDevPackages(
                $this->tmp,
                $decodedComposerJson,
                $decodedComposerLock,
                false,
            ),
        );
    }

    public function test_it_can_retrieve_the_dev_packages_found_in_the_lock_file_in_a_custom_vendor_directory(): void
    {
        $decodedComposerJson = [
            'config' => [
                'vendor-dir' => 'custom-vendor',
            ],
        ];
        $decodedComposerLock = json_decode(self::COMPOSER_LOCK_SAMPLE, true);

        mkdir('custom-vendor/bamarni/composer-bin-plugin');
        mkdir('vendor/doctrine/instantiator');  // Wrong directory

        $expected = [
            $this->tmp.'/custom-vendor/bamarni/composer-bin-plugin',
        ];

        $actual = ComposerConfiguration::retrieveDevPackages($this->tmp, $decodedComposerJson, $decodedComposerLock, true);

        $this->assertSame($expected, $actual);

        $this->assertSame(
            [],
            ComposerConfiguration::retrieveDevPackages(
                $this->tmp,
                $decodedComposerJson,
                $decodedComposerLock,
                false,
            ),
        );
    }

    public function test_it_can_retrieve_the_dev_packages_found_in_the_lock_file_even_if_no_dev_package_is_registered(): void
    {
        $decodedComposerJson = [];

        $decodedComposerLock = json_decode(
            <<<'JSON'
                {
                    "_readme": [
                        "This file locks the dependencies of your project to a known state",
                        "Read more about it at https://getcomposer.org/doc/01-basic-usage.md#composer-lock-the-lock-file",
                        "This file is @generated automatically"
                    ],
                    "content-hash": "c9ae998336c74a11e44be3255b6abceb",
                    "packages": [
                        {
                            "name": "amphp/amp",
                            "version": "v2.0.6",
                            "source": {
                                "type": "git",
                                "url": "https://github.com/amphp/amp.git",
                                "reference": "4a742beb59615f36ed998e2dc210e36576e44c44"
                            },
                            "dist": {
                                "type": "zip",
                                "url": "https://api.github.com/repos/amphp/amp/zipball/4a742beb59615f36ed998e2dc210e36576e44c44",
                                "reference": "4a742beb59615f36ed998e2dc210e36576e44c44",
                                "shasum": ""
                            },
                            "require": {
                                "php": ">=7"
                            },
                            "require-dev": {
                                "amphp/phpunit-util": "^1",
                                "friendsofphp/php-cs-fixer": "^2.3",
                                "phpstan/phpstan": "^0.8.5",
                                "phpunit/phpunit": "^6.0.9",
                                "react/promise": "^2"
                            },
                            "type": "library",
                            "extra": {
                                "branch-alias": {
                                    "dev-master": "2.0.x-dev"
                                }
                            },
                            "autoload": {
                                "psr-4": {
                                    "Amp\\": "lib"
                                },
                                "files": [
                                    "lib/functions.php",
                                    "lib/Internal/functions.php"
                                ]
                            },
                            "notification-url": "https://packagist.org/downloads/",
                            "license": [
                                "MIT"
                            ],
                            "authors": [
                                {
                                    "name": "Bob Weinand",
                                    "email": "bobwei9@hotmail.com"
                                },
                                {
                                    "name": "Niklas Keller",
                                    "email": "me@kelunik.com"
                                },
                                {
                                    "name": "Daniel Lowrey",
                                    "email": "rdlowrey@php.net"
                                },
                                {
                                    "name": "Aaron Piotrowski",
                                    "email": "aaron@trowski.com"
                                }
                            ],
                            "description": "A non-blocking concurrency framework for PHP applications.",
                            "homepage": "http://amphp.org/amp",
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
                            "time": "2018-01-27T19:18:05+00:00"
                        }
                    ],
                    "aliases": [],
                    "minimum-stability": "stable",
                    "stability-flags": [],
                    "prefer-stable": false,
                    "prefer-lowest": false,
                    "platform": {
                        "php": "^7.1",
                        "ext-phar": "*"
                    },
                    "platform-dev": []
                }
                JSON
            ,
            true,
        );

        mkdir('custom-vendor/bamarni/composer-bin-plugin');
        mkdir('vendor/doctrine/instantiator');  // Wrong directory

        $expected = [];

        $actual = ComposerConfiguration::retrieveDevPackages($this->tmp, $decodedComposerJson, $decodedComposerLock, true);

        $this->assertSame($expected, $actual);

        $this->assertSame(
            [],
            ComposerConfiguration::retrieveDevPackages(
                $this->tmp,
                $decodedComposerJson,
                $decodedComposerLock,
                false,
            ),
        );
    }

    public static function excludeDevFilesSettingProvider(): iterable
    {
        yield [true];
        yield [false];
    }
}
