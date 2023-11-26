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

namespace KevinGH\Box\Benchmark;

use Fidry\Console\Application\ApplicationRunner;
use Fidry\Console\ExitCode;
use Fidry\Console\IO;
use Fidry\FileSystem\FS;
use KevinGH\Box\Console\Application;
use PhpBench\Attributes\AfterMethods;
use PhpBench\Attributes\BeforeMethods;
use PhpBench\Attributes\Iterations;
use PhpBench\Attributes\ParamProviders;
use PHPUnit\Framework\Assert;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;
use function Safe\chdir;

final readonly class CompileBench
{
    private const WITHOUT_COMPACTORS_DIR = __DIR__.'/../../fixtures/bench/without-compactors';
    private const WITH_COMPACTORS_DIR = __DIR__.'/../../fixtures/bench/with-compactors';

    private ApplicationRunner $runner;

    public function __construct()
    {
        $this->runner = new ApplicationRunner(
            new Application(
                autoExit: false,
                catchExceptions: false,
            ),
        );
    }

    public function setUp(array $params): void
    {
        $workingDirectory = $params[0];

        chdir($workingDirectory);

        self::removeOutputArtifact();
        self::assertVendorsAreInstalled();
    }

    public function tearDown(): void
    {
        self::removeOutputArtifact();
    }

    #[ParamProviders('parameterProvider')]
    #[Iterations(10)]
    #[BeforeMethods('setUp')]
    #[AfterMethods('tearDown')]
    public function bench(array $params): void
    {
        $enableParallelization = $params[1];

        $exitCode = $this->runner->run(
            self::createIO($enableParallelization),
        );

        Assert::assertSame(ExitCode::SUCCESS, $exitCode);
    }

    private static function removeOutputArtifact(): void
    {
        FS::remove(__DIR__.'/../../dist/bench/box.phar');
    }

    private static function assertVendorsAreInstalled(): void
    {
        $vendorDirs = [
            self::WITH_COMPACTORS_DIR.'/vendor',
            self::WITHOUT_COMPACTORS_DIR.'/vendor',
        ];

        foreach ($vendorDirs as $vendorDir) {
            Assert::assertDirectoryExists($vendorDir);
        }
    }

    public static function parameterProvider(): iterable
    {
        yield 'no compactors' => [
            self::WITHOUT_COMPACTORS_DIR,
            false,
        ];

        yield 'with compactors; no parallel processing' => [
            self::WITH_COMPACTORS_DIR,
            false,
        ];

        yield 'with compactors; parallel processing' => [
            self::WITH_COMPACTORS_DIR,
            true,
        ];
    }

    private static function createIO(bool $enableParallelization): IO
    {
        $input = [
            'compile',
            '--no-restart' => null,
        ];

        if (!$enableParallelization) {
            $input['--no-parallel'] = null;
        }

        return new IO(
            new ArrayInput($input),
            new NullOutput(),
        );
    }
}
