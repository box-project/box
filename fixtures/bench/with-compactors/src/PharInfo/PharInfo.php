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

namespace BenchTest\PharInfo;

use BenchTest\Phar\CompressionAlgorithm;
use Phar;
use PharData;
use PharFileInfo;
use RecursiveIteratorIterator;
use UnexpectedValueException;
use function BenchTest\unique_id;
use function realpath;
use function str_replace;

/**
 * @deprecated Deprecated since 4.4.1 in favour of \BenchTest\Phar\PharInfo.
 */
final class PharInfo
{
    private static array $ALGORITHMS;

    private PharData|Phar $phar;

    private ?array $compressionCount = null;
    private ?string $hash = null;

    public function __construct(string $pharFile)
    {
        self::initAlgorithms();

        try {
            $this->phar = new Phar($pharFile);
        } catch (UnexpectedValueException) {
            $this->phar = new PharData($pharFile);
        }
    }

    private static function initAlgorithms(): void
    {
        if (!isset(self::$ALGORITHMS)) {
            self::$ALGORITHMS = [];

            foreach (CompressionAlgorithm::cases() as $compressionAlgorithm) {
                self::$ALGORITHMS[$compressionAlgorithm->value] = $compressionAlgorithm->name;
            }
        }
    }

    public function equals(self $pharInfo): bool
    {
        return
            $pharInfo->getCompressionCount() === $this->getCompressionCount()
            && $pharInfo->getNormalizedMetadata() === $this->getNormalizedMetadata();
    }

    public function getCompressionCount(): array
    {
        if (null === $this->compressionCount || $this->hash !== $this->getPharHash()) {
            $this->compressionCount = $this->calculateCompressionCount();
            $this->compressionCount['None'] = $this->compressionCount[CompressionAlgorithm::NONE->name];
            unset($this->compressionCount[CompressionAlgorithm::NONE->name]);
            $this->hash = $this->getPharHash();
        }

        return $this->compressionCount;
    }

    public function getPhar(): Phar|PharData
    {
        return $this->phar;
    }

    public function getRoot(): string
    {
        // Do not cache the result
        return 'phar://'.str_replace('\\', '/', realpath($this->phar->getPath())).'/';
    }

    public function getVersion(): string
    {
        // Do not cache the result
        return '' !== $this->phar->getVersion() ? $this->phar->getVersion() : 'No information found';
    }

    public function getNormalizedMetadata(): ?string
    {
        // Do not cache the result
        $metadata = var_export($this->phar->getMetadata(), true);

        return 'NULL' === $metadata ? null : $metadata;
    }

    private function getPharHash(): string
    {
        // If no signature is available (e.g. a tar.gz file), we generate a random hash to ensure
        // it will always be invalidated
        return $this->phar->getSignature()['hash'] ?? unique_id('');
    }

    private function calculateCompressionCount(): array
    {
        $count = array_fill_keys(
            self::$ALGORITHMS,
            0,
        );

        if ($this->phar instanceof PharData) {
            $count[self::$ALGORITHMS[$this->phar->isCompressed()]] = 1;

            return $count;
        }

        $countFile = static function (array $count, PharFileInfo $file): array {
            if (false === $file->isCompressed()) {
                ++$count[CompressionAlgorithm::NONE->name];

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
            $count,
        );
    }
}
