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

namespace KevinGH\Box\Phar\Differ;

use Fidry\Console\IO;
use KevinGH\Box\Phar\PharInfo;
use Symfony\Component\Process\Process;

final readonly class ProcessCommandBasedDiffer implements Differ
{
    public function __construct(private string $command)
    {
    }

    public function diff(PharInfo $pharInfoA, PharInfo $pharInfoB, IO $io): void
    {
        $result = self::getDiff(
            $pharInfoA,
            $pharInfoB,
            $this->command,
        );

        $io->writeln($result ?? Differ::NO_DIFF_MESSAGE);
    }

    public static function getDiff(PharInfo $pharInfoA, PharInfo $pharInfoB, string $command): ?string
    {
        $pharInfoATmp = $pharInfoA->getTmp();
        $pharInfoBTmp = $pharInfoB->getTmp();

        $pharInfoAFileName = $pharInfoA->getFileName();
        $pharInfoBFileName = $pharInfoB->getFileName();

        $diffCommand = implode(
            ' ',
            [
                $command,
                $pharInfoATmp,
                $pharInfoBTmp,
            ],
        );

        $diffProcess = Process::fromShellCommandline($diffCommand);
        $diffProcess->run();

        // We do not check if the process is successful as if there
        // is a difference between the two files then the process
        // _will_ be unsuccessful.
        $diff = trim($diffProcess->getOutput());

        if ('' === $diff) {
            return null;
        }

        return str_replace(
            [
                $pharInfoATmp,
                $pharInfoBTmp,
            ],
            [
                $pharInfoAFileName,
                $pharInfoBFileName,
            ],
            $diff,
        );
    }
}
