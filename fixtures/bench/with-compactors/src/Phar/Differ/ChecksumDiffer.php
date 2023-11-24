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

namespace BenchTest\Phar\Differ;

use BenchTest\Phar\PharInfo;
use Fidry\Console\IO;
use UnexpectedValueException;
use ValueError;
use function hash;
use function implode;

final class ChecksumDiffer implements Differ
{
    public function __construct(
        private string $checksumAlgorithm,
    ) {
    }

    public function diff(
        PharInfo $pharInfoA,
        PharInfo $pharInfoB,
        IO $io,
    ): void {
        $diff = self::computeDiff(
            $pharInfoA,
            $pharInfoB,
            $this->checksumAlgorithm,
        );

        $io->writeln($diff ?? Differ::NO_DIFF_MESSAGE);
    }

    private static function computeDiff(
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
     * @return array<string, string>
     */
    private static function getFileHashesByRelativePathname(
        PharInfo $pharInfo,
        string $algorithm,
    ): array {
        $hashFiles = [];

        try {
            $hashFiles[$pharInfo->getStubPath()] = hash(
                $algorithm,
                $pharInfo->getStubContent(),
            );

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
