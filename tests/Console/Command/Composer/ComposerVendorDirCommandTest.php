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

use Fidry\Console\ExitCode;
use Fidry\Console\Test\CommandTester;
use Fidry\Console\Test\OutputAssertions;
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
#[CoversClass(ComposerVendorDirCommand::class)]
class ComposerVendorDirCommandTest extends TestCase
{
    private CommandTester $commandTester;
    private string $cwd;

    protected function setUp(): void
    {
        $this->commandTester = CommandTester::fromConsoleCommand(new ComposerVendorDirCommand());

        $this->cwd = getcwd();
        chdir(__DIR__);
    }

    protected function tearDown(): void
    {
        chdir($this->cwd);
    }

    #[DataProvider('composerExecutableProvider')]
    public function test_it_retrieves_the_vendor_bin_directory_path(
        array $input,
        array $options,
        string $expectedOutput,
        int $expectedStatusCode,
    ): void {
        $input['command'] = 'composer:vendor-dir';

        $this->commandTester->execute($input, $options);

        OutputAssertions::assertSameOutput(
            $expectedOutput,
            $expectedStatusCode,
            $this->commandTester,
        );
    }

    public static function composerExecutableProvider(): iterable
    {
        $compatibleComposerPath = Path::normalize(__DIR__.'/compatible-composer.phar');
        $incompatibleComposerPath = Path::normalize(__DIR__.'/incompatible-composer.phar');

        yield 'normal verbosity' => [
            [
                '--composer-bin' => 'compatible-composer.phar',
            ],
            [],
            <<<OUTPUT
                [info] '{$compatibleComposerPath}' 'config' 'vendor-dir' '--no-ansi'
                vendor

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

        yield 'incompatible composer executable; quiet verbosity' => [
            [
                '--composer-bin' => 'incompatible-composer.phar',
            ],
            ['verbosity' => OutputInterface::VERBOSITY_QUIET],
            // The output would be too unstable to test in normal verbosity
            '',
            ExitCode::SUCCESS,
        ];
    }
}
