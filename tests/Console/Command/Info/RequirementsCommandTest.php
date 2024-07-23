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

namespace Console\Command\Info;

use Fidry\Console\Test\CommandTester;
use KevinGH\Box\Console\Command\Info\RequirementsCommand;
use KevinGH\Box\RequirementChecker\AppRequirementsFactory;
use KevinGH\Box\RequirementChecker\Requirement;
use KevinGH\Box\RequirementChecker\Requirements;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;

/**
 * @internal
 */
#[CoversClass(RequirementsCommand::class)]
class RequirementsCommandTest extends TestCase
{
    use ProphecyTrait;

    private AppRequirementsFactory|ObjectProphecy $factoryProphecy;
    private CommandTester $commandTester;

    protected function setUp(): void
    {
        $this->factoryProphecy = $this->prophesize(AppRequirementsFactory::class);

        $this->commandTester = CommandTester::fromConsoleCommand(
            new RequirementsCommand($this->factoryProphecy->reveal()),
        );
    }

    #[DataProvider('requirementsProvider')]
    public function test_it_provides_info_about_the_app_requirements(
        Requirements $allRequirements,
        Requirements $optimizedRequirements,
        string $expected,
    ): void {
        $this->factoryProphecy
            ->createUnfiltered(Argument::cetera())
            ->willReturn($allRequirements);

        $this->factoryProphecy
            ->create(Argument::cetera())
            ->willReturn($optimizedRequirements);

        $this->commandTester->execute(['--no-config' => null]);

        $this->commandTester->assertCommandIsSuccessful();
        $display = $this->commandTester->getNormalizedDisplay();

        self::assertSame($expected, $display);
    }

    public static function requirementsProvider(): iterable
    {
        yield 'empty' => [
            new Requirements([]),
            new Requirements([]),
            <<<'OUTPUT'
                No PHP constraint found.

                No extension constraint found.

                The required and provided extensions constraints (see above) are resolved to compute the final required extensions.
                The application does not have any extension constraint.

                No conflicting extension found.

                OUTPUT,
        ];

        yield 'a real case' => [
            new Requirements([
                Requirement::forPHP('>=7.2', null),
                Requirement::forRequiredExtension('http', 'package1'),
                Requirement::forRequiredExtension('http', 'package2'),
                Requirement::forProvidedExtension('http', null),
                Requirement::forRequiredExtension('openssl', 'package1'),
                Requirement::forProvidedExtension('zip', null),
                Requirement::forConflictingExtension('openssl', 'package3'),
                Requirement::forConflictingExtension('phar', 'package1'),
            ]),
            new Requirements([
                Requirement::forRequiredExtension('openssl', 'package1'),
                Requirement::forConflictingExtension('openssl', 'package3'),
                Requirement::forConflictingExtension('phar', 'package1'),
            ]),
            <<<'OUTPUT'
                The following PHP constraints were found:
                ┌─────────────┬────────┐
                │ Constraints │ Source │
                ├─────────────┼────────┤
                │ >=7.2       │ root   │
                └─────────────┴────────┘

                The following extensions constraints were found:
                ┌──────────┬───────────┬──────────┐
                │ Type     │ Extension │ Source   │
                ├──────────┼───────────┼──────────┤
                │ required │ http      │ package1 │
                │ required │ http      │ package2 │
                │ provided │ http      │ root     │
                │ required │ openssl   │ package1 │
                │ provided │ zip       │ root     │
                └──────────┴───────────┴──────────┘

                The required and provided extensions constraints (see above) are resolved to compute the final required extensions.
                The application requires the following extension constraints:
                ┌───────────┬──────────┐
                │ Extension │ Source   │
                ├───────────┼──────────┤
                │ openssl   │ package1 │
                └───────────┴──────────┘

                Conflicting extensions:
                ┌───────────┬──────────┐
                │ Extension │ Source   │
                ├───────────┼──────────┤
                │ openssl   │ package3 │
                │ phar      │ package1 │
                └───────────┴──────────┘

                OUTPUT,
        ];
    }
}
