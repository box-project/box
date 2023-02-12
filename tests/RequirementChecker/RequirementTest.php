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
            'message' => 'The application requires a version matching "^8.2".',
            'helpMessage' => 'The application requires a version matching "^8.2".',
        ];

        self::assertSame($expected, $requirement->toArray());
    }

    public function test_it_can_be_created_for_a_php_version_for_a_package(): void
    {
        $requirement = Requirement::forPHP('^8.2', 'box/test');

        $expected = [
            'type' => 'php',
            'condition' => '^8.2',
            'message' => 'The package "box/test" requires a version matching "^8.2".',
            'helpMessage' => 'The package "box/test" requires a version matching "^8.2".',
        ];

        self::assertSame($expected, $requirement->toArray());
    }

    public function test_it_can_be_created_for_an_extension_constraint(): void
    {
        $requirement = Requirement::forRequiredExtension('mbstring', null);

        $expected = [
            'type' => 'extension',
            'condition' => 'mbstring',
            'message' => 'The application requires the extension "mbstring". Enable it or install a polyfill.',
            'helpMessage' => 'The application requires the extension "mbstring".',
        ];

        self::assertSame($expected, $requirement->toArray());
    }

    public function test_it_can_be_created_for_an_extension_constraint_for_a_package(): void
    {
        $requirement = Requirement::forRequiredExtension('mbstring', 'box/test');

        $expected = [
            'type' => 'extension',
            'condition' => 'mbstring',
            'message' => 'The package "box/test" requires the extension "mbstring". Enable it or install a polyfill.',
            'helpMessage' => 'The package "box/test" requires the extension "mbstring".',
        ];

        self::assertSame($expected, $requirement->toArray());
    }

    public function test_it_can_be_created_for_a_conflicting_extension_constraint(): void
    {
        $requirement = Requirement::forConflictingExtension('mbstring', null);

        $expected = [
            'type' => 'extension-conflict',
            'condition' => 'mbstring',
            'message' => 'The application conflicts with the extension "mbstring".',
            'helpMessage' => 'The application conflicts with the extension "mbstring". Disable it.',
        ];

        self::assertSame($expected, $requirement->toArray());
    }

    public function test_it_can_be_created_for_a_conflicting_extension_constraint_for_a_package(): void
    {
        $requirement = Requirement::forConflictingExtension('mbstring', 'box/test');

        $expected = [
            'type' => 'extension-conflict',
            'condition' => 'mbstring',
            'message' => 'The package "box/test" conflicts with the extension "mbstring".',
            'helpMessage' => 'The package "box/test" conflicts with the extension "mbstring". Disable it.',
        ];

        self::assertSame($expected, $requirement->toArray());
    }
}
