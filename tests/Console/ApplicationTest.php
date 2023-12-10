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

namespace KevinGH\Box\Console;

use Fidry\Console\ExitCode;
use Fidry\Console\Test\AppTester;
use Fidry\Console\Test\OutputAssertions;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(Application::class)]
class ApplicationTest extends TestCase
{
    public function test_it_can_display_the_version_when_no_specific_version_is_given(): void
    {
        $application = new Application(
            autoExit: false,
            catchExceptions: false,
        );

        $appTester = AppTester::fromConsoleApp($application);

        $input = ['--version'];

        $appTester->run($input);

        self::assertSame(ExitCode::SUCCESS, $appTester->getStatusCode());

        $expected = <<<'EOF'
            Box version x.x-dev@151e40a

            EOF;

        OutputAssertions::assertSameOutput(
            $expected,
            ExitCode::SUCCESS,
            $appTester,
            DisplayNormalizer::createReplaceBoxVersionNormalizer(),
        );
    }

    public function test_it_can_display_the_version_when_a_specific_version_is_given(): void
    {
        $application = new Application(
            'Box',
            '1.2.3',
            '2018-04-29 19:33:12',
            false,
            false,
        );

        $appTester = AppTester::fromConsoleApp($application);

        $input = ['--version'];

        $appTester->run($input);

        $expected = <<<'EOF'
            Box version 1.2.3 2018-04-29 19:33:12

            EOF;

        OutputAssertions::assertSameOutput(
            $expected,
            ExitCode::SUCCESS,
            $appTester,
        );
    }

    public function test_get_helper_menu(): void
    {
        $application = new Application(
            autoExit: false,
            catchExceptions: false,
        );

        $appTester = AppTester::fromConsoleApp($application);

        $appTester->run([]);

        $expected = <<<'EOF'

                ____
               / __ )____  _  __
              / __  / __ \| |/_/
             / /_/ / /_/ />  <
            /_____/\____/_/|_|


            Box version x.x-dev@151e40a

            Usage:
              command [options] [arguments]

            Options:
              -h, --help            Display help for the given command. When no command is given display help for the list command
              -q, --quiet           Do not output any message
              -V, --version         Display this application version
                  --ansi|--no-ansi  Force (or disable --no-ansi) ANSI output
              -n, --no-interaction  Do not ask any interactive question
              -v|vv|vvv, --verbose  Increase the verbosity of messages: 1 for normal output, 2 for more verbose output and 3 for debug

            Available commands:
              compile                 🔨  Compiles an application into a PHAR
              completion              Dump the shell completion script
              diff                    🕵  Displays the differences between all of the files in two PHARs
              docker                  🐳  Generates a Dockerfile for the given PHAR
              extract                 🚚  Extracts a given PHAR into a directory
              help                    Display help for a command
              info                    🔍  Displays information about the PHAR extension or file
              list                    List commands
              namespace               Prints the first part of the command namespace
              process                 ⚡  Applies the registered compactors and replacement values on a file
              validate                ⚙  Validates the configuration file
              verify                  🔐️  Verifies the PHAR signature
             check
              check:signature         Checks the hash of the signature
             composer
              composer:check-version  Checks if the Composer executable used is compatible with Box
              composer:vendor-dir     Shows the Composer vendor-dir configured
             info
              info:general            🔍  Displays information about the PHAR extension or file
              info:signature          Displays the hash of the signature

            EOF;

        OutputAssertions::assertSameOutput(
            $expected,
            ExitCode::SUCCESS,
            $appTester,
            DisplayNormalizer::createReplaceBoxVersionNormalizer(),
        );
    }

    public function test_can_give_its_long_version(): void
    {
        $defaultApplication = new Application();

        self::assertMatchesRegularExpression(
            '/^<info>Box<\/info> version <comment>.+@[a-z\d]{7}<\/comment>$/',
            $defaultApplication->getLongVersion(),
        );
    }
}
