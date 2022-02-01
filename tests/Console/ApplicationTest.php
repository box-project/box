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

use PHPUnit\Framework\TestCase;
use function preg_replace;
use Symfony\Component\Console\Tester\ApplicationTester;

/**
 * @covers \KevinGH\Box\Console\Application
 */
class ApplicationTest extends TestCase
{
    public function test_it_can_display_the_version_when_no_specific_version_is_given(): void
    {
        $application = new Application();
        $application->setAutoExit(false);
        $application->setCatchExceptions(false);

        $appTester = new ApplicationTester($application);

        $input = ['--version'];

        $appTester->run($input);

        $this->assertSame(0, $appTester->getStatusCode());

        $expected = <<<'EOF'
Box version 3.x-dev@151e40a

EOF;

        $actual = preg_replace(
            '/Box version .+@[a-z\d]{7}/',
            'Box version 3.x-dev@151e40a',
            $appTester->getDisplay(true)
        );

        $this->assertSame($expected, $actual);
    }

    public function test_it_can_display_the_version_when_a_specific_version_is_given(): void
    {
        $application = new Application('Box', '1.2.3', '2018-04-29 19:33:12');
        $application->setAutoExit(false);
        $application->setCatchExceptions(false);

        $appTester = new ApplicationTester($application);

        $input = ['--version'];

        $appTester->run($input);

        $this->assertSame(0, $appTester->getStatusCode());

        $expected = <<<'EOF'
Box version 1.2.3 2018-04-29 19:33:12

EOF;

        $actual = $appTester->getDisplay(true);

        $this->assertSame($expected, $actual);
    }

    public function test_get_helper_menu(): void
    {
        $application = new Application();
        $application->setAutoExit(false);
        $application->setCatchExceptions(false);

        $appTester = new ApplicationTester($application);

        $appTester->run([]);

        $expected = <<<'EOF'

    ____
   / __ )____  _  __
  / __  / __ \| |/_/
 / /_/ / /_/ />  <
/_____/\____/_/|_|


Box version 3.x-dev@151e40a

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
  build       Builds a new PHAR (deprecated, use "compile" instead)
  compile     🔨  Compiles an application into a PHAR
  completion  Dump the shell completion script
  diff        🕵  Displays the differences between all of the files in two PHARs
  docker      🐳  Generates a Dockerfile for the given PHAR
  extract     🚚  Extracts a given PHAR into a directory
  help        Display help for a command
  info        🔍  Displays information about the PHAR extension or file
  list        List commands
  process     ⚡  Applies the registered compactors and replacement values on a file
  validate    ⚙  Validates the configuration file
  verify      🔐️  Verifies the PHAR signature

EOF;

        $actual = preg_replace(
            '/Box version .+@[a-z\d]{7}/',
            'Box version 3.x-dev@151e40a',
            $appTester->getDisplay(true)
        );

        $this->assertSame($expected, $actual);
    }
}
