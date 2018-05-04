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
 * @covers \KevinGH\Box\RequirementChecker\AppRequirementsFactory
 */
class AppRequirementsFactoryTest extends TestCase
{
    /**
     * @dataProvider provideLockContents
     */
    public function test_it_can_generate_and_serialized_requirements_from_a_composer_lock_file(
        ?string $composerJsonContents,
        ?string $composerLockContents,
        bool $compressed,
        array $expected
    ): void {
        $actual = AppRequirementsFactory::create(
            null === $composerJsonContents ? [] : json_decode($composerJsonContents, true),
            null === $composerLockContents ? [] : json_decode($composerLockContents, true),
            $compressed
        );

        $this->assertSame($expected, $actual);
    }

    public function provideLockContents()
    {
        yield 'empty json file' => [
            '{}',
            null,
            false,
            [],
        ];

        yield 'empty lock file' => [
            null,
            '{}',
            false,
            [],
        ];

        yield 'empty json & lock file' => [
            '{}',
            '{}',
            false,
            [],
        ];

        yield 'empty json file (compressed PHAR)' => [
            '{}',
            null,
            true,
            [
                [
                    "return \\extension_loaded('zip');",
                    'The application requires the extension "zip". Enable it or install a polyfill.',
                    'The application requires the extension "zip".',
                ],
            ],
        ];

        yield 'empty lock file (compressed PHAR)' => [
            null,
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

        yield 'empty json & lock file (compressed PHAR)' => [
            '{}',
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
            false,
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
            false,
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
            false,
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
            true,
            [
                [
                    "return \\extension_loaded('zip');",
                    'The application requires the extension "zip". Enable it or install a polyfill.',
                    'The application requires the extension "zip".',
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
            true,
            [
                [
                    "return \\extension_loaded('zip');",
                    'The application requires the extension "zip". Enable it or install a polyfill.',
                    'The application requires the extension "zip".',
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
            true,
            [
                [
                    "return \\extension_loaded('zip');",
                    'The application requires the extension "zip". Enable it or install a polyfill.',
                    'The application requires the extension "zip".',
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
                    'The application requires the version ">=5.3" or greater.',
                    'The application requires the version ">=5.3" or greater.',
                ],
                [
                    "return \\extension_loaded('mbstring');",
                    'The application requires the extension "mbstring". Enable it or install a polyfill.',
                    'The application requires the extension "mbstring".',
                ],
                [
                    "return \\extension_loaded('openssl');",
                    'The application requires the extension "openssl". Enable it or install a polyfill.',
                    'The application requires the extension "openssl".',
                ],
                [
                    "return \\extension_loaded('pcre');",
                    'The application requires the extension "pcre". Enable it or install a polyfill.',
                    'The application requires the extension "pcre".',
                ],
                [
                    "return \\extension_loaded('pdo_sqlite3');",
                    'The application requires the extension "pdo_sqlite3". Enable it or install a polyfill.',
                    'The application requires the extension "pdo_sqlite3".',
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
            false,
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
            false,
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
            false,
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
            false,
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
            false,
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
            false,
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
            false,
            [],
        ];
    }
}
