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

namespace KevinGH\Box;

use KevinGH\Box\RequirementChecker\Requirement;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use function sprintf;
use const PHP_MAJOR_VERSION;
use const PHP_MINOR_VERSION;

/**
 * @internal
 */
#[CoversClass(DockerFileGenerator::class)]
class DockerFileGeneratorTest extends TestCase
{
    #[DataProvider('generatorDataProvider')]
    public function test_it_can_generate_a_dockerfile_contents(
        string $image,
        array $extensions,
        string $sourcePhar,
        string $expected,
    ): void {
        $actual = (new DockerFileGenerator($image, $extensions, $sourcePhar))->generateStub();

        self::assertSame($expected, $actual);
    }

    #[DataProvider('generatorRequirementsProvider')]
    public function test_it_can_generate_a_dockerfile_contents_from_requirements(
        array $requirements,
        string $sourcePhar,
        string $expected,
    ): void {
        $actual = DockerFileGenerator::createForRequirements($requirements, $sourcePhar)->generateStub();

        self::assertSame($expected, $actual);
    }

    public static function generatorDataProvider(): iterable
    {
        yield 'no extension' => [
            '7.2-cli-alpine',
            [],
            'box.phar',
            <<<'Dockerfile'
                FROM php:7.2-cli-alpine

                COPY --from=mlocati/php-extension-installer /usr/bin/install-php-extensions /usr/local/bin/

                COPY box.phar /box.phar

                ENTRYPOINT ["/box.phar"]

                Dockerfile,
        ];

        yield 'no extension with absolute path' => [
            '7.2-cli-alpine',
            [],
            '/path/to/box.phar',
            <<<'Dockerfile'
                FROM php:7.2-cli-alpine

                COPY --from=mlocati/php-extension-installer /usr/bin/install-php-extensions /usr/local/bin/

                COPY /path/to/box.phar /box.phar

                ENTRYPOINT ["/box.phar"]

                Dockerfile,
        ];

        yield 'no extension with phar that does not have the PHAR suffix' => [
            '7.2-cli-alpine',
            [],
            'box',
            <<<'Dockerfile'
                FROM php:7.2-cli-alpine

                COPY --from=mlocati/php-extension-installer /usr/bin/install-php-extensions /usr/local/bin/

                COPY box /box

                ENTRYPOINT ["/box"]

                Dockerfile,
        ];

        yield 'single extension' => [
            '7.2-cli-alpine',
            ['zip'],
            'box.phar',
            <<<'Dockerfile'
                FROM php:7.2-cli-alpine

                COPY --from=mlocati/php-extension-installer /usr/bin/install-php-extensions /usr/local/bin/
                RUN install-php-extensions zip

                COPY box.phar /box.phar

                ENTRYPOINT ["/box.phar"]

                Dockerfile,
        ];

        yield 'multiple extensions' => [
            '7.2-cli-alpine',
            ['phar', 'gzip'],
            'box.phar',
            <<<'Dockerfile'
                FROM php:7.2-cli-alpine

                COPY --from=mlocati/php-extension-installer /usr/bin/install-php-extensions /usr/local/bin/
                RUN install-php-extensions phar gzip

                COPY box.phar /box.phar

                ENTRYPOINT ["/box.phar"]

                Dockerfile,
        ];
    }

    public static function generatorRequirementsProvider(): iterable
    {
        yield 'nominal' => [
            [
                [
                    'type' => 'php',
                    'condition' => '^7.1',
                    'message' => 'This application requires the PHP version "^7.1" or greater.',
                    'helpMessage' => 'This application requires the PHP version "^7.1" or greater.',
                ],
                [
                    'type' => 'extension',
                    'condition' => 'zlib',
                    'message' => 'This application requires the extension "zlib". Enable it or install a polyfill.',
                    'helpMessage' => 'This application requires the extension "zlib".',
                ],
                [
                    'type' => 'extension',
                    'condition' => 'phar',
                    'message' => 'This application requires the extension "phar". Enable it or install a polyfill.',
                    'helpMessage' => 'This application requires the extension "phar".',
                ],
                [
                    'type' => 'extension',
                    'condition' => 'openssl',
                    'message' => 'The package "composer/ca-bundle" requires the extension "openssl". Enable it or install a polyfill.',
                    'helpMessage' => 'The package "composer/ca-bundle" requires the extension "openssl".',
                ],
                [
                    'type' => 'extension',
                    'condition' => 'pcre',
                    'message' => 'The package "composer/ca-bundle" requires the extension "pcre". Enable it or install a polyfill.',
                    'helpMessage' => 'The package "composer/ca-bundle" requires the extension "pcre".',
                ],
                [
                    'type' => 'extension',
                    'condition' => 'tokenizer',
                    'message' => 'The package "nikic/php-parser" requires the extension "tokenizer". Enable it or install a polyfill.',
                    'helpMessage' => 'The package "nikic/php-parser" requires the extension "tokenizer".',
                ],
            ],
            'box.phar',
            <<<'Dockerfile'
                FROM php:7.4-cli-alpine

                COPY --from=mlocati/php-extension-installer /usr/bin/install-php-extensions /usr/local/bin/
                RUN install-php-extensions zlib phar openssl pcre tokenizer

                COPY box.phar /box.phar

                ENTRYPOINT ["/box.phar"]

                Dockerfile,
        ];

        yield 'multiple PHP constraints' => [
            [
                [
                    'type' => 'php',
                    'condition' => '^7.1',
                    'message' => 'This application requires the PHP version "^7.1" or greater.',
                    'helpMessage' => 'This application requires the PHP version "^7.1" or greater.',
                ],
                [
                    'type' => 'php',
                    'condition' => '~7.1.0',
                    'message' => 'This application requires the PHP version "^7.1" or greater.',
                    'helpMessage' => 'This application requires the PHP version "^7.1" or greater.',
                ],
                [
                    'type' => 'extension',
                    'condition' => 'zlib',
                    'message' => 'This application requires the extension "zlib". Enable it or install a polyfill.',
                    'helpMessage' => 'This application requires the extension "zlib".',
                ],
            ],
            'box.phar',
            <<<'Dockerfile'
                FROM php:7.1-cli-alpine

                COPY --from=mlocati/php-extension-installer /usr/bin/install-php-extensions /usr/local/bin/
                RUN install-php-extensions zlib

                COPY box.phar /box.phar

                ENTRYPOINT ["/box.phar"]

                Dockerfile,
        ];

        yield 'only PHP constraints' => [
            [
                [
                    'type' => 'php',
                    'condition' => '^7.1',
                    'message' => 'This application requires the PHP version "^7.1" or greater.',
                    'helpMessage' => 'This application requires the PHP version "^7.1" or greater.',
                ],
                [
                    'type' => 'php',
                    'condition' => '~7.1.0',
                    'message' => 'This application requires the PHP version "^7.1" or greater.',
                    'helpMessage' => 'This application requires the PHP version "^7.1" or greater.',
                ],
            ],
            'box.phar',
            <<<'Dockerfile'
                FROM php:7.1-cli-alpine

                COPY --from=mlocati/php-extension-installer /usr/bin/install-php-extensions /usr/local/bin/

                COPY box.phar /box.phar

                ENTRYPOINT ["/box.phar"]

                Dockerfile,
        ];

        yield 'duplicate extension constraints' => [
            [
                [
                    'type' => 'php',
                    'condition' => '^7.1',
                    'message' => 'This application requires the PHP version "^7.1" or greater.',
                    'helpMessage' => 'This application requires the PHP version "^7.1" or greater.',
                ],
                [
                    'type' => 'php',
                    'condition' => '~7.1.0',
                    'message' => 'This application requires the PHP version "^7.1" or greater.',
                    'helpMessage' => 'This application requires the PHP version "^7.1" or greater.',
                ],
                [
                    'type' => 'extension',
                    'condition' => 'zlib',
                    'message' => 'This application requires the extension "zlib". Enable it or install a polyfill.',
                    'helpMessage' => 'This application requires the extension "zlib".',
                ],
                [
                    'type' => 'extension',
                    'condition' => 'filter',
                    'message' => 'The package "nikic/php-parser" requires the extension "filter". Enable it or install a polyfill.',
                    'helpMessage' => 'The package "nikic/php-parser" requires the extension "filter".',
                ],
                [
                    'type' => 'extension',
                    'condition' => 'filter',
                    'message' => 'The package "phpdocumentor/reflection-docblock" requires the extension "filter". Enable it or install a polyfill.',
                    'helpMessage' => 'The package "phpdocumentor/reflection-docblock" requires the extension "filter".',
                ],
            ],
            'box.phar',
            <<<'Dockerfile'
                FROM php:7.1-cli-alpine

                COPY --from=mlocati/php-extension-installer /usr/bin/install-php-extensions /usr/local/bin/
                RUN install-php-extensions zlib filter

                COPY box.phar /box.phar

                ENTRYPOINT ["/box.phar"]

                Dockerfile,
        ];

        yield 'old PHP constraints (no existent PHP official image)' => [
            [
                Requirement::forPHP('^5.3', null)->toArray(),
            ],
            'box.phar',
            <<<'Dockerfile'
                FROM php:to-define-manually

                COPY --from=mlocati/php-extension-installer /usr/bin/install-php-extensions /usr/local/bin/

                COPY box.phar /box.phar

                ENTRYPOINT ["/box.phar"]

                Dockerfile,
        ];

        yield 'new non-known PHP constraints' => [
            [
                Requirement::forPHP('^999.0', null)->toArray(),
            ],
            'box.phar',
            <<<'Dockerfile'
                FROM php:to-define-manually

                COPY --from=mlocati/php-extension-installer /usr/bin/install-php-extensions /usr/local/bin/

                COPY box.phar /box.phar

                ENTRYPOINT ["/box.phar"]

                Dockerfile,
        ];

        $currentPhpMajorVersion = PHP_MAJOR_VERSION;
        $currentPhpMinorVersion = PHP_MINOR_VERSION;

        yield 'current PHP constraints' => [
            [
                Requirement::forPHP(
                    sprintf(
                        '~%s.%s.0',
                        $currentPhpMajorVersion,
                        $currentPhpMinorVersion,
                    ),
                    null,
                )
                    ->toArray(),
            ],
            'box.phar',
            <<<Dockerfile
                FROM php:{$currentPhpMajorVersion}.{$currentPhpMinorVersion}-cli-alpine

                COPY --from=mlocati/php-extension-installer /usr/bin/install-php-extensions /usr/local/bin/

                COPY box.phar /box.phar

                ENTRYPOINT ["/box.phar"]

                Dockerfile,
        ];
    }
}
