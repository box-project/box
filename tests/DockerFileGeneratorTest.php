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
 */
class DockerFileGeneratorTest extends TestCase
{
    /**
     * @dataProvider generatorDataProvider
     */
    public function test_all_extension_names_are_in_dockerfile(
        string $image,
        array $extensions,
        string $sourcePhar,

    ): void {
        $stub = (new DockerFileGenerator($image, $extensions, $sourcePhar))->generateStub();
        foreach($extensions as $extension) {
            self::assertStringContainsString($extension, $stub);
        }
    }

    /**
     * @dataProvider generatorRequirementsProvider
     */
    public function test_it_can_generate_a_dockerfile_contents_from_requirements(
        array $requirements,
        string $sourcePhar,
        string $expectedBaseImage
    ): void {
        $actual = DockerFileGenerator::createForRequirements($requirements, $sourcePhar)->generateStub();

        $this->assertStringContainsString($sourcePhar, $actual);
        foreach($requirements as $requirementDefinition) {
            if ($requirementDefinition['type'] === 'extension') {
                $this->assertStringContainsString($requirementDefinition['condition'], $actual);
            }
        }

        $this->assertStringContainsString("FROM php:$expectedBaseImage", $actual);
    }

    public function test_throws_an_error_if_cannot_find_a_suitable_php_image(): void
    {
        try {
            DockerFileGenerator::createForRequirements(
                [
                        [
                            'type' => 'php',
                            'condition' => '^5.3',
                        ],
                    ],
                'path/to/phar',
            )
                ->generateStub()
            ;
        } catch (UnexpectedValueException $exception) {
            $this->assertSame(
                'Could not find a suitable Docker base image for the PHP constraint(s) "^5.3". Images available: "8.1-cli-alpine", "8.0-cli-alpine", "7.4-cli-alpine", "7.3-cli-alpine", "7.2-cli-alpine", "7.1-cli-alpine", "7-cli-alpine"',
                $exception->getMessage(),
            );
        }
    }

    public static function generatorDataProvider(): iterable
    {
        yield [
            '7.2-cli-alpine',
            ['zip'],
            'box.phar'
        ];

        yield [
            '7.2-cli-alpine',
            ['phar', 'gzip'],
            '/path/to/box'
        ];
    }

    public static function generatorRequirementsProvider(): iterable
    {
        yield [
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
            '7.4-cli-alpine'
        ];

        yield [
            [
                [
                    'type' => 'php',
                    'condition' => '^7.1',
                    'message' => 'The application requires the version "^7.1" or greater.',
                    'helpMessage' => 'The application requires the version "^7.1" or greater.',
                ],
                [
                    'type' => 'php',
                    'condition' => '~7.1.0',
                    'message' => 'The application requires the version "^7.1" or greater.',
                    'helpMessage' => 'The application requires the version "^7.1" or greater.',
                ],
                [
                    'type' => 'extension',
                    'condition' => 'zlib',
                    'message' => 'The application requires the extension "zlib". Enable it or install a polyfill.',
                    'helpMessage' => 'The application requires the extension "zlib".',
                ],
            ],
            'box.phar',
            '7.1-cli-alpine'
        ];
    }
}
