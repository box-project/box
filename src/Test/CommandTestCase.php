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

use Fidry\Console\Bridge\Command\SymfonyCommand;
use Fidry\Console\Command\Command;
use Fidry\Console\Test\CommandTester;
use Fidry\Console\Test\OutputAssertions;
use Symfony\Component\Console\Application;

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
        unset($this->command, $this->commandTester);

        parent::tearDown();
    }

    /**
     * Returns the command to be tested.
     *
     * @return Command the command
     */
    abstract protected function getCommand(): Command;

    /**
     * @param callable(string):string $extraNormalizers
     */
    public function assertSameOutput(
        string $expectedOutput,
        int $expectedStatusCode,
        callable ...$extraNormalizers,
    ): void {
        OutputAssertions::assertSameOutput(
            $expectedOutput,
            $expectedStatusCode,
            $this->commandTester,
            ...$extraNormalizers,
        );
    }
}
