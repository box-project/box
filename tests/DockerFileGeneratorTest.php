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

use PHPUnit\Framework\TestCase;
use UnexpectedValueException;

/**
 * @covers \KevinGH\Box\DockerFileGenerator
 *
 * @internal
 */
class DockerFileGeneratorTest extends TestCase
{
    /**
     * @dataProvider generatorDataProvider
     */
    public function test_it_can_generate_a_dockerfile_contents(
        string $image,
        array $extensions,
        string $sourcePhar,
        string $expected,
    ): void {
        $actual = (new DockerFileGenerator($image, $extensions, $sourcePhar))->generateStub();

        self::assertSame($expected, $actual);
    }

    /**
     * @dataProvider generatorRequirementsProvider
     */
    public function test_it_can_generate_a_dockerfile_contents_from_requirements(
        array $requirements,
        string $sourcePhar,
        string $expected,
    ): void {
        $actual = DockerFileGenerator::createForRequirements($requirements, $sourcePhar)->generateStub();

        self::assertSame($expected, $actual);
    }

    public function test_throws_an_error_if_cannot_find_a_suitable_php_image(): void
    {
        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage('Could not find a suitable Docker base image for the PHP constraint(s) "^5.3". Images available: "8.2-cli-alpine", "8.1-cli-alpine", "8.0-cli-alpine", "7.4-cli-alpine", "7.3-cli-alpine", "7.2-cli-alpine", "7.1-cli-alpine", "7-cli-alpine".');

        DockerFileGenerator::createForRequirements(
            [
                [
                    'type' => 'php',
                    'condition' => '^5.3',
                ],
            ],
            'path/to/phar',
        );
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

        yield 'multple extensions' => [
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
    }
}
