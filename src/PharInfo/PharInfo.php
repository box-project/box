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

use function array_flip;
use function KevinGH\Box\get_phar_compression_algorithms;
use Phar;
use PharData;
use PharFileInfo;
use RecursiveIteratorIterator;
use UnexpectedValueException;

final class PharInfo
{
    private static $ALGORITHMS;

    private $phar;

    public function __construct(string $pharFile)
    {
        if (null === self::$ALGORITHMS) {
            self::$ALGORITHMS = array_flip(get_phar_compression_algorithms());
            self::$ALGORITHMS[Phar::NONE] = 'None';
        }

        try {
            $this->phar = new Phar($pharFile);
        } catch (UnexpectedValueException $exception) {
            $this->phar = new PharData($pharFile);
        }
    }

    public function retrieveCompressionCount(): array
    {
        $count = array_fill_keys(
            self::$ALGORITHMS,
            0
        );

        if ($this->phar instanceof PharData) {
            $count[self::$ALGORITHMS[$this->phar->isCompressed()]] = 1;

            return $count;
        }

        $countFile = static function (array $count, PharFileInfo $file): array {
            if (false === $file->isCompressed()) {
                ++$count['None'];

                return $count;
            }

            foreach (self::$ALGORITHMS as $compressionAlgorithmCode => $compressionAlgorithmName) {
                if ($file->isCompressed($compressionAlgorithmCode)) {
                    ++$count[$compressionAlgorithmName];

                    return $count;
                }
            }

            return $count;
        };

        return array_reduce(
            iterator_to_array(new RecursiveIteratorIterator($this->phar), true),
            $countFile,
            $count
        );
    }

    /**
     * @return Phar|PharData
     */
    public function getPhar()
    {
        return $this->phar;
    }

    public function getRoot(): string
    {
        return 'phar://'.str_replace('\\', '/', realpath($this->phar->getPath())).'/';
    }

    public function getVersion(): string
    {
        return '' !== $this->phar->getVersion() ? $this->phar->getVersion() : 'No information found';
    }

    public function getNormalizedMetadata(): ?string
    {
        $metadata = var_export($this->phar->getMetadata(), true);

        return 'NULL' === $metadata ? null : $metadata;
    }
}
