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

namespace KevinGH\Box\RequirementChecker;

use KevinGH\Box\Phar\CompressionAlgorithm;
use PHPUnit\Framework\TestCase;
use function json_decode;
use const JSON_THROW_ON_ERROR;

/**
 * @covers \KevinGH\Box\RequirementChecker\DecodedComposerJson
 *
 * @internal
 */
class DecodedComposerJsonTest extends TestCase
{
    /**
     * @dataProvider composerJsonProvider
     */
    public function test_it_can_interpret_a_decoded_composer_json_file(
        string $composerJsonContents,
        ?string $expectedRequiredPhpVersion,
        bool $expectedHasRequiredPhpVersion,
        array $expectedPackages,
    ): void {
        $actual = new DecodedComposerJson(json_decode($composerJsonContents, true));

        self::assertStateIs(
            $actual,
            $expectedRequiredPhpVersion,
            $expectedHasRequiredPhpVersion,
            $expectedPackages,
        );
    }

    public static function composerJsonProvider(): iterable
    {
        yield 'empty json file' => [
            '{}',
            null,
            false,
            [],
        ];

        yield 'PHP platform requirements' => [
            <<<'JSON'
                {
                    "require": {
                        "php": "^7.1",
                        "ext-phar": "*",
                        "acme/foo": "^1.0"
                    },
                    "require-dev": []
                }
                JSON,
            '^7.1',
            true,
            [],
        ];

        yield 'PHP platform requirements' => [
            <<<'JSON'
                {
                    "require": {
                        "php": "^7.1",
                        "ext-phar": "*",
                        "acme/foo": "^1.0"
                    },
                    "require-dev": []
                }
                JSON,
            '^7.1',
            true,
            [],
        ];

        yield 'lock file packages requirements' => [
            null,
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
                        },
                        {
                            "name": "acme/foo",
                            "version": "1.1.0",
                            "require": {
                                "ext-openssl": "*"
                            },
                            "require-dev": []
                        }
                    ],
                    "platform-dev": []
                }
                JSON,
            CompressionAlgorithm::NONE,
            [
                [
                    'type' => 'php',
                    'condition' => '>=5.3',
                    'message' => 'The package "beberlei/assert" requires a version matching ">=5.3".',
                    'helpMessage' => 'The package "beberlei/assert" requires a version matching ">=5.3".',
                ],
                [
                    'type' => 'php',
                    'condition' => '^5.3.2 || ^7.0',
                    'message' => 'The package "composer/ca-bundle" requires a version matching "^5.3.2 || ^7.0".',
                    'helpMessage' => 'The package "composer/ca-bundle" requires a version matching "^5.3.2 || ^7.0".',
                ],
                [
                    'type' => 'extension',
                    'condition' => 'mbstring',
                    'message' => 'The package "beberlei/assert" requires the extension "mbstring". Enable it or install a polyfill.',
                    'helpMessage' => 'The package "beberlei/assert" requires the extension "mbstring".',
                ],
                [
                    'type' => 'extension',
                    'condition' => 'openssl',
                    'message' => 'The package "composer/ca-bundle" requires the extension "openssl". Enable it or install a polyfill.',
                    'helpMessage' => 'The package "composer/ca-bundle" requires the extension "openssl".',
                ],
                [
                    'type' => 'extension',
                    'condition' => 'openssl',
                    'message' => 'The package "acme/foo" requires the extension "openssl". Enable it or install a polyfill.',
                    'helpMessage' => 'The package "acme/foo" requires the extension "openssl".',
                ],
                [
                    'type' => 'extension',
                    'condition' => 'pcre',
                    'message' => 'The package "composer/ca-bundle" requires the extension "pcre". Enable it or install a polyfill.',
                    'helpMessage' => 'The package "composer/ca-bundle" requires the extension "pcre".',
                ],
            ],
        ];

        yield 'json & lock file packages requirements' => [
            <<<'JSON'
                {
                    "require": {
                        "php": ">=5.3",
                        "ext-mbstring": "*",
                        "ext-openssl": "*",
                        "ext-pcre": "*",
                        "ext-pdo_sqlite3": "*"
                    },
                    "require-dev": []
                }
                JSON,
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
                        },
                        {
                            "name": "acme/foo",
                            "version": "1.1.0",
                            "require": {
                                "ext-openssl": "*"
                            },
                            "require-dev": []
                        }
                    ],
                    "platform-dev": []
                }
                JSON,
            CompressionAlgorithm::NONE,
            [
                [
                    'type' => 'php',
                    'condition' => '>=5.3',
                    'message' => 'The package "beberlei/assert" requires a version matching ">=5.3".',
                    'helpMessage' => 'The package "beberlei/assert" requires a version matching ">=5.3".',
                ],
                [
                    'type' => 'php',
                    'condition' => '^5.3.2 || ^7.0',
                    'message' => 'The package "composer/ca-bundle" requires a version matching "^5.3.2 || ^7.0".',
                    'helpMessage' => 'The package "composer/ca-bundle" requires a version matching "^5.3.2 || ^7.0".',
                ],
                [
                    'type' => 'extension',
                    'condition' => 'mbstring',
                    'message' => 'The package "beberlei/assert" requires the extension "mbstring". Enable it or install a polyfill.',
                    'helpMessage' => 'The package "beberlei/assert" requires the extension "mbstring".',
                ],
                [
                    'type' => 'extension',
                    'condition' => 'openssl',
                    'message' => 'The package "composer/ca-bundle" requires the extension "openssl". Enable it or install a polyfill.',
                    'helpMessage' => 'The package "composer/ca-bundle" requires the extension "openssl".',
                ],
                [
                    'type' => 'extension',
                    'condition' => 'openssl',
                    'message' => 'The package "acme/foo" requires the extension "openssl". Enable it or install a polyfill.',
                    'helpMessage' => 'The package "acme/foo" requires the extension "openssl".',
                ],
                [
                    'type' => 'extension',
                    'condition' => 'pcre',
                    'message' => 'The package "composer/ca-bundle" requires the extension "pcre". Enable it or install a polyfill.',
                    'helpMessage' => 'The package "composer/ca-bundle" requires the extension "pcre".',
                ],
            ],
        ];

        yield 'json file dev packages are ignored' => [
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
            CompressionAlgorithm::NONE,
            [],
        ];

        yield 'lock file dev packages are ignored' => [
            null,
            <<<'JSON'
                {
                    "packages": [],
                    "packages-dev": [
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
                    ]
                }
                JSON,
            CompressionAlgorithm::NONE,
            [],
        ];

        yield 'json & lock file dev packages are ignored' => [
            <<<'JSON'
                {
                    "require": [],
                    "require-dev": {
                        "ext-mbstring": "*",
                        "php": ">=5.3"
                    }
                }
                JSON,
            <<<'JSON'
                {
                    "packages": [],
                    "packages-dev": [
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
                    ]
                }
                JSON,
            CompressionAlgorithm::NONE,
            [],
        ];

        yield 'duplicate requirements' => [
            null,
            <<<'JSON'
                {
                    "packages": [
                        {
                            "name": "beberlei/assert",
                            "version": "v2.9.2",
                            "require": {
                                "php": "^7.3",
                                "ext-mbstring": "*",
                                "ext-json": "*"
                            },
                            "require-dev": []
                        },
                        {
                            "name": "composer/ca-bundle",
                            "version": "1.1.0",
                            "require": {
                                "php": "^7.3",
                                "ext-mbstring": "*",
                                "ext-json": "*"
                            },
                            "require-dev": {
                                "ext-pdo_sqlite3": "*"
                            }
                        }
                    ],
                    "packages-dev": [],
                    "platform": {
                        "php": "^7.3",
                        "ext-mbstring": "*",
                        "ext-json": "*"
                    },
                    "platform-dev": []
                }
                JSON,
            CompressionAlgorithm::NONE,
            [
                [
                    'type' => 'php',
                    'condition' => '^7.3',
                    'message' => 'The application requires a version matching "^7.3".',
                    'helpMessage' => 'The application requires a version matching "^7.3".',
                ],
                [
                    'type' => 'extension',
                    'condition' => 'mbstring',
                    'message' => 'The application requires the extension "mbstring". Enable it or install a polyfill.',
                    'helpMessage' => 'The application requires the extension "mbstring".',
                ],
                [
                    'type' => 'extension',
                    'condition' => 'mbstring',
                    'message' => 'The package "beberlei/assert" requires the extension "mbstring". Enable it or install a polyfill.',
                    'helpMessage' => 'The package "beberlei/assert" requires the extension "mbstring".',
                ],
                [
                    'type' => 'extension',
                    'condition' => 'mbstring',
                    'message' => 'The package "composer/ca-bundle" requires the extension "mbstring". Enable it or install a polyfill.',
                    'helpMessage' => 'The package "composer/ca-bundle" requires the extension "mbstring".',
                ],
                [
                    'type' => 'extension',
                    'condition' => 'json',
                    'message' => 'The application requires the extension "json". Enable it or install a polyfill.',
                    'helpMessage' => 'The application requires the extension "json".',
                ],
                [
                    'type' => 'extension',
                    'condition' => 'json',
                    'message' => 'The package "beberlei/assert" requires the extension "json". Enable it or install a polyfill.',
                    'helpMessage' => 'The package "beberlei/assert" requires the extension "json".',
                ],
                [
                    'type' => 'extension',
                    'condition' => 'json',
                    'message' => 'The package "composer/ca-bundle" requires the extension "json". Enable it or install a polyfill.',
                    'helpMessage' => 'The package "composer/ca-bundle" requires the extension "json".',
                ],
            ],
        ];

        yield 'it supports polyfills (json)' => [
            <<<'JSON'
                {
                    "require": {
                        "php": "^7.3",
                        "ext-mbstring": "*",
                        "ext-json": "*",
                        "symfony/polyfill-mbstring": "^1.0"
                    },
                    "require-dev": {
                        "symfony/polyfill-json": "^4.0"
                    }
                }
                JSON,
            null,
            CompressionAlgorithm::NONE,
            [
                [
                    'type' => 'php',
                    'condition' => '^7.3',
                    'message' => 'The application requires a version matching "^7.3".',
                    'helpMessage' => 'The application requires a version matching "^7.3".',
                ],
                [
                    'type' => 'extension',
                    'condition' => 'json',
                    'message' => 'The application requires the extension "json". Enable it or install a polyfill.',
                    'helpMessage' => 'The application requires the extension "json".',
                ],
            ],
        ];

        yield 'it supports polyfills (lock)' => [
            null,
            <<<'JSON'
                {
                    "packages": [
                        {
                            "name": "beberlei/assert",
                            "version": "v2.9.2",
                            "require": {
                                "php": "^7.3",
                                "ext-mbstring": "*",
                                "ext-json": "*"
                            },
                            "require-dev": []
                        },
                        {
                            "name": "composer/ca-bundle",
                            "version": "1.1.0",
                            "require": {
                                "php": "^7.3",
                                "ext-mbstring": "*",
                                "ext-json": "*"
                            },
                            "require-dev": {
                                "ext-pdo_sqlite3": "*"
                            }
                        },
                        {
                            "name": "symfony/polyfill-mbstring",
                            "version": "1.1.0",
                            "require": [],
                            "require-dev": []
                        }
                    ],
                    "packages-dev": [
                        {
                            "name": "acme/bar",
                            "version": "1.1.0",
                            "require": {
                                "php": "^7.3",
                                "symfony/polyfill-json": "^4.0"
                            },
                            "require-dev": []
                        }
                    ],
                    "platform": {
                        "php": "^7.3",
                        "ext-mbstring": "*",
                        "ext-json": "*"
                    },
                    "platform-dev": []
                }
                JSON,
            CompressionAlgorithm::NONE,
            [
                [
                    'type' => 'php',
                    'condition' => '^7.3',
                    'message' => 'The application requires a version matching "^7.3".',
                    'helpMessage' => 'The application requires a version matching "^7.3".',
                ],
                [
                    'type' => 'extension',
                    'condition' => 'json',
                    'message' => 'The application requires the extension "json". Enable it or install a polyfill.',
                    'helpMessage' => 'The application requires the extension "json".',
                ],
                [
                    'type' => 'extension',
                    'condition' => 'json',
                    'message' => 'The package "beberlei/assert" requires the extension "json". Enable it or install a polyfill.',
                    'helpMessage' => 'The package "beberlei/assert" requires the extension "json".',
                ],
                [
                    'type' => 'extension',
                    'condition' => 'json',
                    'message' => 'The package "composer/ca-bundle" requires the extension "json". Enable it or install a polyfill.',
                    'helpMessage' => 'The package "composer/ca-bundle" requires the extension "json".',
                ],
            ],
        ];

        yield 'libsodium polyfill (json)' => [
            <<<'JSON'
                {
                    "require": {
                        "ext-libsodium": "*",
                        "paragonie/sodium_compat": "^1.0"
                    }
                }
                JSON,
            null,
            CompressionAlgorithm::NONE,
            [],
        ];

        yield 'libsodium polyfill (lock)' => [
            null,
            <<<'JSON'
                {
                    "platform": {
                        "ext-libsodium": "*"
                    },
                    "packages": [
                        {
                            "name": "paragonie/sodium_compat",
                            "version": "1.0.0"
                        }
                    ]
                }
                JSON,
            CompressionAlgorithm::NONE,
            [],
        ];

        yield 'mcrypt polyfill (json)' => [
            <<<'JSON'
                {
                    "require": {
                        "ext-mcrypt": "*",
                        "phpseclib/mcrypt_compat": "^1.0"
                    }
                }
                JSON,
            null,
            CompressionAlgorithm::NONE,
            [],
        ];

        yield 'mcrypt polyfill (lock)' => [
            null,
            <<<'JSON'
                {
                    "platform": {
                        "ext-mcrypt": "*"
                    },
                    "packages": [
                        {
                            "name": "phpseclib/mcrypt_compat",
                            "version": "1.0.0"
                        }
                    ]
                }
                JSON,
            CompressionAlgorithm::NONE,
            [],
        ];
    }

    private static function assertStateIs(
        DecodedComposerJson $composerJson,
        ?string $expectedRequiredPhpVersion,
        bool $expectedHasRequiredPhpVersion,
        array $expectedPackages,
    ): void
    {
        self::assertSame($expectedRequiredPhpVersion, $composerJson->getRequiredPhpVersion());
        self::assertSame($expectedHasRequiredPhpVersion, $composerJson->hasRequiredPhpVersion());
        self::assertEquals($expectedPackages, $composerJson->getPackages());
    }
}
