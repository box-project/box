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

namespace KevinGH\Box\Console\Command;

use Fidry\Console\Command\Command;
use Fidry\Console\ExitCode;
use KevinGH\Box\Test\CommandTestCase;
use KevinGH\Box\Test\RequiresPharReadonlyOff;
use function realpath;

/**
 * @covers \KevinGH\Box\Console\Command\GenerateDockerFile
 *
 * @runTestsInSeparateProcesses This is necessary as instantiating a PHAR in memory may load/autoload some stuff which
 *                              can create undesirable side-effects.
 */
class GenerateDockerFileTest extends CommandTestCase
{
    use RequiresPharReadonlyOff;

    private const FIXTURES_DIR = __DIR__.'/../../../fixtures/docker';

    protected function setUp(): void
    {
        $this->markAsSkippedIfPharReadonlyIsOn();

        parent::setUp();
    }

    protected function getCommand(): Command
    {
        return new GenerateDockerFile();
    }

    public function test_it_generates_a_dockerfile_for_a_given_phar(): void
    {
        $this->commandTester->execute([
            'command' => 'docker',
            'phar' => $pharPath = realpath(self::FIXTURES_DIR.'/simple-phar.phar'),
        ]);

        $expected = <<<OUTPUT

            🐳  Generating a Dockerfile for the PHAR "{$pharPath}"

             [OK] Done

            You can now inspect your Dockerfile file or build your container with:
            $ docker build .

            OUTPUT;

        $this->assertSameOutput($expected, ExitCode::SUCCESS);

        $this->assertFileExists($this->tmp.'/Dockerfile');
    }

    public function test_it_cannot_generate_a_dockerfile_for_a_phar_without_requirements(): void
    {
        $this->commandTester->execute([
            'command' => 'docker',
            'phar' => $pharPath = realpath(self::FIXTURES_DIR.'/simple-phar-without-requirements.phar'),
        ]);

        $expected = <<<OUTPUT

            🐳  Generating a Dockerfile for the PHAR "{$pharPath}"

             [ERROR] Cannot retrieve the requirements for the PHAR. Make sure the PHAR has
                     been built with Box and the requirement checker enabled.


            OUTPUT;

        $this->assertSameOutput($expected, ExitCode::FAILURE);

        $this->assertFileDoesNotExist($this->tmp.'/Dockerfile');
    }

    public function test_it_cannot_generate_a_dockerfile_for_a_corrupted_phar(): void
    {
        $this->commandTester->execute([
            'command' => 'docker',
            'phar' => $pharPath = realpath(self::FIXTURES_DIR.'/simple-corrupted-phar.phar'),
        ]);

        $expected = <<<OUTPUT

            🐳  Generating a Dockerfile for the PHAR "{$pharPath}"

             [ERROR] Cannot retrieve the requirements for the PHAR. Make sure the PHAR has
                     been built with Box and the requirement checker enabled.


            OUTPUT;

        $this->assertSameOutput($expected, ExitCode::FAILURE);
    }
}
