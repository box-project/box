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
use function escapeshellarg;
use function iterator_to_array;
use ParagonIE\Pharaoh\PharDiff as ParagoniePharDiff;
use function realpath;
use SplFileInfo;
use function str_replace;
use Symfony\Component\Finder\Finder;

final class PharDiff
{
    /** @var ParagoniePharDiff */
    private $diff;

    /** @var Pharaoh */
    private $pharA;

    /** @var Pharaoh */
    private $pharB;

    public function __construct(string $pathA, string $pathB)
    {
        $phars = array_map(
            static function (string $path): Pharaoh {
                $realPath = realpath($path);

                return new Pharaoh(false !== $realPath ? $realPath : $path);
            },
            [$pathA, $pathB]
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
        $argA = escapeshellarg($this->pharA->tmp);
        $argB = escapeshellarg($this->pharB->tmp);

        /** @var string $diff */
        // TODO: replace by the process component
        $diff = `git diff --no-index $argA $argB`;

        $diff = str_replace(
            $this->pharA->tmp,
            $this->pharA->getFileName(),
            $diff
        );
        $diff = str_replace(
            $this->pharB->tmp,
            $this->pharB->getFileName(),
            $diff
        );

        return '' === $diff ? null : $diff;
    }

    public function gnuDiff(): ?string
    {
        $argA = escapeshellarg($this->pharA->tmp);
        $argB = escapeshellarg($this->pharB->tmp);

        /** @var string $diff */
        // TODO: replace by the process component
        $diff = `diff $argA $argB`;

        $diff = str_replace(
            $this->pharA->tmp,
            $this->pharA->getFileName(),
            $diff
        );
        $diff = str_replace(
            $this->pharB->tmp,
            $this->pharB->getFileName(),
            $diff
        );

        return '' === $diff ? null : $diff;
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
        $pharAFiles = $this->collectFiles($this->pharA);
        $pharBFiles = $this->collectFiles($this->pharB);

        return [
            array_diff($pharAFiles, $pharBFiles),
            array_diff($pharBFiles, $pharAFiles),
        ];
    }

    /**
     * @return string[]
     */
    private function collectFiles(Pharaoh $phar): array
    {
        $basePath = $phar->tmp.DIRECTORY_SEPARATOR;

        return array_map(
            static function (SplFileInfo $fileInfo) use ($basePath): string {
                return str_replace($basePath, '', $fileInfo->getRealPath());
            },
            iterator_to_array(
                Finder::create()
                    ->files()
                    ->in($basePath)
                    ->ignoreDotFiles(false),
                false
            )
        );
    }
}
