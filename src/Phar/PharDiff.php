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

namespace KevinGH\Box\Phar;

use KevinGH\Box\Console\Command\Extract;
use KevinGH\Box\Pharaoh\PharDiff as ParagoniePharDiff;
use SplFileInfo;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Process\Process;
use function array_diff;
use function array_map;
use function implode;
use function iterator_to_array;
use function str_replace;
use const DIRECTORY_SEPARATOR;

/**
 * @internal
 */
final class PharDiff
{
    private readonly ParagoniePharDiff $diff;
    private readonly PharInfo $pharInfoA;
    private readonly PharInfo $pharInfoB;

    public function __construct(string $pathA, string $pathB)
    {
        [$pharInfoA, $pharInfoB] = array_map(
            static fn (string $path) => new PharInfo($path),
            [$pathA, $pathB],
        );

        $this->pharInfoA = $pharInfoA;
        $this->pharInfoB = $pharInfoB;

        $diff = new ParagoniePharDiff($pharInfoA, $pharInfoB);
        $diff->setVerbose(true);

        $this->diff = $diff;
    }

    public function getPharInfoA(): PharInfo
    {
        return $this->pharInfoA;
    }

    public function getPharInfoB(): PharInfo
    {
        return $this->pharInfoB;
    }

    public function equals(): bool
    {
        return $this->pharInfoA->equals($this->pharInfoB);
    }

    /**
     * @return null|string|array{string[], string[]}
     */
    public function diff(DiffMode $mode): null|string|array
    {
        if (DiffMode::LIST === $mode) {
            return $this->listDiff();
        }

        return self::getDiff(
            $this->pharInfoA,
            $this->pharInfoB,
            self::getModeCommand($mode),
        );
    }

    private static function getModeCommand(DiffMode $mode): string
    {
        return match ($mode) {
            DiffMode::GIT => 'git diff --no-index',
            DiffMode::GNU => 'diff --exclude='.Extract::PHAR_META_PATH,
        };
    }

    /**
     * @see ParagoniePharDiff::listChecksums()
     */
    public function listChecksums(string $algo = 'sha384'): int
    {
        return $this->diff->listChecksums($algo);
    }

    /**
     * @return string[][] Returns two arrays of strings. The first one contains all the files present in the first PHAR
     *                    which are not in the second and the second array all the files present in the second PHAR but
     *                    not the first one.
     */
    private function listDiff(): array
    {
        $pharAFiles = self::collectFiles($this->pharInfoA);
        $pharBFiles = self::collectFiles($this->pharInfoB);

        return [
            array_diff($pharAFiles, $pharBFiles),
            array_diff($pharBFiles, $pharAFiles),
        ];
    }

    private static function getDiff(PharInfo $pharInfoA, PharInfo $pharInfoB, string $command): ?string
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

    /**
     * @return string[]
     */
    private static function collectFiles(PharInfo $phar): array
    {
        $basePath = $phar->getTmp().DIRECTORY_SEPARATOR;

        return array_map(
            static fn (SplFileInfo $fileInfo): string => str_replace($basePath, '', $fileInfo->getRealPath()),
            iterator_to_array(
                Finder::create()
                    ->files()
                    ->in($basePath)
                    ->ignoreDotFiles(false),
                false,
            ),
        );
    }
}
