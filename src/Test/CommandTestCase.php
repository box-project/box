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

namespace KevinGH\Box\Test;

use Fidry\Console\Application\SymfonyApplication;
use Fidry\Console\Command\Command;
use Fidry\Console\Command\SymfonyCommand;
use Fidry\Console\Test\AppTester;
use Fidry\Console\Test\CommandTester;
use Fidry\Console\Test\OutputAssertions;
use KevinGH\Box\Console\Command\TestConfigurableCommand;
use KevinGH\Box\Console\OutputFormatterConfigurator;
use PHPUnit\Framework\Assert;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command as SymfonyBaseCommand;
use Symfony\Component\Console\Tester\CommandTester as SymfonyCommandTester;
use function feof;
use function fgets;
use KevinGH\Box\Console\Application as BoxApplication;
use const PHP_EOL;
use function preg_replace;
use function rewind;
use function str_replace;
use Symfony\Component\Console\Output\StreamOutput;

/**
 * @private
 */
abstract class CommandTestCase extends FileSystemTestCase
{
    protected CommandTester $commandTester;
    protected Command $command;

    protected function setUp(): void
    {
        parent::setUp();

        $this->command = $this->getCommand();

        $command = new SymfonyCommand($this->command);

        $application = new Application();
        $application->add($command);

        $this->commandTester = new CommandTester(
            $application->get(
                $command->getName(),
            ),
        );
    }

    protected function tearDown(): void
    {
        unset($this->commandTester);

        parent::tearDown();
    }

    /**
     * Returns the command to be tested.
     *
     * @return Command the command
     */
    abstract protected function getCommand(): Command;

    /**
     * @param null|callable(string):string $extraNormalization
     */
    public function assertSameOutput(
        string $expectedOutput,
        int $expectedStatusCode,
        ?callable $extraNormalization = null
    ): void {
        OutputAssertions::assertSameOutput(
            $expectedOutput,
            $expectedStatusCode,
            $this->commandTester,
            $extraNormalization,
        );
    }
}
