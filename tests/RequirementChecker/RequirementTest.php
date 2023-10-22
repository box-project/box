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

/**
 * @covers \KevinGH\Box\RequirementChecker\Requirement
 *
 * @internal
 */
final class RequirementTest extends TestCase
{
    public function test_it_can_be_created_for_a_php_version(): void
    {
        $requirement = Requirement::forPHP('^8.2', null);

        $expected = [
            'type' => 'php',
            'condition' => '^8.2',
            'source' => null,
            'message' => 'The application requires a version matching "^8.2".',
            'helpMessage' => 'The application requires a version matching "^8.2".',
        ];

        $actual = $requirement->toArray();

        self::assertSame($expected, $actual);
        self::assertItCanBeCreatedFromItsArrayForm($requirement, $actual);
    }

    public function test_it_can_be_created_for_a_php_version_for_a_package(): void
    {
        $requirement = Requirement::forPHP('^8.2', 'box/test');

        $expected = [
            'type' => 'php',
            'condition' => '^8.2',
            'source' => 'box/test',
            'message' => 'The package "box/test" requires a version matching "^8.2".',
            'helpMessage' => 'The package "box/test" requires a version matching "^8.2".',
        ];

        $actual = $requirement->toArray();

        self::assertSame($expected, $actual);
        self::assertItCanBeCreatedFromItsArrayForm($requirement, $actual);
    }

    public function test_it_can_be_created_for_an_extension_constraint(): void
    {
        $requirement = Requirement::forRequiredExtension('mbstring', null);

        $expected = [
            'type' => 'extension',
            'condition' => 'mbstring',
            'source' => null,
            'message' => 'The application requires the extension "mbstring".',
            'helpMessage' => 'The application requires the extension "mbstring". You either need to enable it or request the application to be shipped with a polyfill for this extension.',
        ];

        $actual = $requirement->toArray();

        self::assertSame($expected, $actual);
        self::assertItCanBeCreatedFromItsArrayForm($requirement, $actual);
    }

    public function test_it_can_be_created_for_an_extension_constraint_for_a_package(): void
    {
        $requirement = Requirement::forRequiredExtension('mbstring', 'box/test');

        $expected = [
            'type' => 'extension',
            'condition' => 'mbstring',
            'source' => 'box/test',
            'message' => 'The package "box/test" requires the extension "mbstring".',
            'helpMessage' => 'The package "box/test" requires the extension "mbstring". You either need to enable it or request the application to be shipped with a polyfill for this extension.',
        ];

        $actual = $requirement->toArray();

        self::assertSame($expected, $actual);
        self::assertItCanBeCreatedFromItsArrayForm($requirement, $actual);
    }

    public function test_it_can_be_created_for_a_conflicting_extension_constraint(): void
    {
        $requirement = Requirement::forConflictingExtension('mbstring', null);

        $expected = [
            'type' => 'extension-conflict',
            'condition' => 'mbstring',
            'source' => null,
            'message' => 'The application conflicts with the extension "mbstring".',
            'helpMessage' => 'The application conflicts with the extension "mbstring". You need to disable it in order to run this application.',
        ];

        $actual = $requirement->toArray();

        self::assertSame($expected, $actual);
        self::assertItCanBeCreatedFromItsArrayForm($requirement, $actual);
    }

    public function test_it_can_be_created_for_a_conflicting_extension_constraint_for_a_package(): void
    {
        $requirement = Requirement::forConflictingExtension('mbstring', 'box/test');

        $expected = [
            'type' => 'extension-conflict',
            'condition' => 'mbstring',
            'source' => 'box/test',
            'message' => 'The package "box/test" conflicts with the extension "mbstring".',
            'helpMessage' => 'The package "box/test" conflicts with the extension "mbstring". You need to disable it in order to run this application.',
        ];

        $actual = $requirement->toArray();

        self::assertSame($expected, $actual);
        self::assertItCanBeCreatedFromItsArrayForm($requirement, $actual);
    }

    private static function assertItCanBeCreatedFromItsArrayForm(Requirement $expected, array $arrayForm): void
    {
        $actual = Requirement::fromArray($arrayForm);

        self::assertEquals($expected, $actual);
    }
}
