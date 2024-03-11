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

namespace KevinGH\Box\Console\Command\Composer;

use Exception;
use Fidry\Console\ExitCode;
use Fidry\Console\Test\CommandTester;
use Fidry\Console\Test\OutputAssertions;
use KevinGH\Box\Composer\Throwable\IncompatibleComposerVersion;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Path;
use function Safe\chdir;
use function Safe\getcwd;

/**
 * @internal
 */
#[CoversClass(ComposerCheckVersionCommand::class)]
class ComposerCheckVersionCommandTest extends TestCase
{
    private CommandTester $commandTester;
    private string $cwd;

    protected function setUp(): void
    {
        $this->commandTester = CommandTester::fromConsoleCommand(new ComposerCheckVersionCommand());

        $this->cwd = getcwd();
        chdir(__DIR__);
    }

    protected function tearDown(): void
    {
        chdir($this->cwd);
    }

    #[DataProvider('compatibleComposerExecutableProvider')]
    public function test_it_succeeds_the_check_when_the_composer_version_is_compatible(
        array $input,
        array $options,
        string $expectedOutput,
        int $expectedStatusCode,
    ): void {
        $input['command'] = 'composer:check-version';

        $this->commandTester->execute($input, $options);

        OutputAssertions::assertSameOutput(
            $expectedOutput,
            $expectedStatusCode,
            $this->commandTester,
        );
    }

    public static function compatibleComposerExecutableProvider(): iterable
    {
        $compatibleComposerPath = Path::normalize(__DIR__.'/compatible-composer.phar');

        yield 'normal verbosity' => [
            [
                '--composer-bin' => 'compatible-composer.phar',
            ],
            [],
            <<<OUTPUT
                [info] '{$compatibleComposerPath}' '--version' '--no-ansi'
                [info] Version detected: 2.6.3 (Box requires ^2.2.0)

                OUTPUT,
            ExitCode::SUCCESS,
        ];

        yield 'quiet verbosity' => [
            [
                '--composer-bin' => 'compatible-composer.phar',
            ],
            ['verbosity' => OutputInterface::VERBOSITY_QUIET],
            '',
            ExitCode::SUCCESS,
        ];

        yield 'no custom composer' => [
            [],
            ['verbosity' => OutputInterface::VERBOSITY_QUIET],
            '',
            ExitCode::SUCCESS,
        ];
    }

    #[DataProvider('incompatibleComposerExecutableProvider')]
    public function test_it_fails_the_check_when_the_composer_version_is_incompatible(
        array $input,
        array $options,
        Exception $expected,
    ): void {
        $input['command'] = 'composer:check-version';

        $this->expectExceptionObject($expected);

        $this->commandTester->execute($input, $options);
    }

    public static function incompatibleComposerExecutableProvider(): iterable
    {
        yield 'normal verbosity' => [
            [
                '--composer-bin' => 'incompatible-composer.phar',
            ],
            [],
            new IncompatibleComposerVersion(
                'The Composer version "2.0.14" does not satisfy the constraint "^2.2.0".',
            ),
        ];

        yield 'quiet verbosity' => [
            [
                '--composer-bin' => 'incompatible-composer.phar',
            ],
            ['verbosity' => OutputInterface::VERBOSITY_QUIET],
            new IncompatibleComposerVersion(
                'The Composer version "2.0.14" does not satisfy the constraint "^2.2.0".',
            ),
        ];
    }
}
