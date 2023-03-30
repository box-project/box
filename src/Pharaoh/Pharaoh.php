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
use KevinGH\Box\Phar\CompressionAlgorithm;
use KevinGH\Box\Phar\PharPhpSettings;
use ParagonIE\ConstantTime\Hex;
use Phar;
use PharData;
use PharFileInfo;
use RecursiveIteratorIterator;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;
use UnexpectedValueException;
use Webmozart\Assert\Assert;
use function file_exists;
use function KevinGH\Box\FileSystem\copy;
use function KevinGH\Box\FileSystem\dump_file;
use function KevinGH\Box\FileSystem\make_tmp_dir;
use function KevinGH\Box\FileSystem\mkdir;
use function KevinGH\Box\FileSystem\remove;
use function KevinGH\Box\FileSystem\tempnam;
use function KevinGH\Box\unique_id;
use function pathinfo;
use function random_bytes;
use function sys_get_temp_dir;
use const DIRECTORY_SEPARATOR;
use const PATHINFO_EXTENSION;

// TODO: rename it to SafePhar

/**
 * Pharaoh is a wrapper around Phar. This is necessary because the Phar API is quite limited and will crash if say two
 * PHARs with the same alias are loaded.
 */
final class Pharaoh
{
    private static array $ALGORITHMS;
    private static string $stubfile;

    private Phar|PharData $phar;
    private string $tmp;
    private string $file;
    private string $fileName;
    private ?string $pubkey = null;
    private ?string $tmpPubkey = null;
    private array $compressionCount;

    #[ArrayShape(['hash' => 'string', 'hash_type' => 'string'])]
    private array|false $signature;

    public function __construct(string $file)
    {
        $file = Path::canonicalize($file);

        Assert::readable($file);
        Assert::false(
            PharPhpSettings::isReadonly(),
            'Pharaoh cannot be used if phar.readonly is enabled in php.ini',
        );

        self::initAlgorithms();
        self::initStubFileName();

        $this->tmp = make_tmp_dir('HumbugBox', 'Pharaoh');

        $extractPharProcess = new Process([
            $_SERVER['SCRIPT_FILENAME'],
            'extract',
            $file,
            $this->tmp,
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

        $this->file = $file;
        $this->fileName = basename($file);
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
        return 'phar://' . str_replace('\\', '/', realpath($this->phar->getPath())) . '/';
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

    public function getSignature(): false|array
    {
        return $this->signature;
    }

    private function getPharHash(): string
    {
        // If no signature is available (e.g. a tar.gz file), we generate a random hash to ensure
        // it will always be invalidated
        return $this->signature['hash'] ?? unique_id('');
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
            self::$stubfile = Hex::encode(random_bytes(12)) . '.pharstub';
        }
    }

    private static function createTmpDir(): string
    {
        $tmp = tempnam(sys_get_temp_dir(), 'box_');

        remove($tmp);
        mkdir($tmp, 0o755);

        return $tmp;
    }

    private static function createPhar(string $file, string $tmpFile): Phar|PharData
    {
        try {
            return new Phar($tmpFile);
        } catch (UnexpectedValueException $cannotCreatePhar) {
            // Continue
        }

        try {
            return new PharData($tmpFile);
        } catch (UnexpectedValueException) {
            throw InvalidPhar::create($file, $cannotCreatePhar);
        }
    }

    private static function extractPhar(Phar|PharData $phar, string $tmp): void
    {
        // Extract the PHAR content
        $phar->extractTo($tmp);

        // Extract the stub; Phar::extractTo() does not do it since it
        // is internal to the PHAR.
        dump_file(
            $tmp . DIRECTORY_SEPARATOR . self::$stubfile,
            $phar->getStub(),
        );
    }

    private static function getExtension(string $file): string
    {
        $lastExtension = pathinfo($file, PATHINFO_EXTENSION);
        $extension = '';

        while ('' !== $lastExtension) {
            $extension = '.' . $lastExtension . $extension;
            $file = mb_substr($file, 0, -(mb_strlen($lastExtension) + 1));
            $lastExtension = pathinfo($file, PATHINFO_EXTENSION);
        }

        return '' === $extension ? '.phar' : $extension;
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

    private function initPhar(string $file): void
    {
        $extension = self::getExtension($file);

        // We have to give every one a different alias, or it pukes.
        $alias = Hex::encode(random_bytes(16)) . $extension;

        $tmpFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $alias;
        copy($file, $tmpFile, true);

        $pubkey = $file . '.pubkey';
        $tmpPubkey = $tmpFile . '.pubkey';
        $hasPubkey = false;

        if (file_exists($pubkey)) {
            copy($pubkey, $tmpPubkey, true);

            $hasPubkey = true;
            $this->pubkey = $tmpPubkey;
            $this->tmpPubkey = $tmpPubkey;
        }

        $phar = self::createPhar($file, $tmpFile);
        $this->signature = $phar->getSignature();

        if (!($phar instanceof PharData) && !$hasPubkey) {
            $phar->setAlias($alias);
        }

        $this->phar = $phar;
    }
}
