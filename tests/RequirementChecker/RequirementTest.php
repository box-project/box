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
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(Requirement::class)]
final class RequirementTest extends TestCase
{
    public function test_it_can_be_created_for_a_php_version(): void
    {
        $requirement = Requirement::forPHP('^8.2', null);

        $expected = [
            'type' => 'php',
            'condition' => '^8.2',
            'source' => null,
            'message' => 'This application requires a PHP version matching "^8.2".',
            'helpMessage' => 'This application requires a PHP version matching "^8.2".',
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
            'message' => 'The package "box/test" requires a PHP version matching "^8.2".',
            'helpMessage' => 'The package "box/test" requires a PHP version matching "^8.2".',
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
            'message' => 'This application requires the extension "mbstring".',
            'helpMessage' => 'This application requires the extension "mbstring". You either need to enable it or request the application to be shipped with a polyfill for this extension.',
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

    public function test_it_can_be_created_for_a_provided_extension_constraint(): void
    {
        $requirement = Requirement::forProvidedExtension('mbstring', null);

        $expected = [
            'type' => 'provided-extension',
            'condition' => 'mbstring',
            'source' => null,
            'message' => 'This application provides the extension "mbstring".',
            'helpMessage' => 'This application does not require the extension "mbstring", it is provided by the application itself.',
        ];

        $actual = $requirement->toArray();

        self::assertSame($expected, $actual);
        self::assertItCanBeCreatedFromItsArrayForm($requirement, $actual);
    }

    public function test_it_can_be_created_for_a_provided_extension_constraint_for_a_package(): void
    {
        $requirement = Requirement::forProvidedExtension('mbstring', 'box/test');

        $expected = [
            'type' => 'provided-extension',
            'condition' => 'mbstring',
            'source' => 'box/test',
            'message' => 'The package "box/test" provides the extension "mbstring".',
            'helpMessage' => 'This application does not require the extension "box/test", it is provided by the package "mbstring".',
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
            'message' => 'This application conflicts with the extension "mbstring".',
            'helpMessage' => 'This application conflicts with the extension "mbstring". You need to disable it in order to run this application.',
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

    public function test_it_can_be_created_for_a_legacy_requirement(): void
    {
        $expected = new Requirement(
            RequirementType::EXTENSION_CONFLICT,
            'mbstring',
            null,
            'The package "box/test" conflicts with the extension "mbstring".',
            'The package "box/test" conflicts with the extension "mbstring". You need to disable it in order to run this application.',
        );

        $actual = Requirement::fromArray([
            'type' => 'extension-conflict',
            'condition' => 'mbstring',
            'message' => 'The package "box/test" conflicts with the extension "mbstring".',
            'helpMessage' => 'The package "box/test" conflicts with the extension "mbstring". You need to disable it in order to run this application.',
        ]);

        self::assertEquals($expected, $actual);
    }

    private static function assertItCanBeCreatedFromItsArrayForm(Requirement $expected, array $arrayForm): void
    {
        $actual = Requirement::fromArray($arrayForm);

        self::assertEquals($expected, $actual);
    }
}
