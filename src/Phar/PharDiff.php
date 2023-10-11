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
use UnexpectedValueException;
use ValueError;
use function array_diff;
use function array_key_exists;
use function array_map;
use function count;
use function hash_file;
use function implode;
use function iterator_to_array;
use function sprintf;
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
    public function diff(DiffMode $mode, string $checksumAlgorithm): null|string|array
    {
        if (DiffMode::FILE_NAME === $mode) {
            return $this->listDiff();
        }

        if (DiffMode::CHECKSUM === $mode) {
            return self::getChecksumDiff(
                $this->pharInfoA,
                $this->pharInfoB,
                $checksumAlgorithm,
            );
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

    private static function getChecksumDiff(
        PharInfo $pharInfoA,
        PharInfo $pharInfoB,
        string $checksumAlgorithm,
    ): ?string {
        $pharInfoAFileHashes = self::getFileHashesByRelativePathname(
            $pharInfoA,
            $checksumAlgorithm,
        );
        $pharInfoBFileHashes = self::getFileHashesByRelativePathname(
            $pharInfoB,
            $checksumAlgorithm,
        );
        $output = [
            '<diff-expected>--- PHAR A</diff-expected>',
            '<diff-actual>+++ PHAR B</diff-actual>',
            '@@ @@',
        ];

        foreach ($pharInfoAFileHashes as $filePath => $fileAHash) {
            if (!array_key_exists($filePath, $pharInfoBFileHashes)) {
                $output[] = $filePath;
                $output[] = sprintf(
                    "\t<diff-expected>- %s</diff-expected>",
                    $fileAHash,
                );

                continue;
            }

            $fileBHash = $pharInfoBFileHashes[$filePath];
            unset($pharInfoBFileHashes[$filePath]);

            if ($fileAHash === $fileBHash) {
                continue;
            }

            $output[] = $filePath;
            $output[] = sprintf(
                "\t<diff-expected>- %s</diff-expected>",
                $fileAHash,
            );
            $output[] = sprintf(
                "\t<diff-actual>+ %s</diff-actual>",
                $fileBHash,
            );
        }

        foreach ($pharInfoBFileHashes as $filePath => $fileBHash) {
            $output[] = $filePath;
            $output[] = sprintf(
                "\t<diff-actual>+ %s</diff-actual>",
                $fileBHash,
            );
        }

        return 3 === count($output) ? null : implode("\n", $output);
    }

    /**
     * @return string[]
     */
    private static function collectFiles(PharInfo $pharInfo): array
    {
        $basePath = $pharInfo->getTmp().DIRECTORY_SEPARATOR;

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

    /**
     * @return array<string, string>
     */
    private static function getFileHashesByRelativePathname(
        PharInfo $pharInfo,
        string $algorithm,
    ): array {
        $hashFiles = [];

        try {
            foreach ($pharInfo->getFiles() as $file) {
                $hashFiles[$file->getRelativePathname()] = hash_file(
                    $algorithm,
                    $file->getPathname(),
                );
            }
        } catch (ValueError) {
            throw new UnexpectedValueException(
                sprintf(
                    'Unexpected algorithm "%s". Please pick a registered hashing algorithm (checksum `hash_algos()`).',
                    $algorithm,
                ),
            );
        }

        return $hashFiles;
    }
}
