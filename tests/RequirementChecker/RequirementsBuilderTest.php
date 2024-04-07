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
    private RequirementsBuilder $builder;

    protected function setUp(): void
    {
        $this->builder = new RequirementsBuilder();
    }

    public function test_it_can_build_requirements_from_an_empty_list(): void
    {
        $expected = new Requirements([]);

        $this->assertBuiltRequirementsEquals($expected);
        $this->assertAllRequirementsEquals($expected);
    }

    public function test_it_can_build_requirements_from_predefined_requirements(): void
    {
        $predefinedRequirements = [
            Requirement::forPHP('7.2', null),
            Requirement::forRequiredExtension('http', null),
            Requirement::forConflictingExtension('http', null),
        ];

        foreach ($predefinedRequirements as $predefinedRequirement) {
            $this->builder->addRequirement($predefinedRequirement);
        }

        $expected = new Requirements($predefinedRequirements);

        $this->assertBuiltRequirementsEquals($expected);
        $this->assertAllRequirementsEquals($expected);
    }

    public function test_it_can_build_requirements_from_required_extensions(): void
    {
        $this->builder->addRequiredExtension(
            new Extension('http'),
            'package1',
        );
        $this->builder->addRequiredExtension(
            new Extension('http'),
            'package2',
        );
        $this->builder->addRequiredExtension(
            new Extension('http'),
            null,
        );
        $this->builder->addRequiredExtension(
            new Extension('phar'),
            'package1',
        );
        $this->builder->addRequiredExtension(
            new Extension('openssl'),
            'package3',
        );
        // Duplicate
        $this->builder->addRequiredExtension(
            new Extension('openssl'),
            'package3',
        );

        $expected = new Requirements([
            Requirement::forRequiredExtension('http', null),
            Requirement::forRequiredExtension('http', 'package1'),
            Requirement::forRequiredExtension('http', 'package2'),
            Requirement::forRequiredExtension('openssl', 'package3'),
            Requirement::forRequiredExtension('phar', 'package1'),
        ]);

        $this->assertBuiltRequirementsEquals($expected);
        $this->assertAllRequirementsEquals($expected);
    }

    public function test_it_can_build_requirements_from_provided_extensions(): void
    {
        $this->builder->addProvidedExtension(
            new Extension('http'),
            'package1',
        );
        $this->builder->addProvidedExtension(
            new Extension('http'),
            'package2',
        );
        $this->builder->addProvidedExtension(
            new Extension('http'),
            null,
        );

        $expectedBuiltRequirements = new Requirements([]);
        $expectedAllRequirements = new Requirements([
            Requirement::forProvidedExtension('http', null),
            Requirement::forProvidedExtension('http', 'package1'),
            Requirement::forProvidedExtension('http', 'package2'),
        ]);

        $this->assertBuiltRequirementsEquals($expectedBuiltRequirements);
        $this->assertAllRequirementsEquals($expectedAllRequirements);
    }

    public function test_it_can_build_requirements_from_provided_extensions_sorting_edge_case(): void
    {
        $this->builder->addProvidedExtension(
            new Extension('http'),
            null,
        );
        $this->builder->addProvidedExtension(
            new Extension('http'),
            'package1',
        );
        $this->builder->addProvidedExtension(
            new Extension('http'),
            'package2',
        );

        $expectedBuiltRequirements = new Requirements([]);
        $expectedAllRequirements = new Requirements([
            Requirement::forProvidedExtension('http', null),
            Requirement::forProvidedExtension('http', 'package1'),
            Requirement::forProvidedExtension('http', 'package2'),
        ]);

        $this->assertBuiltRequirementsEquals($expectedBuiltRequirements);
        $this->assertAllRequirementsEquals($expectedAllRequirements);
    }

    public function test_it_can_build_requirements_from_conflicting_extensions(): void
    {
        $this->builder->addConflictingExtension(
            new Extension('http'),
            'package1',
        );
        $this->builder->addConflictingExtension(
            new Extension('http'),
            'package2',
        );
        $this->builder->addConflictingExtension(
            new Extension('phar'),
            'package1',
        );
        $this->builder->addConflictingExtension(
            new Extension('openssl'),
            'package3',
        );
        // Duplicate
        $this->builder->addConflictingExtension(
            new Extension('openssl'),
            'package3',
        );

        $expected = new Requirements([
            Requirement::forConflictingExtension('http', 'package1'),
            Requirement::forConflictingExtension('http', 'package2'),
            Requirement::forConflictingExtension('openssl', 'package3'),
            Requirement::forConflictingExtension('phar', 'package1'),
        ]);

        $this->assertBuiltRequirementsEquals($expected);
        $this->assertAllRequirementsEquals($expected);
    }

    public function test_it_removes_extension_requirements_if_they_are_provided(): void
    {
        $this->builder->addRequiredExtension(
            new Extension('http'),
            'package1',
        );
        $this->builder->addRequiredExtension(
            new Extension('http'),
            'package2',
        );
        $this->builder->addRequiredExtension(
            new Extension('phar'),
            'package1',
        );
        $this->builder->addRequiredExtension(
            new Extension('openssl'),
            'package3',
        );
        $this->builder->addProvidedExtension(
            new Extension('http'),
            'package3',
        );

        $expectedBuiltRequirements = new Requirements([
            Requirement::forRequiredExtension('openssl', 'package3'),
            Requirement::forRequiredExtension('phar', 'package1'),
        ]);
        $expectedAllRequirements = new Requirements([
            Requirement::forRequiredExtension('http', 'package1'),
            Requirement::forRequiredExtension('http', 'package2'),
            Requirement::forProvidedExtension('http', 'package3'),
            Requirement::forRequiredExtension('openssl', 'package3'),
            Requirement::forRequiredExtension('phar', 'package1'),
        ]);

        $this->assertBuiltRequirementsEquals($expectedBuiltRequirements);
        $this->assertAllRequirementsEquals($expectedAllRequirements);
    }

    public function test_it_does_not_remove_extension_conflicts_if_they_are_provided(): void
    {
        $this->builder->addRequiredExtension(
            new Extension('http'),
            'package1',
        );
        $this->builder->addRequiredExtension(
            new Extension('http'),
            'package2',
        );
        $this->builder->addRequiredExtension(
            new Extension('phar'),
            'package1',
        );
        $this->builder->addRequiredExtension(
            new Extension('openssl'),
            'package3',
        );
        $this->builder->addProvidedExtension(
            new Extension('http'),
            'package3',
        );

        $expectedBuiltRequirements = new Requirements([
            Requirement::forRequiredExtension('openssl', 'package3'),
            Requirement::forRequiredExtension('phar', 'package1'),
        ]);
        $expectedAllRequirements = new Requirements([
            Requirement::forRequiredExtension('http', 'package1'),
            Requirement::forRequiredExtension('http', 'package2'),
            Requirement::forProvidedExtension('http', 'package3'),
            Requirement::forRequiredExtension('openssl', 'package3'),
            Requirement::forRequiredExtension('phar', 'package1'),
        ]);

        $this->assertBuiltRequirementsEquals($expectedBuiltRequirements);
        $this->assertAllRequirementsEquals($expectedAllRequirements);
    }

    public function test_it_can_have_an_extension_that_is_required_and_conflicting_at_the_same_time(): void
    {
        // This scenario does not really make sense but ensuring this does not happen is Composer's job not Box.
        $this->builder->addRequiredExtension(
            new Extension('http'),
            'package1',
        );
        $this->builder->addConflictingExtension(
            new Extension('http'),
            'package2',
        );

        $expected = new Requirements([
            Requirement::forRequiredExtension('http', 'package1'),
            Requirement::forConflictingExtension('http', 'package2'),
        ]);

        $builtRequirements = $this->builder->build();
        $allRequirements = $this->builder->all();

        self::assertEquals($expected, $builtRequirements);
        self::assertEquals($expected, $allRequirements);
    }

    // TODO: this could be solved
    public function test_it_does_not_remove_predefined_requirements_even_if_they_are_provided(): void
    {
        $predefinedRequirement = Requirement::forRequiredExtension('http', null);

        $this->builder->addRequirement($predefinedRequirement);
        $this->builder->addProvidedExtension(
            new Extension('http'),
            'package3',
        );

        $expected = new Requirements([$predefinedRequirement]);

        $actual = $this->builder->build();

        self::assertEquals($expected, $actual);
    }

    #[DataProvider('requirementsProvider')]
    public function test_it_ensures_the_requirements_built_are_consistent(
        array $predefinedRequirements,
        array $requiredExtensionSourcePairs,
        array $conflictingExtensionSourcePairs,
        array $providedExtensionSourcePairs,
        Requirements $expectedBuiltRequirements,
        Requirements $expectedAllRequirements,
    ): void {
        foreach ($predefinedRequirements as $predefinedRequirement) {
            $this->builder->addRequirement($predefinedRequirement);
        }

        foreach ($requiredExtensionSourcePairs as [$requiredExtension, $source]) {
            $this->builder->addRequiredExtension($requiredExtension, $source);
        }

        foreach ($conflictingExtensionSourcePairs as [$conflictingExtension, $source]) {
            $this->builder->addConflictingExtension($conflictingExtension, $source);
        }

        foreach ($providedExtensionSourcePairs as [$conflictingExtension, $source]) {
            $this->builder->addProvidedExtension($conflictingExtension, $source);
        }

        $this->assertBuiltRequirementsEquals($expectedBuiltRequirements);
        $this->assertAllRequirementsEquals($expectedAllRequirements);
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
            [],
            new Requirements([
                $predefinedRequirementZ,
                $predefinedRequirementNull,
                $predefinedRequirementA,
            ]),
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
            [],
            new Requirements([
                Requirement::forRequiredExtension('noop', null),
                Requirement::forRequiredExtension('noop', 'A'),
                Requirement::forRequiredExtension('noop', 'Z'),
            ]),
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
            [],
            new Requirements([
                Requirement::forRequiredExtension('a-ext', null),
                Requirement::forRequiredExtension('z-ext', null),
            ]),
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
            [],
            new Requirements([
                Requirement::forConflictingExtension('noop', null),
                Requirement::forConflictingExtension('noop', 'A'),
                Requirement::forConflictingExtension('noop', 'Z'),
            ]),
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
            [],
            new Requirements([
                Requirement::forConflictingExtension('a-ext', null),
                Requirement::forConflictingExtension('z-ext', null),
            ]),
            new Requirements([
                Requirement::forConflictingExtension('a-ext', null),
                Requirement::forConflictingExtension('z-ext', null),
            ]),
        ];

        yield 'provided extension sources' => [
            [],
            [],
            [],
            [
                [new Extension('noop'), 'Z'],
                [new Extension('noop'), null],
                [new Extension('noop'), 'A'],
            ],
            new Requirements([]),
            new Requirements([
                Requirement::forProvidedExtension('noop', null),
                Requirement::forProvidedExtension('noop', 'A'),
                Requirement::forProvidedExtension('noop', 'Z'),
            ]),
        ];

        yield 'provided extensions' => [
            [],
            [],
            [],
            [
                [new Extension('z-ext'), null],
                [new Extension('a-ext'), null],
            ],
            new Requirements([]),
            new Requirements([
                Requirement::forProvidedExtension('a-ext', null),
                Requirement::forProvidedExtension('z-ext', null),
            ]),
        ];
    }

    private function assertBuiltRequirementsEquals(Requirements $expected): void
    {
        $actual = $this->builder->build();

        self::assertEquals($expected, $actual);
    }

    private function assertAllRequirementsEquals(Requirements $expected): void
    {
        $actual = $this->builder->all();

        self::assertEquals($expected, $actual);
    }
}
