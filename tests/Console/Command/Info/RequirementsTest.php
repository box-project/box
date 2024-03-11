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
use KevinGH\Box\Console\Command\Info\RequirementsCommand as RequirementsCommand;
use KevinGH\Box\RequirementChecker\AppRequirementsFactory;
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
class RequirementsTest extends TestCase
{
    use ProphecyTrait;

    private const FIXTURES = __DIR__.'/../../../../fixtures/requirement-checker';

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
        Requirements $requirements,
        string $expected,
    ): void {
        $this->factoryProphecy
            ->create(Argument::cetera())
            ->willReturn($requirements);

        $this->commandTester->execute(['--no-config' => null]);

        $this->commandTester->assertCommandIsSuccessful();
        $display = $this->commandTester->getNormalizedDisplay();

        self::assertSame($expected, $display);
    }

    public static function requirementsProvider(): iterable
    {
        yield 'empty' => [
            new Requirements([]),
            '',
        ];

        yield 'a real case' => [,
            '',
        ];

        return;
        yield 'PHAR with requirement checker; one PHP and extension and conflict requirement' => [
            ['phar' => self::FIXTURES.'/req-checker-ext-and-php-and-conflict.phar'],
            <<<'OUTPUT'

                API Version: 1.1.0

                Built with Box: dev-main@b2c33cd

                Archive Compression: None
                Files Compression: None

                Signature: SHA-1
                Signature Hash: 2882E27FCEE2268DB6E18A7BBB8B92906F286458

                Metadata: None

                Timestamp: 1697989559 (2023-10-22T15:45:59+00:00)

                RequirementChecker:
                  Required:
                  - PHP ^7.2 (root)
                  - ext-json (root)
                  Conflict:
                  - ext-aerospike (root)

                Contents: 45 files (148.23KB)

                 // Use the --list|-l option to list the content of the PHAR.


                OUTPUT,
        ];
    }
}
