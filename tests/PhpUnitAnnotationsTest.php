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

namespace KevinGH\Box;

use function array_filter;
use function chdir;
use function explode;
use const PHP_EOL;
use PHPUnit\Framework\TestCase;
use function shell_exec;
use Symfony\Component\Process\Process;

/**
 * @coversNothing
 */
class PhpUnitAnnotationsTest extends TestCase
{
    /**
     * @runInSeparateProcess
     */
    public function test_there_is_no_commented_PHPUnit_runTestsInSeparateProcesses_annotations_commented(): void
    {
        chdir(__DIR__.'/..');

        $output = shell_exec(
            Process::fromShellCommandline(
                'grep -rlI "\/\/ \* @runTestsInSeparateProcesses" tests'
            )->getCommandLine()
        );

        $files = array_filter(explode(PHP_EOL, (string) $output));

        $this->assertSame([], $files);
    }
}
