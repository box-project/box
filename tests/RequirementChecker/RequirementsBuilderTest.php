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

use KevinGH\Box\Composer\Package\Extension;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(RequirementsBuilder::class)]
final class RequirementsBuilderTest extends TestCase
{
    public function test_it_can_build_requirements_from_an_empty_list(): void
    {
        $requirements = (new RequirementsBuilder())->build();

        $expected = new Requirements([]);

        self::assertEquals($expected, $requirements);
    }

    public function test_it_can_build_requirements_from_predefined_requirements(): void
    {
        $predefinedRequirements = [
            Requirement::forPHP('7.2', null),
            Requirement::forRequiredExtension('http', null),
            Requirement::forConflictingExtension('http', null),
        ];

        $builder = new RequirementsBuilder();

        foreach ($predefinedRequirements as $predefinedRequirement) {
            $builder->addRequirement($predefinedRequirement);
        }

        $expected = new Requirements($predefinedRequirements);
        $actual = $builder->build();

        self::assertEquals($expected, $actual);
    }

    public function test_it_can_build_requirements_from_required_extensions(): void
    {
        $builder = new RequirementsBuilder();
        $builder->addRequiredExtension(
            new Extension('http'),
            'package1',
        );
        $builder->addRequiredExtension(
            new Extension('http'),
            'package2',
        );
        $builder->addRequiredExtension(
            new Extension('phar'),
            'package1',
        );
        $builder->addRequiredExtension(
            new Extension('openssl'),
            'package3',
        );
        // Duplicate
        $builder->addRequiredExtension(
            new Extension('openssl'),
            'package3',
        );

        $expected = new Requirements([
            Requirement::forRequiredExtension('http', 'package1'),
            Requirement::forRequiredExtension('http', 'package2'),
            Requirement::forRequiredExtension('openssl', 'package3'),
            Requirement::forRequiredExtension('phar', 'package1'),
        ]);

        $actual = $builder->build();

        self::assertEquals($expected, $actual);
    }

    public function test_it_can_build_requirements_from_provided_extensions(): void
    {
        $builder = new RequirementsBuilder();
        $builder->addProvidedExtension(
            new Extension('http'),
            'package1',
        );
        $builder->addProvidedExtension(
            new Extension('http'),
            'package2',
        );

        $expected = new Requirements([]);

        $actual = $builder->build();

        self::assertEquals($expected, $actual);
    }

    public function test_it_can_build_requirements_from_conflicting_extensions(): void
    {
        $builder = new RequirementsBuilder();
        $builder->addConflictingExtension(
            new Extension('http'),
            'package1',
        );
        $builder->addConflictingExtension(
            new Extension('http'),
            'package2',
        );
        $builder->addConflictingExtension(
            new Extension('phar'),
            'package1',
        );
        $builder->addConflictingExtension(
            new Extension('openssl'),
            'package3',
        );
        // Duplicate
        $builder->addConflictingExtension(
            new Extension('openssl'),
            'package3',
        );

        $expected = new Requirements([
            Requirement::forConflictingExtension('http', 'package1'),
            Requirement::forConflictingExtension('http', 'package2'),
            Requirement::forConflictingExtension('openssl', 'package3'),
            Requirement::forConflictingExtension('phar', 'package1'),
        ]);

        $actual = $builder->build();

        self::assertEquals($expected, $actual);
    }

    public function test_it_removes_extension_requirements_if_they_are_provided(): void
    {
        $builder = new RequirementsBuilder();
        $builder->addRequiredExtension(
            new Extension('http'),
            'package1',
        );
        $builder->addRequiredExtension(
            new Extension('http'),
            'package2',
        );
        $builder->addRequiredExtension(
            new Extension('phar'),
            'package1',
        );
        $builder->addRequiredExtension(
            new Extension('openssl'),
            'package3',
        );
        $builder->addProvidedExtension(
            new Extension('http'),
            'package3',
        );

        $expected = new Requirements([
            Requirement::forRequiredExtension('openssl', 'package3'),
            Requirement::forRequiredExtension('phar', 'package1'),
        ]);

        $actual = $builder->build();

        self::assertEquals($expected, $actual);
    }

    public function test_it_does_not_remove_extension_conflicts_if_they_are_provided(): void
    {
        $builder = new RequirementsBuilder();
        $builder->addRequiredExtension(
            new Extension('http'),
            'package1',
        );
        $builder->addRequiredExtension(
            new Extension('http'),
            'package2',
        );
        $builder->addRequiredExtension(
            new Extension('phar'),
            'package1',
        );
        $builder->addRequiredExtension(
            new Extension('openssl'),
            'package3',
        );
        $builder->addProvidedExtension(
            new Extension('http'),
            'package3',
        );

        $expected = new Requirements([
            Requirement::forRequiredExtension('openssl', 'package3'),
            Requirement::forRequiredExtension('phar', 'package1'),
        ]);

        $actual = $builder->build();

        self::assertEquals($expected, $actual);
    }

    public function test_it_can_have_an_extension_that_is_required_and_conflicting_at_the_same_time(): void
    {
        // This scenario does not really make sense but ensuring this does not happen is Composer's job not Box.
        $builder = new RequirementsBuilder();
        $builder->addRequiredExtension(
            new Extension('http'),
            'package1',
        );
        $builder->addConflictingExtension(
            new Extension('http'),
            'package2',
        );

        $expected = new Requirements([
            Requirement::forRequiredExtension('http', 'package1'),
            Requirement::forConflictingExtension('http', 'package2'),
        ]);

        $actual = $builder->build();

        self::assertEquals($expected, $actual);
    }

    // TODO: this could be solved
    public function test_it_does_not_remove_predefined_requirements_even_if_they_are_provided(): void
    {
        $predefinedRequirement = Requirement::forRequiredExtension('http', null);

        $builder = new RequirementsBuilder();
        $builder->addRequirement($predefinedRequirement);
        $builder->addProvidedExtension(
            new Extension('http'),
            'package3',
        );

        $expected = new Requirements([$predefinedRequirement]);

        $actual = $builder->build();

        self::assertEquals($expected, $actual);
    }

    #[DataProvider('requirementsProvider')]
    public function test_it_ensures_the_requirements_built_are_consistent(
        array $predefinedRequirements,
        array $requiredExtensionSourcePairs,
        array $conflictingExtensionSourcePairs,
        Requirements $expected,
    ): void {
        $builder = new RequirementsBuilder();

        foreach ($predefinedRequirements as $predefinedRequirement) {
            $builder->addRequirement($predefinedRequirement);
        }

        foreach ($requiredExtensionSourcePairs as [$requiredExtension, $source]) {
            $builder->addRequiredExtension($requiredExtension, $source);
        }

        foreach ($conflictingExtensionSourcePairs as [$conflictingExtension, $source]) {
            $builder->addConflictingExtension($conflictingExtension, $source);
        }

        $actual = $builder->build();

        self::assertEquals($expected, $actual);
    }

    public static function requirementsProvider(): iterable
    {
        $predefinedRequirementA = Requirement::forRequiredExtension('http', 'A');
        $predefinedRequirementZ = Requirement::forRequiredExtension('http', 'Z');
        $predefinedRequirementNull = Requirement::forRequiredExtension('http', null);

        yield 'pre-defined requirements' => [
            [
                $predefinedRequirementZ,
                $predefinedRequirementNull,
                $predefinedRequirementA,
            ],
            [],
            [],
            new Requirements([
                $predefinedRequirementZ,
                $predefinedRequirementNull,
                $predefinedRequirementA,
            ]),
        ];

        yield 'required extension sources' => [
            [],
            [
                [new Extension('noop'), 'Z'],
                [new Extension('noop'), null],
                [new Extension('noop'), 'A'],
            ],
            [],
            new Requirements([
                Requirement::forRequiredExtension('noop', null),
                Requirement::forRequiredExtension('noop', 'A'),
                Requirement::forRequiredExtension('noop', 'Z'),
            ]),
        ];

        yield 'required extensions' => [
            [],
            [
                [new Extension('z-ext'), null],
                [new Extension('a-ext'), null],
            ],
            [],
            new Requirements([
                Requirement::forRequiredExtension('a-ext', null),
                Requirement::forRequiredExtension('z-ext', null),
            ]),
        ];

        yield 'conflicting extension sources' => [
            [],
            [],
            [
                [new Extension('noop'), 'Z'],
                [new Extension('noop'), null],
                [new Extension('noop'), 'A'],
            ],
            new Requirements([
                Requirement::forConflictingExtension('noop', null),
                Requirement::forConflictingExtension('noop', 'A'),
                Requirement::forConflictingExtension('noop', 'Z'),
            ]),
        ];

        yield 'conflicting extensions' => [
            [],
            [],
            [
                [new Extension('z-ext'), null],
                [new Extension('a-ext'), null],
            ],
            new Requirements([
                Requirement::forConflictingExtension('a-ext', null),
                Requirement::forConflictingExtension('z-ext', null),
            ]),
        ];
    }
}
