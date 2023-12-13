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

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(Requirements::class)]
final class RequirementsTest extends TestCase
{
    #[DataProvider('requirementsProvider')]
    public function test_it_can_be_cast_into_an_array(
        Requirements $requirements,
        array $expected,
    ): void {
        $actual = $requirements->toArray();

        self::assertSame($expected, $actual);
    }

    public static function requirementsProvider(): iterable
    {
        yield 'empty' => [
            new Requirements([]),
            [],
        ];

        yield 'nominal' => [
            new Requirements([
                Requirement::forPHP('7.2', null),
                Requirement::forRequiredExtension('http', null),
            ]),
            [
                [
                    'type' => 'php',
                    'condition' => '7.2',
                    'source' => null,
                    'message' => 'This application requires a PHP version matching "7.2".',
                    'helpMessage' => 'This application requires a PHP version matching "7.2".',
                ],
                [
                    'type' => 'extension',
                    'condition' => 'http',
                    'source' => null,
                    'message' => 'This application requires the extension "http".',
                    'helpMessage' => 'This application requires the extension "http". You either need to enable it or request the application to be shipped with a polyfill for this extension.',
                ],
            ],
        ];

        yield 'non list' => [
            new Requirements([
                10 => Requirement::forPHP('7.2', null),
                20 => Requirement::forRequiredExtension('http', null),
            ]),
            [
                [
                    'type' => 'php',
                    'condition' => '7.2',
                    'source' => null,
                    'message' => 'This application requires a PHP version matching "7.2".',
                    'helpMessage' => 'This application requires a PHP version matching "7.2".',
                ],
                [
                    'type' => 'extension',
                    'condition' => 'http',
                    'source' => null,
                    'message' => 'This application requires the extension "http".',
                    'helpMessage' => 'This application requires the extension "http". You either need to enable it or request the application to be shipped with a polyfill for this extension.',
                ],
            ],
        ];
    }
}
