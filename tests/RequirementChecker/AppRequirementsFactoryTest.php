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

use function json_decode;
use const JSON_THROW_ON_ERROR;
use Phar;
use PHPUnit\Framework\TestCase;

/**
 * @covers \KevinGH\Box\RequirementChecker\AppRequirementsFactory
 */
class AppRequirementsFactoryTest extends TestCase
{
    /**
     * @dataProvider lockContentsProvider
     */
    public function test_it_can_generate_and_serialized_requirements_from_a_composer_lock_file(
        ?string $composerJsonContents,
        ?string $composerLockContents,
        ?int $compressionAlgorithm,
        array $expected,
    ): void {
        $actual = AppRequirementsFactory::create(
            null === $composerJsonContents ? [] : json_decode($composerJsonContents, true, 512, JSON_THROW_ON_ERROR),
            null === $composerLockContents ? [] : json_decode($composerLockContents, true, 512, JSON_THROW_ON_ERROR),
            $compressionAlgorithm,
        );

        $this->assertSame($expected, $actual);
    }

    public static function lockContentsProvider(): iterable
    {
        yield 'empty json file' => [
            '{}',
            null,
            null,
            [],
        ];

        yield 'empty lock file' => [
            null,
            '{}',
            null,
            [],
        ];

        yield 'empty json & lock file' => [
            '{}',
            '{}',
            null,
            [],
        ];

        yield 'empty json file (compressed PHAR GZ)' => [
            '{}',
            null,
            Phar::GZ,
            [
                [
                    'type' => 'extension',
                    'condition' => 'zlib',
                    'message' => 'The application requires the extension "zlib". Enable it or install a polyfill.',
                    'helpMessage' => 'The application requires the extension "zlib".',
                ],
            ],
        ];

        yield 'empty json file (compressed PHAR BZ2)' => [
            '{}',
            null,
            Phar::BZ2,
            [
                [
                    'type' => 'extension',
                    'condition' => 'bz2',
                    'message' => 'The application requires the extension "bz2". Enable it or install a polyfill.',
                    'helpMessage' => 'The application requires the extension "bz2".',
                ],
            ],
        ];

        yield 'empty lock file (compressed PHAR GZ)' => [
            null,
            '{}',
            Phar::GZ,
            [
                [
                    'type' => 'extension',
                    'condition' => 'zlib',
                    'message' => 'The application requires the extension "zlib". Enable it or install a polyfill.',
                    'helpMessage' => 'The application requires the extension "zlib".',
                ],
            ],
        ];

        yield 'empty lock file (compressed PHAR BZ2)' => [
            null,
            '{}',
            Phar::BZ2,
            [
                [
                    'type' => 'extension',
                    'condition' => 'bz2',
                    'message' => 'The application requires the extension "bz2". Enable it or install a polyfill.',
                    'helpMessage' => 'The application requires the extension "bz2".',
                ],
            ],
        ];

        yield 'empty json & lock file (compressed PHAR GZ)' => [
            '{}',
            '{}',
            Phar::GZ,
            [
                [
                    'type' => 'extension',
                    'condition' => 'zlib',
                    'message' => 'The application requires the extension "zlib". Enable it or install a polyfill.',
                    'helpMessage' => 'The application requires the extension "zlib".',
                ],
            ],
        ];

        yield 'empty json & lock file (compressed PHAR BZ2)' => [
            '{}',
            '{}',
            Phar::BZ2,
            [
                [
                    'type' => 'extension',
                    'condition' => 'bz2',
                    'message' => 'The application requires the extension "bz2". Enable it or install a polyfill.',
                    'helpMessage' => 'The application requires the extension "bz2".',
                ],
            ],
        ];

        yield 'json file platform requirements' => [
            <<<'JSON'
                {
                    "require": {
                        "php": "^7.1",
                        "ext-phar": "*",
                        "acme/foo": "^1.0"
                    },
                    "require-dev": []
                }
                JSON
            ,
            null,
            null,
            [
                [
                    'type' => 'php',
                    'condition' => '^7.1',
                    'message' => 'The application requires the version "^7.1" or greater.',
                    'helpMessage' => 'The application requires the version "^7.1" or greater.',
                ],
                [
                    'type' => 'extension',
                    'condition' => 'phar',
                    'message' => 'The application requires the extension "phar". Enable it or install a polyfill.',
                    'helpMessage' => 'The application requires the extension "phar".',
                ],
            ],
        ];

        yield 'lock file platform requirements' => [
            null,
            <<<'JSON'
                {
                    "platform": {
                        "php": "^7.1",
                        "ext-phar": "*"
                    },
                    "platform-dev": []
                }
                JSON
            ,
            null,
            [
                [
                    'type' => 'php',
                    'condition' => '^7.1',
                    'message' => 'The application requires the version "^7.1" or greater.',
                    'helpMessage' => 'The application requires the version "^7.1" or greater.',
                ],
                [
                    'type' => 'extension',
                    'condition' => 'phar',
                    'message' => 'The application requires the extension "phar". Enable it or install a polyfill.',
                    'helpMessage' => 'The application requires the extension "phar".',
                ],
            ],
        ];

        yield 'json & lock file platform requirements' => [
            <<<'JSON'
                {
                    "platform": {
                        "php": "^7.2",
                        "ext-phar": "*",
                        "acme/foo": "^1.0"
                    },
                    "platform-dev": []
                }
                JSON
            ,
            <<<'JSON'
                {
                    "platform": {
                        "php": "^7.1",
                        "ext-phar": "*"
                    },
                    "platform-dev": []
                }
                JSON
            ,
            null,
            [
                [
                    'type' => 'php',
                    'condition' => '^7.1',
                    'message' => 'The application requires the version "^7.1" or greater.',
                    'helpMessage' => 'The application requires the version "^7.1" or greater.',
                ],
                [
                    'type' => 'extension',
                    'condition' => 'phar',
                    'message' => 'The application requires the extension "phar". Enable it or install a polyfill.',
                    'helpMessage' => 'The application requires the extension "phar".',
                ],
            ],
        ];

        yield 'json file platform requirements (compressed PHAR)' => [
            <<<'JSON'
                {
                    "require": {
                        "php": "^7.1",
                        "ext-phar": "*"
                    },
                    "require-dev": []
                }
                JSON
            ,
            null,
            Phar::GZ,
            [
                [
                    'type' => 'php',
                    'condition' => '^7.1',
                    'message' => 'The application requires the version "^7.1" or greater.',
                    'helpMessage' => 'The application requires the version "^7.1" or greater.',
                ],
                [
                    'type' => 'extension',
                    'condition' => 'zlib',
                    'message' => 'The application requires the extension "zlib". Enable it or install a polyfill.',
                    'helpMessage' => 'The application requires the extension "zlib".',
                ],
                [
                    'type' => 'extension',
                    'condition' => 'phar',
                    'message' => 'The application requires the extension "phar". Enable it or install a polyfill.',
                    'helpMessage' => 'The application requires the extension "phar".',
                ],
            ],
        ];

        yield 'lock file platform requirements (compressed PHAR)' => [
            null,
            <<<'JSON'
                {
                    "platform": {
                        "php": "^7.1",
                        "ext-phar": "*"
                    },
                    "platform-dev": []
                }
                JSON
            ,
            Phar::GZ,
            [
                [
                    'type' => 'php',
                    'condition' => '^7.1',
                    'message' => 'The application requires the version "^7.1" or greater.',
                    'helpMessage' => 'The application requires the version "^7.1" or greater.',
                ],
                [
                    'type' => 'extension',
                    'condition' => 'zlib',
                    'message' => 'The application requires the extension "zlib". Enable it or install a polyfill.',
                    'helpMessage' => 'The application requires the extension "zlib".',
                ],
                [
                    'type' => 'extension',
                    'condition' => 'phar',
                    'message' => 'The application requires the extension "phar". Enable it or install a polyfill.',
                    'helpMessage' => 'The application requires the extension "phar".',
                ],
            ],
        ];

        yield 'json & lock file platform requirements (compressed PHAR)' => [
            <<<'JSON'
                {
                    "require": {
                        "php": "^7.2",
                        "ext-phar": "*"
                    },
                    "require-dev": []
                }
                JSON
            ,
            <<<'JSON'
                {
                    "platform": {
                        "php": "^7.1",
                        "ext-phar": "*"
                    },
                    "platform-dev": []
                }
                JSON
            ,
            Phar::GZ,
            [
                [
                    'type' => 'php',
                    'condition' => '^7.1',
                    'message' => 'The application requires the version "^7.1" or greater.',
                    'helpMessage' => 'The application requires the version "^7.1" or greater.',
                ],
                [
                    'type' => 'extension',
                    'condition' => 'zlib',
                    'message' => 'The application requires the extension "zlib". Enable it or install a polyfill.',
                    'helpMessage' => 'The application requires the extension "zlib".',
                ],
                [
                    'type' => 'extension',
                    'condition' => 'phar',
                    'message' => 'The application requires the extension "phar". Enable it or install a polyfill.',
                    'helpMessage' => 'The application requires the extension "phar".',
                ],
            ],
        ];

        yield 'json file platform dev requirements are ignored' => [
            <<<'JSON'
                {
                    "require": [],
                    "require-dev": {
                        "php": "^7.3",
                        "ext-json": "*"
                    }
                }
                JSON
            ,
            null,
            null,
            [],
        ];

        yield 'lock file platform dev requirements are ignored' => [
            null,
            <<<'JSON'
                {
                    "platform": [],
                    "platform-dev": {
                        "php": "^7.3",
                        "ext-json": "*"
                    }
                }
                JSON
            ,
            null,
            [],
        ];

        yield 'json & lock file platform dev requirements are ignored' => [
            <<<'JSON'
                {
                    "require": [],
                    "require-dev": {
                        "php": "^7.3",
                        "ext-json": "*"
                    }
                }
                JSON
            ,
            <<<'JSON'
                {
                    "platform": [],
                    "platform-dev": {
                        "php": "^7.3",
                        "ext-json": "*"
                    }
                }
                JSON
            ,
            null,
            [],
        ];

        yield 'json file platform dev requirements are ignored (compressed PHAR)' => [
            <<<'JSON'
                {
                    "require": [],
                    "require-dev": {
                        "php": "^7.3",
                        "ext-json": "*"
                    }
                }
                JSON
            ,
            null,
            Phar::GZ,
            [
                [
                    'type' => 'extension',
                    'condition' => 'zlib',
                    'message' => 'The application requires the extension "zlib". Enable it or install a polyfill.',
                    'helpMessage' => 'The application requires the extension "zlib".',
                ],
            ],
        ];

        yield 'lock file platform dev requirements are ignored (compressed PHAR)' => [
            null,
            <<<'JSON'
                {
                    "platform": [],
                    "platform-dev": {
                        "php": "^7.3",
                        "ext-json": "*"
                    }
                }
                JSON
            ,
            Phar::GZ,
            [
                [
                    'type' => 'extension',
                    'condition' => 'zlib',
                    'message' => 'The application requires the extension "zlib". Enable it or install a polyfill.',
                    'helpMessage' => 'The application requires the extension "zlib".',
                ],
            ],
        ];

        yield 'json & lock file platform dev requirements are ignored (compressed PHAR)' => [
            <<<'JSON'
                {
                    "require": [],
                    "require-dev": {
                        "php": "^7.3",
                        "ext-json": "*"
                    }
                }
                JSON
            ,
            <<<'JSON'
                {
                    "platform": [],
                    "platform-dev": {
                        "php": "^7.3",
                        "ext-json": "*"
                    }
                }
                JSON
            ,
            Phar::GZ,
            [
                [
                    'type' => 'extension',
                    'condition' => 'zlib',
                    'message' => 'The application requires the extension "zlib". Enable it or install a polyfill.',
                    'helpMessage' => 'The application requires the extension "zlib".',
                ],
            ],
        ];

        yield 'json file packages requirements' => [
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
                JSON
            ,
            null,
            null,
            [
                [
                    'type' => 'php',
                    'condition' => '>=5.3',
                    'message' => 'The application requires the version ">=5.3" or greater.',
                    'helpMessage' => 'The application requires the version ">=5.3" or greater.',
                ],
                [
                    'type' => 'extension',
                    'condition' => 'mbstring',
                    'message' => 'The application requires the extension "mbstring". Enable it or install a polyfill.',
                    'helpMessage' => 'The application requires the extension "mbstring".',
                ],
                [
                    'type' => 'extension',
                    'condition' => 'openssl',
                    'message' => 'The application requires the extension "openssl". Enable it or install a polyfill.',
                    'helpMessage' => 'The application requires the extension "openssl".',
                ],
                [
                    'type' => 'extension',
                    'condition' => 'pcre',
                    'message' => 'The application requires the extension "pcre". Enable it or install a polyfill.',
                    'helpMessage' => 'The application requires the extension "pcre".',
                ],
                [
                    'type' => 'extension',
                    'condition' => 'pdo_sqlite3',
                    'message' => 'The application requires the extension "pdo_sqlite3". Enable it or install a polyfill.',
                    'helpMessage' => 'The application requires the extension "pdo_sqlite3".',
                ],
            ],
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
                JSON
            ,
            null,
            [
                [
                    'type' => 'php',
                    'condition' => '>=5.3',
                    'message' => 'The package "beberlei/assert" requires the version ">=5.3" or greater.',
                    'helpMessage' => 'The package "beberlei/assert" requires the version ">=5.3" or greater.',
                ],
                [
                    'type' => 'php',
                    'condition' => '^5.3.2 || ^7.0',
                    'message' => 'The package "composer/ca-bundle" requires the version "^5.3.2 || ^7.0" or greater.',
                    'helpMessage' => 'The package "composer/ca-bundle" requires the version "^5.3.2 || ^7.0" or greater.',
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
                JSON
            ,
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
                JSON
            ,
            null,
            [
                [
                    'type' => 'php',
                    'condition' => '>=5.3',
                    'message' => 'The package "beberlei/assert" requires the version ">=5.3" or greater.',
                    'helpMessage' => 'The package "beberlei/assert" requires the version ">=5.3" or greater.',
                ],
                [
                    'type' => 'php',
                    'condition' => '^5.3.2 || ^7.0',
                    'message' => 'The package "composer/ca-bundle" requires the version "^5.3.2 || ^7.0" or greater.',
                    'helpMessage' => 'The package "composer/ca-bundle" requires the version "^5.3.2 || ^7.0" or greater.',
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
                JSON
            ,
            null,
            null,
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
                JSON
            ,
            null,
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
                JSON
            ,
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
                JSON
            ,
            null,
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
                JSON
            ,
            null,
            [
                [
                    'type' => 'php',
                    'condition' => '^7.3',
                    'message' => 'The application requires the version "^7.3" or greater.',
                    'helpMessage' => 'The application requires the version "^7.3" or greater.',
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
                JSON
            ,
            null,
            null,
            [
                [
                    'type' => 'php',
                    'condition' => '^7.3',
                    'message' => 'The application requires the version "^7.3" or greater.',
                    'helpMessage' => 'The application requires the version "^7.3" or greater.',
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
                JSON
            ,
            null,
            [
                [
                    'type' => 'php',
                    'condition' => '^7.3',
                    'message' => 'The application requires the version "^7.3" or greater.',
                    'helpMessage' => 'The application requires the version "^7.3" or greater.',
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
                JSON
            ,
            null,
            null,
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
                JSON
            ,
            null,
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
                JSON
            ,
            null,
            null,
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
                JSON
            ,
            null,
            [],
        ];
    }
}
