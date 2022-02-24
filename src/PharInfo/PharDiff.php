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

namespace KevinGH\Box\PharInfo;

use function array_diff;
use function array_map;
use const DIRECTORY_SEPARATOR;
use function implode;
use function iterator_to_array;
use ParagonIE\Pharaoh\PharDiff as ParagoniePharDiff;
use SplFileInfo;
use function str_replace;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Process\Process;

final class PharDiff
{
    private readonly ParagoniePharDiff $diff;
    private readonly Pharaoh $pharA;
    private readonly Pharaoh $pharB;

    public function __construct(string $pathA, string $pathB)
    {
        $phars = array_map(
            static fn (string $path) => new Pharaoh($path),
            [$pathA, $pathB],
        );

        $this->pharA = $phars[0];
        $this->pharB = $phars[1];

        $diff = new ParagoniePharDiff(...$phars);
        $diff->setVerbose(true);

        $this->diff = $diff;
    }

    public function getPharA(): Pharaoh
    {
        return $this->pharA;
    }

    public function getPharB(): Pharaoh
    {
        return $this->pharB;
    }

    public function gitDiff(): ?string
    {
        return self::getDiff(
            $this->pharA,
            $this->pharB,
            'git diff --no-index',
        );
    }

    public function gnuDiff(): ?string
    {
        return self::getDiff(
            $this->pharA,
            $this->pharB,
            'diff',
        );
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
    public function listDiff(): array
    {
        $pharAFiles = self::collectFiles($this->pharA);
        $pharBFiles = self::collectFiles($this->pharB);

        return [
            array_diff($pharAFiles, $pharBFiles),
            array_diff($pharBFiles, $pharAFiles),
        ];
    }

    private static function getDiff(Pharaoh $pharA, Pharaoh $pharB, string $command): ?string
    {
        $pharATmp = $pharA->tmp;
        $pharBTmp = $pharB->tmp;

        $pharAFileName = $pharA->getFileName();
        $pharBFileName = $pharB->getFileName();

        $diffCommmand = implode(
            ' ',
            [
                $command,
                $pharATmp,
                $pharBTmp,
            ],
        );

        $diffProcess = Process::fromShellCommandline($diffCommmand);
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
                $pharATmp,
                $pharBTmp,
            ],
            [
                $pharAFileName,
                $pharBFileName,
            ],
            $diff,
        );
    }

    /**
     * @return string[]
     */
    private static function collectFiles(Pharaoh $phar): array
    {
        $basePath = $phar->tmp.DIRECTORY_SEPARATOR;

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
