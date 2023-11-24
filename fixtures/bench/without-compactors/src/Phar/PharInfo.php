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

namespace BenchTest\Phar;

use BenchTest\Console\Command\Extract;
use BenchTest\ExecutableFinder;
use Fidry\FileSystem\FS;
use OutOfBoundsException;
use Phar;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;
use function bin2hex;
use function file_exists;
use function is_readable;
use function iter\mapKeys;
use function iter\toArrayWithKeys;
use function random_bytes;
use function sprintf;
use const DIRECTORY_SEPARATOR;

/**
 * @private
 *
 * PharInfo is a wrapper around the native Phar class. Its goal is to provide an equivalent API whilst being in-memory
 * safe.
 *
 * Indeed, the native Phar API is extremely limited due to the fact that it loads the code in-memory. This pollutes the
 * current process and will result in a crash if another PHAR with the same alias is loaded. This PharInfo class
 * circumvents those issues by extracting all the desired information in a separate process.
 */
final class PharInfo
{
    private static array $ALGORITHMS;
    private static string $stubfile;

    private PharMeta $meta;
    private string $tmp;
    private string $file;
    private string $fileName;
    private array $compressionCount;

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

        $this->tmp = FS::makeTmpDir('HumbugBox', 'Pharaoh');

        self::dumpPhar($file, $this->tmp);
        [
            $this->meta,
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

        if (isset($this->tmp)) {
            FS::remove($this->tmp);
        }
    }

    public function getTmp(): string
    {
        return $this->tmp;
    }

    public function getFile(): string
    {
        return $this->file;
    }

    public function getPubKeyContent(): ?string
    {
        return $this->meta->pubKeyContent;
    }

    public function hasPubKey(): bool
    {
        return null !== $this->getPubKeyContent();
    }

    public function getFileName(): string
    {
        return $this->fileName;
    }

    public function equals(self $pharInfo): bool
    {
        return
            $this->contentEquals($pharInfo)
            && $this->getCompression() === $pharInfo->getCompression()
            && $this->getNormalizedMetadata() === $pharInfo->getNormalizedMetadata();
    }

    /**
     * Checks if the content of the given PHAR equals the current one. Note that by content is meant
     * the list of files and their content. The files compression or the PHAR metadata are not considered.
     */
    private function contentEquals(self $pharInfo): bool
    {
        // The signature only checks if the contents are equal (same files, each files same content), but do
        // not check the compression of the files.
        // As a result, we also need to check the compression of each file.
        if ($this->getSignature() != $pharInfo->getSignature()) {
            return false;
        }

        foreach ($this->meta->filesMeta as $file => ['compression' => $compressionAlgorithm]) {
            ['compression' => $otherCompressionAlgorithm] = $this->getFileMeta($file);

            if ($otherCompressionAlgorithm !== $compressionAlgorithm) {
                return false;
            }
        }

        return true;
    }

    public function getCompression(): CompressionAlgorithm
    {
        return $this->meta->compression;
    }

    /**
     * @return array<string, positive-int|0> The number of files per compression algorithm label.
     */
    public function getFilesCompressionCount(): array
    {
        if (!isset($this->compressionCount)) {
            $this->compressionCount = self::calculateCompressionCount($this->meta->filesMeta);
        }

        return $this->compressionCount;
    }

    /**
     * @return array{'compression': CompressionAlgorithm, compressedSize: int}
     */
    public function getFileMeta(string $path): array
    {
        $meta = $this->meta->filesMeta[$path] ?? null;

        if (null === $meta) {
            throw new OutOfBoundsException(
                sprintf(
                    'No metadata found for the file "%s".',
                    $path,
                ),
            );
        }

        return $meta;
    }

    public function getVersion(): ?string
    {
        // TODO: review this fallback value
        return $this->meta->version ?? 'No information found';
    }

    public function getNormalizedMetadata(): ?string
    {
        return $this->meta->normalizedMetadata;
    }

    public function getTimestamp(): int
    {
        return $this->meta->timestamp;
    }

    public function getSignature(): ?array
    {
        return $this->meta->signature;
    }

    public function getStubPath(): string
    {
        return Extract::STUB_PATH;
    }

    public function getStubContent(): ?string
    {
        return $this->meta->stub;
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
            self::$stubfile = bin2hex(random_bytes(12)).'.pharstub';
        }
    }

    private static function dumpPhar(string $file, string $tmp): void
    {
        $extractPharProcess = new Process([
            ExecutableFinder::findPhpExecutable(),
            ExecutableFinder::findBoxExecutable(),
            'extract',
            $file,
            $tmp,
            '--no-interaction',
            '--internal',
        ]);
        $extractPharProcess->run();

        if (false === $extractPharProcess->isSuccessful()) {
            throw new InvalidPhar(
                $extractPharProcess->getErrorOutput(),
                $extractPharProcess->getExitCode(),
                new ProcessFailedException($extractPharProcess),
            );
        }
    }

    /**
     * @return array{PharMeta, array<string, SplFileInfo>}
     */
    private static function loadDumpedPharFiles(string $tmp): array
    {
        $dumpedFiles = toArrayWithKeys(
            mapKeys(
                static fn (string $filePath) => Path::makeRelative($filePath, $tmp),
                Finder::create()
                    ->files()
                    ->ignoreDotFiles(false)
                    ->exclude('.phar')
                    ->in($tmp),
            ),
        );

        $meta = PharMeta::fromJson(FS::getFileContents($tmp.DIRECTORY_SEPARATOR.Extract::PHAR_META_PATH));
        unset($dumpedFiles[Extract::PHAR_META_PATH]);

        return [$meta, $dumpedFiles];
    }

    /**
     * @param array<string, array{'compression': CompressionAlgorithm, compressedSize: int}> $filesMeta
     */
    private static function calculateCompressionCount(array $filesMeta): array
    {
        $count = array_fill_keys(
            self::$ALGORITHMS,
            0,
        );

        foreach ($filesMeta as ['compression' => $compression]) {
            ++$count[$compression->name];
        }

        return $count;
    }
}
