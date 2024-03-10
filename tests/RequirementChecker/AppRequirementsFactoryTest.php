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

use KevinGH\Box\Composer\Artifact\ComposerJson;
use KevinGH\Box\Composer\Artifact\ComposerLock;
use KevinGH\Box\Phar\CompressionAlgorithm;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use function json_decode;
use const JSON_THROW_ON_ERROR;

/**
 * @internal
 */
#[CoversClass(AppRequirementsFactory::class)]
class AppRequirementsFactoryTest extends TestCase
{
    #[DataProvider('lockContentsProvider')]
    public function test_it_can_generate_and_serialized_requirements_from_a_composer_lock_file(
        ?string $composerJsonContents,
        ?string $composerLockContents,
        CompressionAlgorithm $compressionAlgorithm,
        array $expected,
    ): void {
        $composerJson = new ComposerJson(
            '',
            null === $composerJsonContents
                ? []
                : json_decode($composerJsonContents, true, flags: JSON_THROW_ON_ERROR),
        );
        $composerLock = new ComposerLock(
            '',
            null === $composerLockContents
                ? []
                : json_decode($composerLockContents, true, flags: JSON_THROW_ON_ERROR),
        );

        $actual = (new AppRequirementsFactory())->create(
            $composerJson,
            $composerLock,
            $compressionAlgorithm,
        );

        self::assertEquals(
            (new Requirements($expected))->toArray(),
            $actual->toArray(),
        );
    }

    public static function lockContentsProvider(): iterable
    {
        yield 'empty json file' => [
            '{}',
            null,
            CompressionAlgorithm::NONE,
            [],
        ];

        yield 'empty lock file' => [
            null,
            '{}',
            CompressionAlgorithm::NONE,
            [],
        ];

        yield 'empty json & lock file' => [
            '{}',
            '{}',
            CompressionAlgorithm::NONE,
            [],
        ];

        yield 'empty json file (compressed PHAR GZ)' => [
            '{}',
            null,
            CompressionAlgorithm::GZ,
            [
                Requirement::forRequiredExtension('zlib', null),
            ],
        ];

        yield 'empty json file (compressed PHAR BZ2)' => [
            '{}',
            null,
            CompressionAlgorithm::BZ2,
            [
                Requirement::forRequiredExtension('bz2', null),
            ],
        ];

        yield 'empty lock file (compressed PHAR GZ)' => [
            null,
            '{}',
            CompressionAlgorithm::GZ,
            [
                Requirement::forRequiredExtension('zlib', null),
            ],
        ];

        yield 'empty lock file (compressed PHAR BZ2)' => [
            null,
            '{}',
            CompressionAlgorithm::BZ2,
            [
                Requirement::forRequiredExtension('bz2', null),
            ],
        ];

        yield 'empty json & lock file (compressed PHAR GZ)' => [
            '{}',
            '{}',
            CompressionAlgorithm::GZ,
            [
                Requirement::forRequiredExtension('zlib', null),
            ],
        ];

        yield 'empty json & lock file (compressed PHAR BZ2)' => [
            '{}',
            '{}',
            CompressionAlgorithm::BZ2,
            [
                Requirement::forRequiredExtension('bz2', null),
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
                JSON,
            null,
            CompressionAlgorithm::NONE,
            [
                Requirement::forPHP('^7.1', null),
                Requirement::forRequiredExtension('phar', null),
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
                JSON,
            CompressionAlgorithm::NONE,
            [
                Requirement::forPHP('^7.1', null),
                Requirement::forRequiredExtension('phar', null),
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
                JSON,
            <<<'JSON'
                {
                    "platform": {
                        "php": "^7.1",
                        "ext-phar": "*"
                    },
                    "platform-dev": []
                }
                JSON,
            CompressionAlgorithm::NONE,
            [
                Requirement::forPHP('^7.1', null),
                Requirement::forRequiredExtension('phar', null),
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
                JSON,
            null,
            CompressionAlgorithm::GZ,
            [
                Requirement::forPHP('^7.1', null),
                Requirement::forRequiredExtension('phar', null),
                Requirement::forRequiredExtension('zlib', null),
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
                JSON,
            CompressionAlgorithm::GZ,
            [
                Requirement::forPHP('^7.1', null),
                Requirement::forRequiredExtension('phar', null),
                Requirement::forRequiredExtension('zlib', null),
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
                JSON,
            <<<'JSON'
                {
                    "platform": {
                        "php": "^7.1",
                        "ext-phar": "*"
                    },
                    "platform-dev": []
                }
                JSON,
            CompressionAlgorithm::GZ,
            [
                Requirement::forPHP('^7.1', null),
                Requirement::forRequiredExtension('phar', null),
                Requirement::forRequiredExtension('zlib', null),
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
                JSON,
            null,
            CompressionAlgorithm::NONE,
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
                JSON,
            CompressionAlgorithm::NONE,
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
                JSON,
            <<<'JSON'
                {
                    "platform": [],
                    "platform-dev": {
                        "php": "^7.3",
                        "ext-json": "*"
                    }
                }
                JSON,
            CompressionAlgorithm::NONE,
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
                JSON,
            null,
            CompressionAlgorithm::GZ,
            [
                Requirement::forRequiredExtension('zlib', null),
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
                JSON,
            CompressionAlgorithm::GZ,
            [
                Requirement::forRequiredExtension('zlib', null),
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
                JSON,
            <<<'JSON'
                {
                    "platform": [],
                    "platform-dev": {
                        "php": "^7.3",
                        "ext-json": "*"
                    }
                }
                JSON,
            CompressionAlgorithm::GZ,
            [
                Requirement::forRequiredExtension('zlib', null),
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
                JSON,
            null,
            CompressionAlgorithm::NONE,
            [
                Requirement::forPHP('>=5.3', null),
                Requirement::forRequiredExtension('mbstring', null),
                Requirement::forRequiredExtension('openssl', null),
                Requirement::forRequiredExtension('pcre', null),
                Requirement::forRequiredExtension('pdo_sqlite3', null),
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
                JSON,
            CompressionAlgorithm::NONE,
            [
                Requirement::forPHP('>=5.3', 'beberlei/assert'),
                Requirement::forPHP('^5.3.2 || ^7.0', 'composer/ca-bundle'),
                Requirement::forRequiredExtension('mbstring', 'beberlei/assert'),
                Requirement::forRequiredExtension('openssl', 'acme/foo'),
                Requirement::forRequiredExtension('openssl', 'composer/ca-bundle'),
                Requirement::forRequiredExtension('pcre', 'composer/ca-bundle'),
            ],
        ];

        yield 'The application defines a PHP requirement (takes it from the composer.lock)' => [
            <<<'JSON'
                {
                    "require": {
                        "php": ">=5.3"
                    },
                    "require-dev": []
                }
                JSON,
            <<<'JSON'
                {
                    "platform": {
                        "php": ">=5.4"
                    },
                    "packages": [
                        {
                            "name": "beberlei/assert",
                            "version": "v2.9.2",
                            "require": {
                                "php": ">=5.3"
                            },
                            "require-dev": []
                        },
                        {
                            "name": "composer/ca-bundle",
                            "version": "1.1.0",
                            "require": {},
                            "require-dev": {
                                "ext-pdo_sqlite3": "*"
                            }
                        }
                    ]
                }
                JSON,
            CompressionAlgorithm::NONE,
            [
                Requirement::forPHP('>=5.4', null),
            ],
        ];

        yield 'The application defines a PHP requirement (takes it from the composer.json if the .lock does not have it)' => [
            <<<'JSON'
                {
                    "require": {
                        "php": ">=5.3"
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
                                "php": ">=5.3"
                            },
                            "require-dev": []
                        },
                        {
                            "name": "composer/ca-bundle",
                            "version": "1.1.0",
                            "require": {},
                            "require-dev": {
                                "ext-pdo_sqlite3": "*"
                            }
                        }
                    ]
                }
                JSON,
            CompressionAlgorithm::NONE,
            [
                Requirement::forPHP('>=5.3', null),
            ],
        ];

        yield 'The application does not define a PHP requirement (takes it from packages)' => [
            <<<'JSON'
                {
                    "require": {},
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
                                "php": ">=5.3"
                            },
                            "require-dev": []
                        },
                        {
                            "name": "acme/foo",
                            "version": "2.0.0",
                            "require": {},
                            "require-dev": {}
                        },
                        {
                            "name": "composer/ca-bundle",
                            "version": "1.1.0",
                            "require": {
                                "php": "^7.1"
                            },
                            "require-dev": {
                                "ext-pdo_sqlite3": "*"
                            }
                        }
                    ]
                }
                JSON,
            CompressionAlgorithm::NONE,
            [
                Requirement::forPHP('>=5.3', 'beberlei/assert'),
                Requirement::forPHP('^7.1', 'composer/ca-bundle'),
            ],
        ];

        yield 'The application has extension conflicts' => [
            <<<'JSON'
                {
                    "require": {
                        "php": ">=5.3"
                    },
                    "require-dev": [],
                    "conflict": {
                        "ext-psr": "*",
                        "ext-phar": "*",
                        "laminas/laminas-code": "<3.3.1"
                    }
                }
                JSON,
            <<<'JSON'
                {
                    "platform": {
                        "php": ">=5.4"
                    },
                    "packages": [
                        {
                            "name": "beberlei/assert",
                            "version": "v2.9.2",
                            "require": {
                                "php": ">=5.3"
                            },
                            "require-dev": [],
                            "conflict": {
                                "ext-http": "*",
                                "ext-psr": "*"
                            }
                        },
                        {
                            "name": "composer/ca-bundle",
                            "version": "1.1.0",
                            "require": {},
                            "require-dev": {
                                "ext-pdo_sqlite3": "*"
                            },
                            "conflict": {
                                "ext-zlib": "*"
                            }
                        }
                    ]
                }
                JSON,
            CompressionAlgorithm::NONE,
            [
                Requirement::forPHP('>=5.4', null),
                Requirement::forConflictingExtension('http', 'beberlei/assert'),
                Requirement::forConflictingExtension('phar', null),
                Requirement::forConflictingExtension('psr', null),
                Requirement::forConflictingExtension('psr', 'beberlei/assert'),
                Requirement::forConflictingExtension('zlib', 'composer/ca-bundle'),
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
                                "ext-pcre": "*"
                            },
                            "require-dev": {
                                "ext-pdo_sqlite3": "*"
                            }
                        },
                        {
                            "name": "acme/foo",
                            "version": "1.1.0",
                            "require": {
                                "ext-openssl": "*",
                                "php": "^5.3.2 || ^7.0"
                            },
                            "require-dev": []
                        }
                    ],
                    "platform-dev": []
                }
                JSON,
            CompressionAlgorithm::NONE,
            [
                Requirement::forPHP('>=5.3', null),
                Requirement::forRequiredExtension('mbstring', null),
                Requirement::forRequiredExtension('mbstring', 'beberlei/assert'),
                Requirement::forRequiredExtension('openssl', null),
                Requirement::forRequiredExtension('openssl', 'acme/foo'),
                Requirement::forRequiredExtension('openssl', 'composer/ca-bundle'),
                Requirement::forRequiredExtension('pcre', null),
                Requirement::forRequiredExtension('pcre', 'composer/ca-bundle'),
                Requirement::forRequiredExtension('pdo_sqlite3', null),
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
                Requirement::forPHP('^7.3', null),
                Requirement::forRequiredExtension('json', null),
                Requirement::forRequiredExtension('json', 'beberlei/assert'),
                Requirement::forRequiredExtension('json', 'composer/ca-bundle'),
                Requirement::forRequiredExtension('mbstring', null),
                Requirement::forRequiredExtension('mbstring', 'beberlei/assert'),
                Requirement::forRequiredExtension('mbstring', 'composer/ca-bundle'),
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
                Requirement::forPHP('^7.3', null),
                Requirement::forRequiredExtension('json', null),
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
                Requirement::forPHP('^7.3', null),
                Requirement::forRequiredExtension('json', null),
                Requirement::forRequiredExtension('json', 'beberlei/assert'),
                Requirement::forRequiredExtension('json', 'composer/ca-bundle'),
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
}
