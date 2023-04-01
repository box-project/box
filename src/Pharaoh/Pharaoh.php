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

/*
 * This file originates from https://github.com/paragonie/pharaoh.
 *
 * For maintenance reasons it had to be in-lined within Box. To simplify the
 * configuration for PHP-CS-Fixer, the original license is in-lined as follows:
 *
 * The MIT License (MIT)
 *
 * Copyright (c) 2015 - 2018 Paragon Initiative Enterprises
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 */

namespace KevinGH\Box\Pharaoh;

use JetBrains\PhpStorm\ArrayShape;
use KevinGH\Box\Console\Command\Extract;
use KevinGH\Box\Phar\CompressionAlgorithm;
use ParagonIE\ConstantTime\Hex;
use Phar;
use PharData;
use PharFileInfo;
use RecursiveIteratorIterator;
use RuntimeException;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;
use function file_exists;
use function getenv;
use function is_readable;
use function iter\mapKeys;
use function iter\toArrayWithKeys;
use function iterator_to_array;
use function KevinGH\Box\FileSystem\make_tmp_dir;
use function KevinGH\Box\FileSystem\remove;
use function random_bytes;
use function Safe\json_decode;

// TODO: rename it to SafePhar

/**
 * @private
 *
 * Pharaoh is a wrapper around Phar. This is necessary because the Phar API is quite limited and will crash if say two
 * PHARs with the same alias are loaded.
 */
final class Pharaoh
{
    private static array $ALGORITHMS;
    private static string $stubfile;
    private static string $phpExecutable;

    private Phar|PharData $phar;
    private string $tmp;
    private string $file;
    private string $fileName;
    private ?string $pubkey;
    private ?string $tmpPubkey = null;
    private array $compressionCount;

    #[ArrayShape(['hash' => 'string', 'hash_type' => 'string'])]
    private ?array $signature;
    private ?SplFileInfo $stub;
    private ?string $version;
    private ?string $metadata;

    /**
     * @var array<string, SplFileInfo>
     */
    private array $files;

    public function __construct(string $file)
    {
        $file = Path::canonicalize($file);

        if (!file_exists($file)) {
            throw InvalidPhar::fileNotFound($file);
        }

        if (!is_readable($file)) {
            throw InvalidPhar::fileNotReadable($file);
        }

        self::initAlgorithms();
        self::initStubFileName();

        $this->file = $file;
        $this->fileName = basename($file);

        $this->tmp = make_tmp_dir('HumbugBox', 'Pharaoh');

        self::dumpPhar($file, $this->tmp);
        [
            $this->pubkey,
            $this->signature,
            $this->stub,
            $this->version,
            $this->metadata,
            $this->files,
        ] = self::loadDumpedPharFiles($this->tmp);
    }

    public function __destruct()
    {
        unset($this->pharInfo);

        if (isset($this->phar)) {
            $path = $this->phar->getPath();
            unset($this->phar);

            Phar::unlinkArchive($path);
        }

        if (null !== $this->tmp) {
            remove($this->tmp);
        }
    }

    // TODO: consider making it internal
    public function getPhar(): Phar|PharData
    {
        return $this->phar;
    }

    public function getTmp(): string
    {
        return $this->tmp;
    }

    public function getTmpPubkey(): ?string
    {
        return $this->tmpPubkey;
    }

    public function getFile(): string
    {
        return $this->file;
    }

    public function getPubkey(): ?string
    {
        return $this->pubkey;
    }

    public function hasPubkey(): bool
    {
        return null !== $this->pubkey;
    }

    public function getFileName(): string
    {
        return $this->fileName;
    }

    public function equals(self $pharInfo): bool
    {
        return
            $pharInfo->getCompressionCount() === $this->getCompressionCount()
            && $pharInfo->getNormalizedMetadata() === $this->getNormalizedMetadata();
    }

    public function getCompressionCount(): array
    {
        if (!isset($this->compressionCount)) {
            $this->compressionCount = self::calculateCompressionCount($this->phar);
        }

        return $this->compressionCount;
    }

    public function getRoot(): string
    {
        // Do not cache the result
        return 'phar://'.str_replace('\\', '/', realpath($this->phar->getPath())).'/';
    }

    public function getVersion(): ?string
    {
        return $this->version;
    }

    public function getNormalizedMetadata(): ?string
    {
        return $this->metadata;
    }

    public function getSignature(): ?array
    {
        return $this->signature;
    }

    public function getStubContent(): ?string
    {
        return $this->stub?->getContents();
    }

    /**
     * @return array<string, SplFileInfo>
     */
    public function getFiles(): array
    {
        return $this->files;
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

    private static function initStubFileName(): void
    {
        if (!isset(self::$stubfile)) {
            self::$stubfile = Hex::encode(random_bytes(12)).'.pharstub';
        }
    }

    private static function dumpPhar(string $file, string $tmp): void
    {
        $extractPharProcess = new Process([
            self::getPhpExecutable(),
            self::getBoxBin(),
            'extract',
            $file,
            $tmp,
            '--quiet',
        ]);
        $extractPharProcess->run();

        if (false === $extractPharProcess->isSuccessful()) {
            throw new InvalidPhar(
                'TODO.',
                0,
                new ProcessFailedException($extractPharProcess),
            );
        }
    }

    /**
     * @return array{?string, ?array{'hash': string, 'hash_type': 'string'}, SplFileInfo, ?string, ?string, array<string, SplFileInfo>}
     */
    private static function loadDumpedPharFiles(string $tmp): array
    {
        $dumpedFiles = toArrayWithKeys(
            mapKeys(
                static fn (string $filePath) => Path::makeRelative($filePath, $tmp),
                Finder::create()
                    ->files()
                    ->ignoreDotFiles(false)
                    ->in($tmp),
            ),
        );

        $pubkey = ($dumpedFiles[Extract::PUBKEY_PATH] ?? null)?->getContents();
        unset($dumpedFiles[Extract::PUBKEY_PATH]);

        $signature = json_decode($dumpedFiles[Extract::SIGNATURE_PATH]->getContents(), true);
        unset($dumpedFiles[Extract::SIGNATURE_PATH]);

        $stub = $dumpedFiles[Extract::STUB_PATH] ?? null;
        unset($dumpedFiles[Extract::STUB_PATH]);

        $version = $dumpedFiles[Extract::VERSION_PATH]->getContents();
        unset($dumpedFiles[Extract::VERSION_PATH]);

        $metadata = $dumpedFiles[Extract::METADATA_PATH]->getContents();
        unset($dumpedFiles[Extract::METADATA_PATH]);

        return [
            $pubkey,
            false === $signature ? null : $signature,
            $stub,
            '' === $version ? null : $version,
            'NULL' === $metadata ? null : $metadata,
            $dumpedFiles,
        ];
    }

    private static function getPhpExecutable(): string
    {
        if (isset(self::$phpExecutable)) {
            return self::$phpExecutable;
        }

        $phpExecutable = (new PhpExecutableFinder())->find();

        if (false === $phpExecutable) {
            throw new RuntimeException('Could not find a PHP executable.');
        }

        self::$phpExecutable = $phpExecutable;

        return self::$phpExecutable;
    }

    private static function getBoxBin(): string
    {
        // TODO: move the constraint strings declaration in one place
        return getenv('BOX_BIN') ?: $_SERVER['SCRIPT_NAME'];
    }

    private static function calculateCompressionCount(Phar|PharData $phar): array
    {
        $count = array_fill_keys(
            self::$ALGORITHMS,
            0,
        );

        if ($phar instanceof PharData) {
            $count[self::$ALGORITHMS[$phar->isCompressed()]] = 1;
        } else {
            $count = self::calculatePharCompressionCount($count, $phar);
        }

        $count['None'] = $count[CompressionAlgorithm::NONE->name];
        unset($count[CompressionAlgorithm::NONE->name]);

        return $count;
    }

    private static function calculatePharCompressionCount(array $count, Phar $phar): array
    {
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
            iterator_to_array(new RecursiveIteratorIterator($phar)),
            $countFile,
            $count,
        );
    }
}
