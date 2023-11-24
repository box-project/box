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
use UnexpectedValueException;
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
enum SigningAlgorithm: int
{
    case MD5 = Phar::MD5;
    case SHA1 = Phar::SHA1;
    case SHA256 = Phar::SHA256;
    case SHA512 = Phar::SHA512;
    case OPENSSL = Phar::OPENSSL;

    private const LABELS = [
        'MD5' => Phar::MD5,
        'SHA1' => Phar::SHA1,
        'SHA256' => Phar::SHA256,
        'SHA512' => Phar::SHA512,
        'OPENSSL' => Phar::OPENSSL,
    ];

    /**
     * @return list<string>
     */
    public static function getLabels(): array
    {
        return array_keys(self::LABELS);
    }

    public static function fromLabel(string $label): self
    {
        return match ($label) {
            'MD5' => self::MD5,
            'SHA1' => self::SHA1,
            'SHA256' => self::SHA256,
            'SHA512' => self::SHA512,
            'OPENSSL' => self::OPENSSL,
            default => throw new UnexpectedValueException(
                sprintf(
                    'The signing algorithm "%s" is not supported by your current PHAR version.',
                    $label,
                ),
            ),
        };
    }

    public function getLabel(): string
    {
        return array_search($this, self::LABELS, true);
    }
}
