<?php

declare(strict_types=1);

namespace KevinGH\Box\Phar;

use Phar;
use Webmozart\Assert\Assert;
use function array_flip;
use function array_keys;
use function array_search;

/**
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
        return match($label) {
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
        return match($this) {
            self::BZ2 => 'bz2',
            self::GZ => 'zlib',
            self::NONE => null,
        };
    }
}
