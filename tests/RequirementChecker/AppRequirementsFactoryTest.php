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

use PHPUnit\Framework\TestCase;
use function json_decode;

/**
 * @coversNothing
 */
class pAppRequirementsFactoryTest extends TestCase
{
    /**
     * @dataProvider provideLockContents
     */
    public function test_it_can_generate_and_serialized_requirements_from_a_composer_lock_file(string $composerLockContents, bool $compressed, array $expected): void
    {
        $actual = AppRequirementsFactory::create(json_decode($composerLockContents, true), $compressed);

        $this->assertSame($expected, $actual);
    }

    public function provideLockContents()
    {
        yield 'empty lock file' => [
            '{}',
            false,
            [],
        ];

        yield 'empty lock file (compressed PHAR)' => [
            '{}',
            true,
            [
                [
                    "return \\extension_loaded('zip');",
                    'The application requires the extension "zip". Enable it or install a polyfill.',
                    'The application requires the extension "zip".',
                ],
            ],
        ];

        yield 'lock file platform requirements' => [
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
            false,
            [
                [
                    <<<'PHP'
require_once __DIR__.'/../vendor/composer/semver/src/Semver.php';
require_once __DIR__.'/../vendor/composer/semver/src/VersionParser.php';
require_once __DIR__.'/../vendor/composer/semver/src/Constraint/ConstraintInterface.php';
require_once __DIR__.'/../vendor/composer/semver/src/Constraint/EmptyConstraint.php';
require_once __DIR__.'/../vendor/composer/semver/src/Constraint/MultiConstraint.php';
require_once __DIR__.'/../vendor/composer/semver/src/Constraint/Constraint.php';

return \Composer\Semver\Semver::satisfies(
    sprintf('%d.%d.%d', \PHP_MAJOR_VERSION, \PHP_MINOR_VERSION, \PHP_RELEASE_VERSION),
    '^7.1'
);

PHP
                    ,
                    'The application requires the version "^7.1" or greater.',
                    'The application requires the version "^7.1" or greater.',
                ],
                [
                    "return \\extension_loaded('phar');",
                    'The application requires the extension "phar". Enable it or install a polyfill.',
                    'The application requires the extension "phar".',
                ],
            ],
        ];

        yield 'lock file platform requirements (compressed PHAR)' => [
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
            true,
            [
                [
                    <<<'PHP'
require_once __DIR__.'/../vendor/composer/semver/src/Semver.php';
require_once __DIR__.'/../vendor/composer/semver/src/VersionParser.php';
require_once __DIR__.'/../vendor/composer/semver/src/Constraint/ConstraintInterface.php';
require_once __DIR__.'/../vendor/composer/semver/src/Constraint/EmptyConstraint.php';
require_once __DIR__.'/../vendor/composer/semver/src/Constraint/MultiConstraint.php';
require_once __DIR__.'/../vendor/composer/semver/src/Constraint/Constraint.php';

return \Composer\Semver\Semver::satisfies(
    sprintf('%d.%d.%d', \PHP_MAJOR_VERSION, \PHP_MINOR_VERSION, \PHP_RELEASE_VERSION),
    '^7.1'
);

PHP
                    ,
                    'The application requires the version "^7.1" or greater.',
                    'The application requires the version "^7.1" or greater.',
                ],
                [
                    "return \\extension_loaded('zip');",
                    'The application requires the extension "zip". Enable it or install a polyfill.',
                    'The application requires the extension "zip".',
                ],
                [
                    "return \\extension_loaded('phar');",
                    'The application requires the extension "phar". Enable it or install a polyfill.',
                    'The application requires the extension "phar".',
                ],
            ],
        ];

        yield 'lock file platform dev requirements are ignored' => [
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
            false,
            [],
        ];

        yield 'lock file platform dev requirements are ignored (compressed PHAR)' => [
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
            true,
            [
                [
                    "return \\extension_loaded('zip');",
                    'The application requires the extension "zip". Enable it or install a polyfill.',
                    'The application requires the extension "zip".',
                ],
            ],
        ];

        yield 'lock file packages requirements' => [
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
            false,
            [
                [
                    <<<'PHP'
require_once __DIR__.'/../vendor/composer/semver/src/Semver.php';
require_once __DIR__.'/../vendor/composer/semver/src/VersionParser.php';
require_once __DIR__.'/../vendor/composer/semver/src/Constraint/ConstraintInterface.php';
require_once __DIR__.'/../vendor/composer/semver/src/Constraint/EmptyConstraint.php';
require_once __DIR__.'/../vendor/composer/semver/src/Constraint/MultiConstraint.php';
require_once __DIR__.'/../vendor/composer/semver/src/Constraint/Constraint.php';

return \Composer\Semver\Semver::satisfies(
    sprintf('%d.%d.%d', \PHP_MAJOR_VERSION, \PHP_MINOR_VERSION, \PHP_RELEASE_VERSION),
    '>=5.3'
);

PHP
                    ,
                    'The package "beberlei/assert" requires the version ">=5.3" or greater.',
                    'The package "beberlei/assert" requires the version ">=5.3" or greater.',
                ],
                [
                    <<<'PHP'
require_once __DIR__.'/../vendor/composer/semver/src/Semver.php';
require_once __DIR__.'/../vendor/composer/semver/src/VersionParser.php';
require_once __DIR__.'/../vendor/composer/semver/src/Constraint/ConstraintInterface.php';
require_once __DIR__.'/../vendor/composer/semver/src/Constraint/EmptyConstraint.php';
require_once __DIR__.'/../vendor/composer/semver/src/Constraint/MultiConstraint.php';
require_once __DIR__.'/../vendor/composer/semver/src/Constraint/Constraint.php';

return \Composer\Semver\Semver::satisfies(
    sprintf('%d.%d.%d', \PHP_MAJOR_VERSION, \PHP_MINOR_VERSION, \PHP_RELEASE_VERSION),
    '^5.3.2 || ^7.0'
);

PHP
                    ,
                    'The package "composer/ca-bundle" requires the version "^5.3.2 || ^7.0" or greater.',
                    'The package "composer/ca-bundle" requires the version "^5.3.2 || ^7.0" or greater.',
                ],
                [
                    "return \\extension_loaded('mbstring');",
                    'The package "beberlei/assert" requires the extension "mbstring". Enable it or install a polyfill.',
                    'The package "beberlei/assert" requires the extension "mbstring".',
                ],
                [
                    "return \\extension_loaded('openssl');",
                    'The package "composer/ca-bundle" requires the extension "openssl". Enable it or install a polyfill.',
                    'The package "composer/ca-bundle" requires the extension "openssl".',
                ],
                [
                    "return \\extension_loaded('openssl');",
                    'The package "acme/foo" requires the extension "openssl". Enable it or install a polyfill.',
                    'The package "acme/foo" requires the extension "openssl".',
                ],
                [
                    "return \\extension_loaded('pcre');",
                    'The package "composer/ca-bundle" requires the extension "pcre". Enable it or install a polyfill.',
                    'The package "composer/ca-bundle" requires the extension "pcre".',
                ],
            ],
        ];

        yield 'lock file dev packages are ignored' => [
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
            false,
            [],
        ];

        yield 'duplicate requirements' => [
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
            false,
            [
                [
                    <<<'PHP'
require_once __DIR__.'/../vendor/composer/semver/src/Semver.php';
require_once __DIR__.'/../vendor/composer/semver/src/VersionParser.php';
require_once __DIR__.'/../vendor/composer/semver/src/Constraint/ConstraintInterface.php';
require_once __DIR__.'/../vendor/composer/semver/src/Constraint/EmptyConstraint.php';
require_once __DIR__.'/../vendor/composer/semver/src/Constraint/MultiConstraint.php';
require_once __DIR__.'/../vendor/composer/semver/src/Constraint/Constraint.php';

return \Composer\Semver\Semver::satisfies(
    sprintf('%d.%d.%d', \PHP_MAJOR_VERSION, \PHP_MINOR_VERSION, \PHP_RELEASE_VERSION),
    '^7.3'
);

PHP
                    ,
                    'The application requires the version "^7.3" or greater.',
                    'The application requires the version "^7.3" or greater.',
                ],
                [
                    "return \\extension_loaded('mbstring');",
                    'The application requires the extension "mbstring". Enable it or install a polyfill.',
                    'The application requires the extension "mbstring".',
                ],
                [
                    "return \\extension_loaded('mbstring');",
                    'The package "beberlei/assert" requires the extension "mbstring". Enable it or install a polyfill.',
                    'The package "beberlei/assert" requires the extension "mbstring".',
                ],
                [
                    "return \\extension_loaded('mbstring');",
                    'The package "composer/ca-bundle" requires the extension "mbstring". Enable it or install a polyfill.',
                    'The package "composer/ca-bundle" requires the extension "mbstring".',
                ],
                [
                    "return \\extension_loaded('json');",
                    'The application requires the extension "json". Enable it or install a polyfill.',
                    'The application requires the extension "json".',
                ],
                [
                    "return \\extension_loaded('json');",
                    'The package "beberlei/assert" requires the extension "json". Enable it or install a polyfill.',
                    'The package "beberlei/assert" requires the extension "json".',
                ],
                [
                    "return \\extension_loaded('json');",
                    'The package "composer/ca-bundle" requires the extension "json". Enable it or install a polyfill.',
                    'The package "composer/ca-bundle" requires the extension "json".',
                ],
            ],
        ];

        yield 'it supports polyfills' => [
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
            false,
            [
                [
                    <<<'PHP'
require_once __DIR__.'/../vendor/composer/semver/src/Semver.php';
require_once __DIR__.'/../vendor/composer/semver/src/VersionParser.php';
require_once __DIR__.'/../vendor/composer/semver/src/Constraint/ConstraintInterface.php';
require_once __DIR__.'/../vendor/composer/semver/src/Constraint/EmptyConstraint.php';
require_once __DIR__.'/../vendor/composer/semver/src/Constraint/MultiConstraint.php';
require_once __DIR__.'/../vendor/composer/semver/src/Constraint/Constraint.php';

return \Composer\Semver\Semver::satisfies(
    sprintf('%d.%d.%d', \PHP_MAJOR_VERSION, \PHP_MINOR_VERSION, \PHP_RELEASE_VERSION),
    '^7.3'
);

PHP
                    ,
                    'The application requires the version "^7.3" or greater.',
                    'The application requires the version "^7.3" or greater.',
                ],
                [
                    "return \\extension_loaded('json');",
                    'The application requires the extension "json". Enable it or install a polyfill.',
                    'The application requires the extension "json".',
                ],
                [
                    "return \\extension_loaded('json');",
                    'The package "beberlei/assert" requires the extension "json". Enable it or install a polyfill.',
                    'The package "beberlei/assert" requires the extension "json".',
                ],
                [
                    "return \\extension_loaded('json');",
                    'The package "composer/ca-bundle" requires the extension "json". Enable it or install a polyfill.',
                    'The package "composer/ca-bundle" requires the extension "json".',
                ],
            ],
        ];
    }
}
