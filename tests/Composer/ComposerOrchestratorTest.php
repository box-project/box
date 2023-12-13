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

namespace KevinGH\Box\Composer;

use Fidry\FileSystem\FileSystem;
use KevinGH\Box\Composer\Throwable\UndetectableComposerVersion;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Psr\Log\NullLogger;
use Symfony\Component\Process\Process;

/**
 * @internal
 */
#[CoversClass(ComposerOrchestrator::class)]
final class ComposerOrchestratorTest extends TestCase
{
    use ProphecyTrait;

    /** @var ObjectProphecy<ComposerProcessFactory> */
    private ObjectProphecy $processFactoryProphecy;
    /** @var ObjectProphecy<Process> */
    private ObjectProphecy $processProphecy;
    private ComposerOrchestrator $orchestrator;

    protected function setUp(): void
    {
        $this->processFactoryProphecy = $this->prophesize(ComposerProcessFactory::class);
        $this->processProphecy = $this->prophesize(Process::class);

        $this->orchestrator = new ComposerOrchestrator(
            $this->processFactoryProphecy->reveal(),
            new NullLogger(),
            new FileSystem(),
        );
    }

    #[DataProvider('outputProvider')]
    public function test_it_can_detect_the_version_from_the_process_output(
        string $output,
        string|UndetectableComposerVersion $expected,
    ): void {
        $this->processFactoryProphecy
            ->getVersionProcess()
            ->willReturn($this->processProphecy->reveal());

        $this->configureProcessProphecy($output);

        if ($expected instanceof UndetectableComposerVersion) {
            $this->expectExceptionObject($expected);
        }

        $actual = $this->orchestrator->getVersion();

        self::assertSame($expected, $actual);
    }

    public static function outputProvider(): iterable
    {
        yield 'nominal' => [
            'Composer version 2.6.3 2023-09-15 09:38:21',
            '2.6.3',
        ];

        yield 'with ANSI' => [
            '[32mComposer[39m version [33m2.6.3[39m 2023-09-15 09:38:21',
            new UndetectableComposerVersion(
                <<<'EOF'
                    Could not determine the Composer version from the following output:
                    [32mComposer[39m version [33m2.6.3[39m 2023-09-15 09:38:21
                    EOF,
            ),
        ];

        yield 'output polluted by deprecated messages' => [
            <<<'EOF'
                PHP Deprecated: ...
                Composer version 2.6.3 2023-09-15 09:38:21
                EOF,
            '2.6.3',
        ];
    }

    public function configureProcessProphecy(string $output): void
    {
        $this->processProphecy
            ->getCommandLine()
            ->willReturn('cmd');

        $this->processProphecy
            ->run()
            ->willReturn(0);

        $this->processProphecy
            ->isSuccessful()
            ->willReturn(true);

        $this->processProphecy
            ->getOutput()
            ->willReturn($output);
    }
}
