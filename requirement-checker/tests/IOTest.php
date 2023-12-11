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

namespace KevinGH\RequirementChecker;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use function function_exists;
use function getenv;
use function posix_isatty;
use function putenv;

/**
 * @internal
 */
#[CoversClass(IO::class)]
class IOTest extends TestCase
{
    private static function getDefaultInteractive(): bool
    {
        return !(function_exists('posix_isatty')
            && !@posix_isatty(STDOUT)
            && false === getenv('SHELL_INTERACTIVE'));
    }

    #[DataProvider('provideOptions')]
    public function test_it_can_parse_the_options(array $argv, bool $interactive, int $verbosity): void
    {
        $_SERVER['argv'] = $argv;

        $io = new IO();

        self::assertSame($interactive, $io->isInteractive());
        self::assertSame($verbosity, $io->getVerbosity());
    }

    #[DataProvider('provideOptionsWithShellVerbosity')]
    public function test_it_uses_the_shell_verbosity_environment_variable_over_the_options(array $argv, string $putenv, bool $interactive, int $verbosity): void
    {
        $_SERVER['argv'] = $argv;
        putenv($putenv);

        $io = new IO();

        self::assertSame($interactive, $io->isInteractive());
        self::assertSame($verbosity, $io->getVerbosity());
    }

    public static function provideOptions(): iterable
    {
        yield [
            ['cli.php', '--foo'],
            self::getDefaultInteractive(),
            IO::VERBOSITY_NORMAL,
        ];

        yield [
            ['cli.php', '--foo', '--verbose=0'],
            self::getDefaultInteractive(),
            IO::VERBOSITY_VERBOSE,
        ];

        yield [
            ['cli.php', '--foo', '--quiet'],
            false,
            IO::VERBOSITY_QUIET,
        ];

        yield [
            ['cli.php', '--foo', '-q'],
            false,
            IO::VERBOSITY_QUIET,
        ];

        yield [
            ['cli.php', '--foo', '-vvv'],
            self::getDefaultInteractive(),
            IO::VERBOSITY_DEBUG,
        ];

        yield [
            ['cli.php', '--foo', '--verbose=3'],
            self::getDefaultInteractive(),
            IO::VERBOSITY_DEBUG,
        ];

        yield [
            ['cli.php', '--foo', '--verbose  3'],
            self::getDefaultInteractive(),
            IO::VERBOSITY_DEBUG,
        ];

        yield [
            ['cli.php', '--foo', '-vv'],
            self::getDefaultInteractive(),
            IO::VERBOSITY_VERY_VERBOSE,
        ];

        yield [
            ['cli.php', '--foo', '--verbose=2'],
            self::getDefaultInteractive(),
            IO::VERBOSITY_VERY_VERBOSE,
        ];

        yield [
            ['cli.php', '--foo', '--verbose  2'],
            self::getDefaultInteractive(),
            IO::VERBOSITY_VERY_VERBOSE,
        ];

        yield [
            ['cli.php', '--foo', '-v'],
            self::getDefaultInteractive(),
            IO::VERBOSITY_VERBOSE,
        ];

        yield [
            ['cli.php', '--foo', '--verbose=1'],
            self::getDefaultInteractive(),
            IO::VERBOSITY_VERBOSE,
        ];

        yield [
            ['cli.php', '--foo', '--verbose  '],
            self::getDefaultInteractive(),
            IO::VERBOSITY_VERBOSE,
        ];

        yield [
            ['cli.php', '--no-interaction'],
            false,
            IO::VERBOSITY_NORMAL,
        ];

        yield [
            ['cli.php', '-n'],
            false,
            IO::VERBOSITY_NORMAL,
        ];
    }

    public static function provideOptionsWithShellVerbosity(): iterable
    {
        yield [
            ['cli.php', '--foo'],
            'SHELL_VERBOSITY=-1',
            false,
            IO::VERBOSITY_QUIET,
        ];

        yield [
            ['cli.php', '--foo'],
            'SHELL_VERBOSITY=0',
            self::getDefaultInteractive(),
            IO::VERBOSITY_NORMAL,
        ];

        yield [
            ['cli.php', '--foo'],
            'SHELL_VERBOSITY=1',
            self::getDefaultInteractive(),
            IO::VERBOSITY_VERBOSE,
        ];

        yield [
            ['cli.php', '--foo'],
            'SHELL_VERBOSITY=2',
            self::getDefaultInteractive(),
            IO::VERBOSITY_VERY_VERBOSE,
        ];

        yield [
            ['cli.php', '--foo'],
            'SHELL_VERBOSITY=3',
            self::getDefaultInteractive(),
            IO::VERBOSITY_DEBUG,
        ];
    }
}
