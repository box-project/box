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
        array $expectedRequiredItems,
        array $expectedConflictingExtensions,
    ): void {
        $actual = new DecodedComposerJson(json_decode($composerJsonContents, true));

        self::assertStateIs(
            $actual,
            $expectedRequiredPhpVersion,
            $expectedHasRequiredPhpVersion,
            $expectedRequiredItems,
            $expectedConflictingExtensions,
        );
    }

    public static function composerJsonProvider(): iterable
    {
        yield 'empty json file' => [
            '{}',
            null,
            false,
            [],
            [],
        ];

        yield 'PHP platform requirements' => [
            <<<'JSON'
                {
                    "require": {
                        "php": "^7.1",
                        "ext-phar": "*"
                    },
                    "require-dev": []
                }
                JSON,
            '^7.1',
            true,
            [
                new RequiredItem(['php' => '^7.1']),
                new RequiredItem(['ext-phar' => '*']),
            ],
            [],
        ];

        yield 'PHP platform-dev requirements' => [
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
            false,
            [],
            [],
        ];

        yield 'packages required' => [
            <<<'JSON'
                {
                    "require": {
                        "beberlei/assert": "^2.9",
                        "composer/ca-bundle": "^1.1"
                    },
                    "require-dev": {
                        "webmozarts/assert": "^3.2"
                    }
                }
                JSON,
            null,
            false,
            [
                new RequiredItem(['beberlei/assert' => '^2.9']),
                new RequiredItem(['composer/ca-bundle' => '^1.1']),
            ],
            [],
        ];

        yield 'conflicts' => [
            <<<'JSON'
                {
                    "conflict": {
                        "psr/logger": ">=7.1",
                        "ext-phar": "*"
                    }
                }
                JSON,
            null,
            false,
            [],
            ['phar'],
        ];

        yield 'nominal' => [
            <<<'JSON'
                {
                    "require": {
                        "php": "^7.3",
                        "beberlei/assert": "^2.9",
                        "ext-http": "*",
                        "composer/ca-bundle": "^1.1"
                    },
                    "require-dev": {
                        "webmozarts/assert": "^3.2"
                    },
                    "conflict": {
                        "ext-http": "*"
                    }
                }
                JSON,
            '^7.3',
            true,
            [
                new RequiredItem(['php' => '^7.3']),
                new RequiredItem(['beberlei/assert' => '^2.9']),
                new RequiredItem(['ext-http' => '*']),
                new RequiredItem(['composer/ca-bundle' => '^1.1']),
            ],
            ['http'],
        ];
    }

    private static function assertStateIs(
        DecodedComposerJson $composerJson,
        ?string $expectedRequiredPhpVersion,
        bool $expectedHasRequiredPhpVersion,
        array $expectedRequiredItems,
        array $expectedConflictingExtensions,
    ): void {
        self::assertSame($expectedRequiredPhpVersion, $composerJson->getRequiredPhpVersion());
        self::assertSame($expectedHasRequiredPhpVersion, $composerJson->hasRequiredPhpVersion());
        self::assertEquals($expectedRequiredItems, $composerJson->getRequiredItems());
        self::assertSame($expectedConflictingExtensions, $composerJson->getConflictingExtensions());
    }
}
