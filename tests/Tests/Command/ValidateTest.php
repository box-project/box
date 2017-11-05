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

namespace KevinGH\Box\Tests\Command;

use Exception;
use KevinGH\Box\Command\Validate;
use KevinGH\Box\Test\CommandTestCase;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @coversNothing
 */
class ValidateTest extends CommandTestCase
{
    public function testExecute(): void
    {
        file_put_contents('test.json', '{}');

        $tester = $this->getTester();
        $tester->execute(
            [
                'command' => 'validate',
                '--configuration' => 'test.json',
            ],
            [
                'verbosity' => OutputInterface::VERBOSITY_VERBOSE,
            ]
        );

        $expected = <<<'OUTPUT'
Validating the Box configuration file...
The configuration file passed validation.

OUTPUT;

        $this->assertSame($expected, $this->getOutput($tester));
    }

    public function testExecuteNotFound(): void
    {
        $tester = $this->getTester();
        $expected = <<<'OUTPUT'
The configuration file failed validation.

OUTPUT;

        $this->assertSame(1, $tester->execute(['command' => 'validate']));
        $this->assertSame($expected, $this->getOutput($tester));
    }

    public function testExecuteFailed(): void
    {
        file_put_contents('box.json.dist', '{');

        $tester = $this->getTester();
        $exit = $tester->execute(['command' => 'validate']);
        $expected = <<<'OUTPUT'
The configuration file failed validation.

OUTPUT;

        $this->assertSame(1, $exit);
        $this->assertSame($expected, $this->getOutput($tester));
    }

    public function testExecuteFailedVerbose(): void
    {
        file_put_contents('box.json', '{');

        $tester = $this->getTester();

        try {
            $tester->execute(
                [
                    'command' => 'validate',
                ],
                [
                    'verbosity' => OutputInterface::VERBOSITY_VERBOSE,
                ]
            );
        } catch (Exception $exception) {
        }

        $expected = <<<'OUTPUT'
Validating the Box configuration file...
The configuration file failed validation.

OUTPUT;

        $this->assertTrue(isset($exception));
        $this->assertSame($expected, $this->getOutput($tester));
    }

    public function testExecuteInvalidVerbose(): void
    {
        file_put_contents('box.json', '{"test": true}');

        $tester = $this->getTester();

        $tester->execute(
            [
                'command' => 'validate',
            ],
            [
                'verbosity' => OutputInterface::VERBOSITY_VERBOSE,
            ]
        );

        $expected = <<<'OUTPUT'
Validating the Box configuration file...
The configuration file failed validation.

  - The property test is not defined and the definition does not allow additional properties

OUTPUT;

        $this->assertSame($expected, $this->getOutput($tester));
    }

    protected function getCommand()
    {
        return new Validate();
    }
}
