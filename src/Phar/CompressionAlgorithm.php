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

use Phar;
use function array_keys;
use function array_search;

/**
 * The required extension to execute the PHAR now that it is compressed.
 *
 * This is a tiny wrapper around the PHAR compression algorithm
 * to make it a bit more type-safe and convenient to work with.
 *
 * @private
 */
enum CompressionAlgorithm: int
{
    case GZ = Phar::GZ;
    case BZ2 = Phar::BZ2;
    case NONE = Phar::NONE;

    private const LABELS = [
        'GZ' => self::GZ,
        'BZ2' => self::BZ2,
        'NONE' => self::NONE,
    ];

    /**
     * @return list<string>
     */
    public static function getLabels(): array
    {
        return array_keys(self::LABELS);
    }

    public static function fromLabel(?string $label): self
    {
        return match ($label) {
            'BZ2' => self::BZ2,
            'GZ' => self::GZ,
            'NONE', null => self::NONE,
        };
    }

    public function getLabel(): string
    {
        return array_search($this, self::LABELS, true);
    }

    public function getRequiredExtension(): ?string
    {
        return match ($this) {
            self::BZ2 => 'bz2',
            self::GZ => 'zlib',
            self::NONE => null,
        };
    }
}
