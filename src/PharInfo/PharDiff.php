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

use function array_map;
use function escapeshellarg;
use ParagonIE\Pharaoh\PharDiff as ParagoniePharDiff;
use function realpath;
use function str_replace;

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

    public function gitDiff(): ?string
    {
        $argA = escapeshellarg($this->pharA->tmp);
        $argB = escapeshellarg($this->pharB->tmp);

        /** @var string $diff */
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
}
